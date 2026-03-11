<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\RMARequest;
use App\Models\RMAComment;
use App\Models\RmaAttachment;
use App\Enums\RMAStatus;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RMAController extends Controller
{
    protected $fileUploadService;

    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }

    /**
     * Get all RMAs for the authenticated customer.
     */
    public function myRmas(Request $request)
    {
        $user = $request->user();

        $rmas = RMARequest::with(['product', 'attachments', 'customer'])
            ->where('customer_id', $user->id)
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->search, function ($q) use ($request) {
                $search = $request->search;
                $q->where(function ($query) use ($search) {
                    $query->where('rma_number', 'like', "%{$search}%")
                        ->orWhereHas('product', fn($pq) => $pq->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate($request->per_page ?? 15);

        // Add formatted data for frontend
        $rmas->through(function ($rma) {
            // Check if status is backed by enum and has label
            if ($rma->status instanceof \App\Enums\RMAStatus) {
                $rma->status_display = $rma->status->label();
            } else {
                $rma->status_display = ucfirst($rma->status);
            }

            if ($rma->priority instanceof \App\Enums\RMAPriority) {
                $rma->priority_display = $rma->priority->label();
            } else {
                $rma->priority_display = ucfirst($rma->priority);
            }

            // Add attachment thumbnails
            $rma->attachments->each(function ($attachment) {
                if ($attachment->isImage()) {
                    $attachment->thumbnail = $attachment->getThumbnailUrl(100, 100);
                }
            });

            return $rma;
        });

        return response()->json([
            'success' => true,
            'data' => $rmas
        ]);
    }

    /**
     * Get customer's RMA statistics.
     */
    public function stats(Request $request)
    {
        $user = $request->user();

        $stats = [
            'total' => RMARequest::where('customer_id', $user->id)->count(),
            'pending' => RMARequest::where('customer_id', $user->id)->where('status', RMAStatus::PENDING)->count(),
            'approved' => RMARequest::where('customer_id', $user->id)->where('status', RMAStatus::APPROVED)->count(),
            'in_repair' => RMARequest::where('customer_id', $user->id)->where('status', RMAStatus::IN_REPAIR)->count(),
            'shipped' => RMARequest::where('customer_id', $user->id)->where('status', RMAStatus::SHIPPED)->count(),
            'completed' => RMARequest::where('customer_id', $user->id)->where('status', RMAStatus::COMPLETED)->count(),
            'rejected' => RMARequest::where('customer_id', $user->id)->where('status', RMAStatus::REJECTED)->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Store a newly created RMA request.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'sale_id' => 'nullable|exists:sales,id',
            'rma_type' => 'required|string|in:simple_return,warranty_repair',
            'reason' => 'required|string',
            'issue_description' => 'required|string',
            'serial_number_provided' => 'nullable|string',
            'receipt_number' => 'nullable|string',

            // Contact info (optional - will use user's info if not provided)
            'contact_name' => 'nullable|string',
            'contact_email' => 'nullable|email',
            'contact_phone' => 'nullable|string',
            'shipping_address' => 'nullable|string',

            // Attachments
            'attachments.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        return DB::transaction(function () use ($request, $validated) {
            $user = $request->user();

            // Set default contact info from user if not provided
            $validated['contact_name'] = $validated['contact_name'] ?? $user->name;
            $validated['contact_email'] = $validated['contact_email'] ?? $user->email;
            $validated['contact_phone'] = $validated['contact_phone'] ?? $user->phone;
            $validated['shipping_address'] = $validated['shipping_address'] ?? $user->address;

            // Add customer_id
            $validated['customer_id'] = $user->id;

            // Set default status
            $validated['status'] = RMAStatus::PENDING;
            $validated['priority'] = 'medium';

            // Auto-determine warranty check requirement
            $rmaType = \App\Enums\RMAType::from($validated['rma_type']);
            $validated['requires_warranty_check'] = $rmaType->requiresWarrantyCheck();

            // Filter out fields not in database
            $rmaData = collect($validated)->except([
                'contact_name',
                'contact_email',
                'contact_phone',
                'shipping_address',
                'attachments'
            ])->toArray();

            // Create RMA
            $rma = RMARequest::create($rmaData);

            $uploadedAttachments = [];

            // Handle file uploads
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    try {
                        $uploaded = $this->fileUploadService->uploadToCloudinary(
                            $file,
                            $user->id,
                            $rma->id
                        );

                        $attachment = $rma->attachments()->create($uploaded);

                        $uploadedAttachments[] = [
                            'id' => $attachment->id,
                            'original_name' => $attachment->original_name,
                            'url' => $attachment->cloudinary_url,
                            'thumbnail' => $attachment->isImage() ? $attachment->getThumbnailUrl(100, 100) : null,
                            'formatted_size' => $attachment->formatted_size,
                        ];

                    } catch (\Exception $e) {
                        Log::error('Client attachment upload failed: ' . $e->getMessage());
                        // Continue with other files
                    }
                }
            }

            // Load relationships
            $rma->load(['product']);

            return response()->json([
                'success' => true,
                'message' => 'RMA request submitted successfully',
                'data' => [
                    'rma' => $rma,
                    'attachments' => $uploadedAttachments
                ]
            ], 201);
        });
    }

    /**
     * Display the specified RMA.
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();

        $rma = RMARequest::with([
            'product',
            'sale',
            'attachments',
            'customer',
            'comments' => function ($q) {
                $q->with('user')->latest();
            },
            'statusHistory' => function ($q) {
                $q->with('changedBy')->latest();
            }
        ])
            ->where('customer_id', $user->id)
            ->findOrFail($id);

        // Add formatted data for frontend
        $rma->status_display = $rma->status->label();
        $rma->priority_display = $rma->priority->label();

        // Process attachments
        $rma->attachments->each(function ($attachment) {
            if ($attachment->isImage()) {
                $attachment->thumbnail = $attachment->getThumbnailUrl(150, 150);
                $attachment->preview = $attachment->getThumbnailUrl(800, 600);
                $attachment->optimized = $attachment->getOptimizedUrl();
            }
        });

        // Process comments - only show external comments to customer
        $rma->comments = $rma->comments->filter(function ($comment) {
            return $comment->type === 'external';
        })->values();

        return response()->json([
            'success' => true,
            'data' => $rma
        ]);
    }

    /**
     * Cancel an RMA (only if pending).
     */
    public function cancel(Request $request, $id)
    {
        $user = $request->user();

        $rma = RMARequest::where('customer_id', $user->id)
            ->where('status', RMAStatus::PENDING)
            ->findOrFail($id);

        return DB::transaction(function () use ($rma, $request) {
            $oldStatus = $rma->status;
            $rma->status = RMAStatus::CANCELLED;
            $rma->save();

            // Record status history
            \App\Models\RMAStatusHistory::create([
                'rma_id' => $rma->id,
                'old_status' => $oldStatus,
                'new_status' => RMAStatus::CANCELLED,
                'changed_by' => $request->user()->id,
                'notes' => 'RMA cancelled by customer',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'RMA cancelled successfully',
                'data' => [
                    'status' => $rma->status,
                    'status_display' => $rma->status->label()
                ]
            ]);
        });
    }

    /**
     * Add a comment to an RMA.
     */
    public function addComment(Request $request, $id)
    {
        $user = $request->user();

        $request->validate([
            'comment' => 'required|string',
        ]);

        $rma = RMARequest::where('customer_id', $user->id)->findOrFail($id);

        $comment = RMAComment::create([
            'rma_id' => $rma->id,
            'user_id' => $user->id,
            'type' => 'external', // Customer comments are always external
            'comment' => $request->comment,
            'notify_customer' => false, // Don't notify themselves
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Comment added successfully',
            'data' => $comment->load('user')
        ]);
    }

    /**
     * Get comments for an RMA.
     */
    public function getComments(Request $request, $id)
    {
        $user = $request->user();

        $rma = RMARequest::where('customer_id', $user->id)->findOrFail($id);

        $comments = RMAComment::with('user')
            ->where('rma_id', $rma->id)
            ->where('type', 'external') // Only show external comments
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $comments
        ]);
    }

    /**
     * Get attachment for download/view.
     */
    public function getAttachment(Request $request, $id)
    {
        $user = $request->user();

        $attachment = RmaAttachment::whereHas('rmaRequest', function ($q) use ($user) {
            $q->where('customer_id', $user->id);
        })->findOrFail($id);

        // Generate URLs for different sizes
        if ($attachment->isImage()) {
            $attachment->urls = [
                'original' => $attachment->cloudinary_url,
                'thumbnail' => $attachment->getThumbnailUrl(100, 100),
                'preview' => $attachment->getThumbnailUrl(800, 600),
                'optimized' => $attachment->getOptimizedUrl(),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $attachment
        ]);
    }

    /**
     * Download an attachment.
     */
    public function downloadAttachment(Request $request, $id)
    {
        $user = $request->user();

        $attachment = RmaAttachment::whereHas('rmaRequest', function ($q) use ($user) {
            $q->where('customer_id', $user->id);
        })->findOrFail($id);

        // Redirect to Cloudinary with download flag
        $downloadUrl = $attachment->cloudinary_url . '?fl_attachment=true';
        return redirect()->away($downloadUrl);
    }

    /**
     * Get return shipping label (if RMA is approved).
     */
    public function getShippingLabel(Request $request, $id)
    {
        $user = $request->user();

        $rma = RMARequest::where('customer_id', $user->id)
            ->whereIn('status', [RMAStatus::APPROVED, RMAStatus::READY_FOR_SHIPMENT])
            ->findOrFail($id);

        if (!$rma->tracking_number) {
            return response()->json([
                'success' => false,
                'message' => 'Shipping label not available yet'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'tracking_number' => $rma->tracking_number,
                'carrier' => $rma->carrier,
                'shipped_at' => $rma->shipped_at,
                'label_url' => $rma->attachments()
                    ->where('original_name', 'like', '%label%')
                    ->first()?->cloudinary_url
            ]
        ]);
    }

    /**
     * Check RMA status.
     */
    public function checkStatus(Request $request, $rmaNumber)
    {
        $rma = RMARequest::with(['product'])
            ->where('rma_number', $rmaNumber)
            ->firstOrFail();

        // Don't expose customer data, just status
        return response()->json([
            'success' => true,
            'data' => [
                'rma_number' => $rma->rma_number,
                'status' => $rma->status,
                'status_display' => $rma->status->label(),
                'product_name' => $rma->product->name,
                'created_at' => $rma->created_at,
                'updated_at' => $rma->updated_at,
            ]
        ]);
    }
}
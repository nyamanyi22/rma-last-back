<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\RMARequest;
use App\Models\RMAComment;
use App\Models\RmaAttachment;
use App\Enums\RMAStatus;
use App\Enums\RMAPriority;
use App\Services\FileUploadService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RMAController extends Controller
{
    protected $fileUploadService;
    protected $notificationService;

    public function __construct(FileUploadService $fileUploadService, NotificationService $notificationService)
    {
        $this->fileUploadService = $fileUploadService;
        $this->notificationService = $notificationService;
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
            if ($rma->status instanceof RMAStatus) {
                $rma->status_display = $rma->status->label();
            } else {
                $rma->status_display = ucfirst($rma->status);
            }

            if ($rma->priority instanceof RMAPriority) {
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

            // Strip private admin fields
            $rma->makeHidden(['admin_notes']);
            if ($rma->status !== RMAStatus::REJECTED) {
                $rma->makeHidden(['rejection_reason', 'customer_message']);
            }

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
    $user = $request->user();

    // Log request for debugging
    Log::info('RMA store request received', [
        'user_id' => $user->id,
        'user_email' => $user->email,
        'request_method' => $request->method(),
        'content_type' => $request->header('Content-Type'),
        'has_files' => $request->hasFile('attachments'),
        'file_count' => $request->hasFile('attachments') ? count($request->file('attachments')) : 0,
        'all_request_data' => $request->all(),
    ]);

    // Validate request
    $validated = $request->validate([
        'product_id' => 'required|exists:products,id',
        'sale_id' => 'nullable|exists:sales,id',
        'rma_type' => 'required|string|in:simple_return,warranty_repair',
        'reason' => 'required|string',
        'issue_description' => 'required|string',
        'serial_number_provided' => 'nullable|string',
        'receipt_number' => 'nullable|string',

        // Optional contact/shipping info
        'contact_name' => 'nullable|string',
        'contact_email' => 'nullable|email',
        'contact_phone' => 'nullable|string',
        'shipping_address' => 'nullable|string',

        // Attachments
        'attachments.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
    ]);

    return DB::transaction(function () use ($request, $validated, $user) {

        // Fill default contact info if not provided
        $validated['contact_name'] = $validated['contact_name'] ?? $user->name;
        $validated['contact_email'] = $validated['contact_email'] ?? $user->email;
        $validated['contact_phone'] = $validated['contact_phone'] ?? $user->phone;
        $validated['shipping_address'] = $validated['shipping_address'] ?? $user->address;

        // Set customer_id
        $validated['customer_id'] = $user->id;

        // Set default RMA status and priority
        $validated['status'] = RMAStatus::PENDING;
        $validated['priority'] = 'medium';

        // Determine warranty check requirement
        $rmaType = \App\Enums\RMAType::from($validated['rma_type']);
        $validated['requires_warranty_check'] = $rmaType->requiresWarrantyCheck();

        // Only keep fields that exist in rma_requests table
        $rmaData = collect($validated)->except([
            'contact_name',
            'contact_email',
            'contact_phone',
            'shipping_address',
            'attachments'
        ])->toArray();

        // Create RMA request
        $rma = RMARequest::create($rmaData);

        Log::info('RMA created successfully', [
            'rma_id' => $rma->id,
            'customer_id' => $user->id,
            'rma_number' => $rma->rma_number,
            'status' => $rma->status,
        ]);

        $uploadedAttachments = [];
        $uploadErrors = [];

        // Handle attachments if any
        if ($request->hasFile('attachments')) {
            $files = is_array($request->file('attachments')) ? $request->file('attachments') : [$request->file('attachments')];

            foreach ($files as $index => $file) {
                try {
                    $uploaded = $this->fileUploadService->uploadToCloudinary($file, $user->id, $rma->id);

                    // Save attachment in database
                    $attachment = $rma->attachments()->create([
                        'uploaded_by' => $user->id,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                        'file_path' => $uploaded['file_path'] ?? null,
                        'cloudinary_public_id' => $uploaded['cloudinary_public_id'] ?? null,
                        'cloudinary_url' => $uploaded['cloudinary_url'] ?? null,
                        'cloudinary_metadata' => null,
                        'storage_type' => $uploaded['storage_type'] ?? 'local',
                    ]);

                    $uploadedAttachments[] = [
                        'id' => $attachment->id,
                        'original_name' => $attachment->original_name,
                        'url' => $attachment->getUrl(),
                        'thumbnail' => $attachment->getThumbnailUrl(100, 100),
                        'formatted_size' => $this->fileUploadService->formatSize($attachment->file_size),
                    ];

                } catch (\Throwable $e) {
                    $errorMessage = $e->getMessage();
                    Log::error('Attachment upload failed', [
                        'user_id' => $user->id,
                        'rma_id' => $rma->id,
                        'file_index' => $index,
                        'file_name' => $file->getClientOriginalName(),
                        'error' => $errorMessage,
                    ]);
                    $uploadErrors[] = "File '{$file->getClientOriginalName()}' failed to upload: {$errorMessage}";
                }
            }
        }

        // If we had upload errors, we should fail the transaction to ensure data integrity
        if (!empty($uploadErrors)) {
            throw new \Exception(implode("\n", $uploadErrors));
        }

        // Load product relationship for response
        $rma->load('product');

        // Send confirmation email
        \Illuminate\Support\Facades\Mail::to($user)->send(new \App\Mail\RmaSubmittedConfirmation($rma));

        // Notify admins
        $this->notificationService->newRmaSubmitted($rma);

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
                $q->with('changer')->latest();
            }
        ])
            ->where('customer_id', $user->id)
            ->findOrFail($id);

        // Add formatted data for frontend
        $rma->status_display = $rma->status->label();
        $rma->priority_display = $rma->priority->label();

        // Process comments - only show external comments to customer
        $rma->comments = $rma->comments->filter(function ($comment) {
            return $comment->type === 'external';
        })->values();

        // Strip private admin fields - never expose admin_notes to the customer
        $rma->makeHidden(['admin_notes']);

        // Only expose rejection details if actually rejected
        if ($rma->status !== RMAStatus::REJECTED) {
            $rma->makeHidden(['rejection_reason', 'customer_message']);
        }

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
                'original' => $attachment->getUrl(),
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

    public function downloadAttachment(Request $request, $id)
    {
        $user = $request->user();

        // Manual token authentication for window.open downloads
        if (!$user && $request->has('token')) {
            $token = \Laravel\Sanctum\PersonalAccessToken::findToken($request->token);
            if ($token && $token->tokenable) {
                $user = $token->tokenable;
            }
        }

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $attachment = RmaAttachment::whereHas('rmaRequest', function ($q) use ($user) {
            $q->where('customer_id', $user->id);
        })->findOrFail($id);

        if ($attachment->storage_type === 'cloudinary' && $attachment->cloudinary_url) {
            // Simply redirect to Cloudinary URL without forcing attachment download
            $downloadUrl = $attachment->cloudinary_url;
            return redirect()->away($downloadUrl);
        }

        if ($attachment->file_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($attachment->file_path)) {
            $fullPath = \Illuminate\Support\Facades\Storage::disk('public')->path($attachment->file_path);
            return response()->file($fullPath, [
                'Content-Disposition' => 'inline; filename="' . str_replace('"', '\"', $attachment->original_name) . '"'
            ]);
        }

        return response()->json(['message' => 'File not found on storage'], 404);
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

<?php

namespace App\Http\Controllers\Api\Admin;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\RMARequest;
use App\Models\RMAComment;
use App\Models\RMAStatusHistory;
use App\Enums\RMAStatus;
use App\Models\RmaAttachment;
use App\Services\FileUploadService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminRMAController extends Controller
{
    protected $fileUploadService;
    protected $notificationService;

    public function __construct(FileUploadService $fileUploadService, NotificationService $notificationService)
    {
        $this->fileUploadService = $fileUploadService;
        $this->notificationService = $notificationService;
    }

    public function index(Request $request)
    {
        $rmas = RMARequest::with(['customer', 'product', 'assignedTo', 'attachments'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->customer_id, fn($q) => $q->where('customer_id', $request->customer_id))
            ->when($request->assigned_to, fn($q) => $q->where('assigned_to', $request->assigned_to))
            ->when($request->rma_type, fn($q) => $q->where('rma_type', $request->rma_type))
            ->when($request->search, function ($q) use ($request) {
                $search = $request->search;
                $q->where(function ($query) use ($search) {
                    $query->where('rma_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn($cq) => $cq->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                        ->orWhereHas('product', fn($pq) => $pq->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate($request->per_page ?? 15);
        // Add transformed URLs for each attachment
        $rmas->through(function ($rma) {
            $rma->attachments->each(function ($attachment) {
                if ($attachment->isImage()) {
                    $attachment->thumbnail = $attachment->getThumbnailUrl(200, 200);
                    $attachment->preview = $attachment->getThumbnailUrl(800, 600);
                    $attachment->optimized = $attachment->getOptimizedUrl();
                }
            });
            return $rma;
        });

        return response()->json([
            'success' => true,
            'data' => $rmas
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            // RMA fields
            'customer_id' => 'required|exists:users,id',
            'product_id' => 'required|exists:products,id',
            'rma_type' => 'required|string',
            'reason' => 'required|string',
            'issue_description' => 'required|string',
            'sale_id' => 'nullable|exists:sales,id',
            'requires_warranty_check' => 'nullable|boolean',
            'is_warranty_valid' => 'nullable|boolean',
            'warranty_expiry_date' => 'nullable|date',
            'serial_number_provided' => 'nullable|string',
            'receipt_number' => 'nullable|string',
            'contact_name' => 'nullable|string',
            'contact_email' => 'nullable|email',
            'contact_phone' => 'nullable|string',
            'shipping_address' => 'nullable|string',
            'priority' => 'nullable|string|in:low,medium,high,urgent',

            // Attachments
            'attachments.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120', // 5MB max
        ]);

        return DB::transaction(function () use ($request, $validated) {
            // Create RMA (without attachments field since we removed it)
            $rma = RMARequest::create($validated);

            $uploadedAttachments = [];

            // Handle file uploads to Cloudinary with automatic compression
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    try {
                        // Upload to Cloudinary using your service (auto-compression happens here)
                        $uploaded = $this->fileUploadService->uploadToCloudinary(
                            $file,
                            $request->user()->id,
                            $rma->id
                        );

                        // Save attachment record
                        $attachment = $rma->attachments()->create($uploaded);

                        // Get compression stats for response
                        $compressionStats = $this->fileUploadService->getCompressionStats($uploaded);

                        $uploadedAttachments[] = [
                            'id' => $attachment->id,
                            'original_name' => $attachment->original_name,
                            'url' => $attachment->cloudinary_url,
                            'thumbnail' => $attachment->isImage() ? $attachment->getThumbnailUrl(200, 200) : null,
                            'preview' => $attachment->isImage() ? $attachment->getThumbnailUrl(800, 600) : null,
                            'optimized' => $attachment->isImage() ? $attachment->getOptimizedUrl() : $attachment->cloudinary_url,
                            'file_size' => $attachment->file_size,
                            'formatted_size' => $attachment->formatted_size,
                            'compression_stats' => $compressionStats,
                        ];

                    } catch (\Exception $e) {
                        Log::error('Attachment upload failed: ' . $e->getMessage());
                        // Continue with other files even if one fails
                    }
                }
            }

            // Load relationships for response
            $rma->load(['customer', 'product']);

            // Notify other admins/system (even if created by admin)
            $this->notificationService->newRmaSubmitted($rma);

            return response()->json([
                'success' => true,
                'message' => 'RMA created successfully',
                'data' => [
                    'rma' => $rma,
                    'attachments' => $uploadedAttachments
                ]
            ], 201);
        });
    }


    public function stats()
    {
        $stats = [
            'total' => RMARequest::count(),
            'pending' => RMARequest::where('status', RMAStatus::PENDING)->count(),
            'under_review' => RMARequest::where('status', RMAStatus::UNDER_REVIEW)->count(),
            'approved' => RMARequest::where('status', RMAStatus::APPROVED)->count(),
            'rejected' => RMARequest::where('status', RMAStatus::REJECTED)->count(),
            'in_repair' => RMARequest::where('status', RMAStatus::IN_REPAIR)->count(),
            'shipped' => RMARequest::where('status', RMAStatus::SHIPPED)->count(),
            'completed' => RMARequest::where('status', RMAStatus::COMPLETED)->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    public function show($id)
    {
        $rma = RMARequest::with([
            'customer',
            'product',
            'sale',
            'assignedTo',
            'approvedBy',
            'comments.user',
            'statusHistory.changedBy',
            'attachments.uploadedBy'
        ])
            ->findOrFail($id);

        // Add transformed URLs and compression stats for convenience
        $rma->attachments->each(function ($attachment) {
            if ($attachment->isImage()) {
                $attachment->thumbnail = $attachment->getThumbnailUrl(200, 200);
                $attachment->preview = $attachment->getThumbnailUrl(800, 600);
                $attachment->optimized = $attachment->getOptimizedUrl();
            }
            // Add compression stats if available
            if ($attachment->cloudinary_metadata) {
                $metadata = $attachment->cloudinary_metadata;
                $attachment->compression_stats = [
                    'original_size' => $metadata['original_bytes'] ?? $attachment->file_size,
                    'compressed_size' => $metadata['bytes'] ?? $attachment->file_size,
                    'savings_percent' => $metadata['compression_percent'] ?? 0,
                ];
            }
        });

        return response()->json([
            'success' => true,
            'data' => $rma
        ]);
    }

    public function update(Request $request, $id)
    {
        $rma = RMARequest::findOrFail($id);

        $request->validate([
            'status' => 'nullable|string',
            'priority' => 'nullable|string',
            'admin_notes' => 'nullable|string',
            'rejection_reason' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($request, $rma) {
            $oldStatus = $rma->status;

            if ($request->has('status') && $request->status !== $rma->status->value) {
                $newStatus = RMAStatus::from($request->status);

                // Optional: Check status transition validity
                // if (!$rma->status->canTransitionTo($newStatus)) {
                //     return response()->json(['message' => 'Invalid status transition'], 422);
                // }

                $rma->status = $newStatus;

                if ($newStatus === RMAStatus::APPROVED) {
                    $rma->approved_by = $request->user()->id;
                    $rma->approved_at = now();
                }

                if ($newStatus === RMAStatus::REJECTED) {
                    $rma->rejection_reason = $request->rejection_reason ?? $rma->rejection_reason;
                }

                // Record status history
                RMAStatusHistory::create([
                    'rma_id' => $rma->id,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'changed_by' => $request->user()->id,
                    'notes' => $request->notes ?? "Status changed from {$oldStatus->value} to {$newStatus->value}",
                ]);

                // Send email notification (queue automatically if implements ShouldQueue)
                \Illuminate\Support\Facades\Mail::to($rma->customer)->send(new \App\Mail\RmaStatusUpdated($rma, $oldStatus->value, $newStatus->value));
            }

            if ($request->has('priority')) {
                $rma->priority = $request->priority;
            }

            if ($request->has('admin_notes')) {
                $rma->admin_notes = $request->admin_notes;
            }

            $rma->save();

            return response()->json([
                'success' => true,
                'message' => 'RMA updated successfully',
                'data' => $rma->load(['customer', 'product', 'assignedTo'])
            ]);
        });
    }

    public function destroy($id)
    {
        $rma = RMARequest::findOrFail($id);

        // Get attachment count for logging
        $attachmentCount = $rma->attachments()->count();

        // Delete RMA - attachments will be automatically deleted from Cloudinary
        // due to the booted method in RmaAttachment model
        $rma->delete();

        return response()->json([
            'success' => true,
            'message' => "RMA deleted successfully along with {$attachmentCount} attachments"
        ]);
    }



    /**
     * Bulk delete RMAs.
     */
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:rma_requests,id'
        ]);

        $count = 0;
        $attachmentCount = 0;

        foreach ($request->ids as $id) {
            $rma = RMARequest::find($id);
            if ($rma) {
                $attachmentCount += $rma->attachments()->count();
                $rma->delete();
                $count++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$count} RMAs deleted successfully along with {$attachmentCount} attachments"
        ]);
    }

    public function bulkUpdateStatus(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:rma_requests,id',
            'status' => 'required|string'
        ]);

        $newStatus = RMAStatus::from($request->status);

        return DB::transaction(function () use ($request, $newStatus) {
            $rmas = RMARequest::whereIn('id', $request->ids)->get();

            foreach ($rmas as $rma) {
                /** @var RMARequest $rma */
                $oldStatus = $rma->status;
                if ($oldStatus !== $newStatus) {
                    $rma->status = $newStatus;
                    $rma->save();

                    RMAStatusHistory::create([
                        'rma_id' => $rma->id,
                        'old_status' => $oldStatus,
                        'new_status' => $newStatus,
                        'changed_by' => $request->user()->id,
                        'notes' => 'Bulk status update',
                    ]);

                    \Illuminate\Support\Facades\Mail::to($rma->customer)->send(new \App\Mail\RmaStatusUpdated($rma, $oldStatus->value, $newStatus->value));
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'RMAs status updated successfully'
            ]);
        });
    }

    public function assign(Request $request, $id)
    {
        $request->validate([
            'assigned_to' => 'required|exists:users,id',
        ]);

        $rma = RMARequest::findOrFail($id);
        $rma->assigned_to = $request->assigned_to;
        $rma->save();

        return response()->json([
            'success' => true,
            'message' => 'RMA assigned successfully',
            'data' => $rma->load('assignedTo')
        ]);
    }

    public function getComments($id)
    {
        $comments = RMAComment::with('user')
            ->where('rma_id', $id)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $comments
        ]);
    }

    public function addComment(Request $request, $id)
    {
        $request->validate([
            'comment' => 'required|string',
            'type' => 'required|in:internal,external',
            'notify_customer' => 'boolean',
        ]);

        $rma = RMARequest::findOrFail($id);

        $comment = RMAComment::create([
            'rma_id' => $rma->id,
            'user_id' => $request->user()->id,
            'type' => $request->type,
            'comment' => $request->comment,
            'notify_customer' => $request->notify_customer ?? false,
        ]);

        if ($comment->type === 'external') {
            \Illuminate\Support\Facades\Mail::to($rma->customer)->send(new \App\Mail\NewRmaComment($rma, $comment));
        }

        return response()->json([
            'success' => true,
            'message' => 'Comment added successfully',
            'data' => $comment->load('user')
        ]);
    }

    public function updateShipping(Request $request, $id)
    {
        $request->validate([
            'tracking_number' => 'required|string',
            'carrier' => 'required|string',
            'shipped_at' => 'nullable|date',
        ]);

        $rma = RMARequest::findOrFail($id);
        $rma->tracking_number = $request->tracking_number;
        $rma->carrier = $request->carrier;
        $rma->shipped_at = $request->shipped_at ?? now();

        // If it was READY_FOR_SHIPMENT, automatically move to SHIPPED
        if ($rma->status === RMAStatus::READY_FOR_SHIPMENT || $rma->status === RMAStatus::APPROVED) {
            $oldStatus = $rma->status;
            $rma->status = RMAStatus::SHIPPED;

            RMAStatusHistory::create([
                'rma_id' => $rma->id,
                'old_status' => $oldStatus,
                'new_status' => RMAStatus::SHIPPED,
                'changed_by' => $request->user()->id,
                'notes' => 'Shipping information updated, status moved to Shipped.',
            ]);

            \Illuminate\Support\Facades\Mail::to($rma->customer)->send(new \App\Mail\RmaStatusUpdated($rma, $oldStatus->value, 'shipped'));
        }

        $rma->save();

        return response()->json([
            'success' => true,
            'message' => 'Shipping information updated successfully',
            'data' => $rma
        ]);
    }

    /**
     * Get attachment by ID with optimized URLs.
     */
    public function getAttachment($id)
    {
        $attachment = RmaAttachment::with(['rmaRequest', 'uploadedBy'])
            ->findOrFail($id);

        // Generate different sized URLs for images
        if ($attachment->isImage()) {
            $attachment->urls = [
                'original' => $attachment->cloudinary_url,
                'thumbnail' => $attachment->getThumbnailUrl(100, 100),
                'small' => $attachment->getThumbnailUrl(300, 300),
                'medium' => $attachment->getThumbnailUrl(800, 600),
                'large' => $attachment->getThumbnailUrl(1200, 900),
                'optimized' => $attachment->getOptimizedUrl(),
            ];
        }

        // Add compression stats
        $attachment->compression_stats = $this->fileUploadService->getCompressionStats($attachment->toArray());

        return response()->json([
            'success' => true,
            'data' => $attachment
        ]);
    }

    /**
     * Delete a specific attachment (removes from Cloudinary too).
     */
    public function deleteAttachment($id)
    {
        $attachment = RmaAttachment::findOrFail($id);

        // Store info for response
        $name = $attachment->original_name;
        $rmaId = $attachment->rma_request_id;

        // Delete - this triggers Cloudinary deletion via model booted method
        $attachment->delete();

        return response()->json([
            'success' => true,
            'message' => "Attachment '{$name}' deleted successfully from RMA #{$rmaId}"
        ]);
    }

    /**
     * Add more attachments to an existing RMA.
     */
    public function addAttachments(Request $request, $id)
    {
        $request->validate([
            'attachments.*' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $rma = RMARequest::findOrFail($id);

        $uploadedAttachments = [];

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                try {
                    $uploaded = $this->fileUploadService->uploadToCloudinary(
                        $file,
                        $request->user()->id,
                        $rma->id
                    );

                    $attachment = $rma->attachments()->create($uploaded);

                    $uploadedAttachments[] = [
                        'id' => $attachment->id,
                        'original_name' => $attachment->original_name,
                        'url' => $attachment->cloudinary_url,
                        'thumbnail' => $attachment->isImage() ? $attachment->getThumbnailUrl(200, 200) : null,
                        'preview' => $attachment->isImage() ? $attachment->getThumbnailUrl(800, 600) : null,
                        'optimized' => $attachment->isImage() ? $attachment->getOptimizedUrl() : $attachment->cloudinary_url,
                        'formatted_size' => $attachment->formatted_size,
                    ];

                } catch (\Exception $e) {
                    Log::error('Attachment upload failed: ' . $e->getMessage());
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to upload: ' . $e->getMessage()
                    ], 500);
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => count($uploadedAttachments) . ' attachments added successfully',
            'data' => [
                'rma_id' => $rma->id,
                'attachments' => $uploadedAttachments
            ]
        ]);
    }

    /**
     * Download an attachment (redirects to Cloudinary URL).
     */
    public function downloadAttachment($id)
    {
        $attachment = RmaAttachment::findOrFail($id);

        // For Cloudinary files, redirect to the URL with forced download flag
        if ($attachment->storage_type === 'cloudinary') {
            // Add flag to force download
            $downloadUrl = $attachment->cloudinary_url . '?fl_attachment=true';
            return redirect()->away($downloadUrl);
        }

        // For legacy local files
        if ($attachment->path && \Storage::disk('public')->exists($attachment->path)) {
            return \Storage::disk('public')->download($attachment->path, $attachment->original_name);
        }

        return response()->json(['message' => 'File not found'], 404);
    }

    /**
     * Get compression statistics for all attachments of an RMA.
     */
    public function getCompressionStats($id)
    {
        $rma = RMARequest::with('attachments')->findOrFail($id);

        $stats = [
            'total_attachments' => $rma->attachments->count(),
            'total_original_size' => 0,
            'total_compressed_size' => 0,
            'total_savings' => 0,
            'average_savings_percent' => 0,
            'attachments' => []
        ];

        foreach ($rma->attachments as $attachment) {
            $attachmentStats = $this->fileUploadService->getCompressionStats($attachment->toArray());

            $stats['total_original_size'] += $attachmentStats['original_size'];
            $stats['total_compressed_size'] += $attachmentStats['compressed_size'];

            $stats['attachments'][] = [
                'id' => $attachment->id,
                'name' => $attachment->original_name,
                'stats' => $attachmentStats
            ];
        }

        $stats['total_savings'] = $stats['total_original_size'] - $stats['total_compressed_size'];
        $stats['total_savings_formatted'] = $this->fileUploadService->formatSize($stats['total_savings']);
        $stats['total_original_formatted'] = $this->fileUploadService->formatSize($stats['total_original_size']);
        $stats['total_compressed_formatted'] = $this->fileUploadService->formatSize($stats['total_compressed_size']);

        if ($stats['total_original_size'] > 0) {
            $stats['average_savings_percent'] = round(
                ($stats['total_savings'] / $stats['total_original_size']) * 100,
                2
            );
        }

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }
}

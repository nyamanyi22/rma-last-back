<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Enums\RMAPriority;
use App\Enums\RMAReason;
use App\Enums\RMAStatus;
use App\Enums\RMAType;
use App\Models\User;
use App\Models\Product;
use App\Models\Sale;
use App\Models\RMAComment;
use App\Models\RMAStatusHistory;
use App\Models\RmaAttachment;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Services\RMANumberGenerator;
use Illuminate\Database\Eloquent\Casts\Attribute;

class RMARequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'rma_requests';

    protected static function booted()
    {
        static::creating(function ($rma) {
            if (!$rma->rma_number) {
                $rma->rma_number = \App\Services\RMANumberGenerator::generate();
            }

            // Automatically check warranty if not already set
            if ($rma->rma_type === \App\Enums\RMAType::WARRANTY_REPAIR && is_null($rma->is_warranty_valid)) {
                try {
                    $warrantyService = app(\App\Services\WarrantyService::class);
                    $sale = null;

                    if ($rma->sale_id) {
                        $sale = \App\Models\Sale::find($rma->sale_id);
                    } elseif ($rma->serial_number_provided) {
                        $sale = $warrantyService->findSale($rma->serial_number_provided, $rma->receipt_number);
                    }

                    if ($sale) {
                        $validation = $warrantyService->validate($sale);
                        $rma->is_warranty_valid = $validation['is_valid'];
                        $rma->warranty_expiry_date = $validation['expiry_date'];
                        if (!$rma->sale_id) {
                            $rma->sale_id = $sale->id;
                        }
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning('Automatic warranty check failed: ' . $e->getMessage());
                }
            }
        });
    }

    protected $fillable = [
        'rma_number',
        'customer_id',
        'product_id',
        'sale_id',
        'rma_type',
        'reason',
        'requires_warranty_check',
        'is_warranty_valid',
        'warranty_expiry_date',
        'serial_number_provided',
        'receipt_number',
        'issue_description',
        'status',
        'priority',
        'rejection_reason',
        'customer_message',
        'admin_notes',
        'assigned_to',
        'approved_by',
        'approved_at',
        'tracking_number',
        'carrier',
        'shipped_at',
        'delivered_at',
    ];

    protected $casts = [
        'rma_type' => RMAType::class,
        'reason' => RMAReason::class,
        'status' => RMAStatus::class,
        'priority' => RMAPriority::class,
        'requires_warranty_check' => 'boolean',
        'is_warranty_valid' => 'boolean',
        'warranty_expiry_date' => 'date',
        'approved_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(RmaAttachment::class, 'rma_request_id');
    }
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(RMAComment::class, 'rma_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(RMAStatusHistory::class, 'rma_id');
    }

    // Status Helpers
    public function isPending(): bool
    {
        return $this->status === RMAStatus::PENDING;
    }
    public function isApproved(): bool
    {
        return $this->status === RMAStatus::APPROVED;
    }
    public function isRejected(): bool
    {
        return $this->status === RMAStatus::REJECTED;
    }
    public function isInRepair()
    {
        return $this->status === RMAStatus::IN_REPAIR;
    }

    public function isShipped()
    {
        return $this->status === RMAStatus::SHIPPED;
    }

    public function isCompleted()
    {
        return $this->status === RMAStatus::COMPLETED;
    }
    // Helper to get all attachment URLs
    public function getAttachmentUrlsAttribute(): array
    {
        return $this->attachments->pluck('cloudinary_url')->toArray();
    }

    // Helper to get first image for thumbnails
    public function getFirstImageAttribute()
    {
        return $this->attachments->first();
    }
    /**
     * Add a comment to the RMA request.
     *
     * @param int $userId
     * @param string $comment
     * @param string $type
     * @param bool $notifyCustomer
     * @return \App\Models\RMAComment
     */
    public function addComment(int $userId, string $comment, string $type = 'external', bool $notifyCustomer = false)
    {
        return $this->comments()->create([
            'user_id' => $userId,
            'comment' => $comment,
            'type' => $type,
            'notify_customer' => $notifyCustomer,
        ]);
    }

    // Contact Info Fallbacks
    protected function contactName(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value ?: ($this->customer ? "{$this->customer->first_name} {$this->customer->last_name}" : null),
        );
    }

    protected function contactEmail(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value ?: ($this->customer ? $this->customer->email : null),
        );
    }

    protected function contactPhone(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value ?: ($this->customer ? $this->customer->phone : null),
        );
    }

    protected function shippingAddress(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value ?: ($this->customer ? $this->customer->address : null),
        );
    }
}

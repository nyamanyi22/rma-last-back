<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Enums\RMAPriority;
use App\Enums\RMAReason;
use App\Enums\RMAStatus;
use App\Enums\RMAType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RMARequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'rma_requests';

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
        'attachments',
        'status',
        'priority',
        'rejection_reason',
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
        'rma_type' => RMAType::class ,
        'reason' => RMAReason::class ,
        'status' => RMAStatus::class ,
        'priority' => RMAPriority::class ,
        'requires_warranty_check' => 'boolean',
        'is_warranty_valid' => 'boolean',
        'warranty_expiry_date' => 'date',
        'attachments' => 'json',
        'approved_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class , 'customer_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class , 'assigned_to');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class , 'approved_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(RMAComment::class , 'rma_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(RMAStatusHistory::class , 'rma_id');
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
}

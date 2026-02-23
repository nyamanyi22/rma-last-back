<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'customer_email',
        'customer_id',
        'product_id',
        'sale_date',
        'amount',
        'serial_number',
        'warranty_months',
        'warranty_expiry_date',
        'payment_method',
        'notes',
    ];

    protected $casts = [
        'sale_date' => 'date',
        'amount' => 'decimal:2',
        'warranty_expiry_date' => 'date',
    ];

    /**
     * Boot method to auto-calculate warranty expiry
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($sale) {
            if (!$sale->warranty_expiry_date && $sale->sale_date && $sale->warranty_months) {
                $sale->warranty_expiry_date = Carbon::parse($sale->sale_date)
                    ->addMonths($sale->warranty_months);
            }
        });

        static::updating(function ($sale) {
            if ($sale->isDirty('sale_date') || $sale->isDirty('warranty_months')) {
                $sale->warranty_expiry_date = Carbon::parse($sale->sale_date)
                    ->addMonths($sale->warranty_months);
            }
        });
    }

    /**
     * Relationships
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class , 'customer_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function rmaRequests()
    {
        return $this->hasMany(RMARequest::class);
    }

    /**
     * Scopes
     */
    public function scopeByCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeByEmail($query, $email)
    {
        return $query->where('customer_email', $email);
    }

    public function scopeByInvoice($query, $invoice)
    {
        return $query->where('invoice_number', 'LIKE', "%{$invoice}%");
    }

    public function scopeWarrantyValid($query)
    {
        return $query->where('warranty_expiry_date', '>=', now());
    }

    public function scopeWarrantyExpired($query)
    {
        return $query->where('warranty_expiry_date', '<', now());
    }

    /**
     * Helper methods
     */
    public function isWarrantyValid(): bool
    {
        return $this->warranty_expiry_date && now()->lte($this->warranty_expiry_date);
    }

    public function getWarrantyStatusAttribute(): string
    {
        if (!$this->warranty_expiry_date)
            return 'Unknown';

        if ($this->isWarrantyValid()) {
            $daysLeft = now()->diffInDays($this->warranty_expiry_date);
            return "Valid ({$daysLeft} days left)";
        }

        return 'Expired';
    }

    public function getWarrantyStatusColorAttribute(): string
    {
        if (!$this->warranty_expiry_date)
            return 'default';
        return $this->isWarrantyValid() ? 'success' : 'error';
    }

    public function linkToUser($userId)
    {
        $this->customer_id = $userId;
        $this->save();
    }
}
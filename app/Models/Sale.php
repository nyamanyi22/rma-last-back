<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Sale extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'invoice_number',
        'customer_email',
        'customer_id',
        'customer_name',
        'product_id',
        'sale_date',
        'amount',
        'quantity',
        'serial_number',
        'warranty_months',
        'warranty_expiry_date',
        'payment_method',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'sale_date' => 'date',
        'amount' => 'decimal:2',
        'warranty_expiry_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The model's default values.
     *
     * @var array
     */
    protected $attributes = [
        'warranty_months' => 12,
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        // BEFORE creating a new sale - auto-calculate warranty
        static::creating(function ($sale) {
            // Ensure product is loaded to get its warranty info
            if ($sale->product_id && !$sale->relationLoaded('product')) {
                $sale->load('product');
            }

            // Set warranty months from product if not manually set
            if (!$sale->warranty_months && $sale->product) {
                $sale->warranty_months = $sale->product->warranty_months ?? 12;
            }

            // Calculate warranty expiry date
            if ($sale->sale_date && $sale->warranty_months) {
                $sale->warranty_expiry_date = Carbon::parse($sale->sale_date)
                    ->addMonths($sale->warranty_months);
            }
        });

        // BEFORE updating - recalculate if dates changed
        static::updating(function ($sale) {
            // If sale date or warranty months changed, recalculate expiry
            if ($sale->isDirty('sale_date') || $sale->isDirty('warranty_months')) {
                if ($sale->sale_date && $sale->warranty_months) {
                    $sale->warranty_expiry_date = Carbon::parse($sale->sale_date)
                        ->addMonths($sale->warranty_months);
                }
            }
        });
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the customer (user) who made this purchase.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class , 'customer_id');
    }

    /**
     * Get the product that was purchased.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the RMA requests associated with this sale.
     */
    public function rmaRequests(): HasMany
    {
        return $this->hasMany(RMARequest::class);
    }

    // =========================================================================
    // QUERY SCOPES
    // =========================================================================

    /**
     * Scope to filter sales by customer ID.
     */
    public function scopeByCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Scope to filter sales by customer email.
     */
    public function scopeByEmail($query, $email)
    {
        return $query->where('customer_email', $email);
    }

    /**
     * Scope to search by invoice number (partial match).
     */
    public function scopeByInvoice($query, $invoice)
    {
        return $query->where('invoice_number', 'LIKE', "%{$invoice}%");
    }

    /**
     * Scope to get only sales with valid warranty.
     */
    public function scopeWarrantyValid($query)
    {
        return $query->where('warranty_expiry_date', '>=', now());
    }

    /**
     * Scope to get only sales with expired warranty.
     */
    public function scopeWarrantyExpired($query)
    {
        return $query->where('warranty_expiry_date', '<', now());
    }

    /**
     * Scope to get sales within a date range.
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('sale_date', [$startDate, $endDate]);
    }

    /**
     * Scope to get sales for a specific product.
     */
    public function scopeForProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    // =========================================================================
    // ACCESSORS (Dynamic Attributes)
    // =========================================================================

    /**
     * Check if warranty is still valid.
     */
    public function getIsWarrantyValidAttribute(): bool
    {
        return $this->warranty_expiry_date && now()->lte($this->warranty_expiry_date);
    }

    /**
     * Get warranty status as human-readable string.
     */
    public function getWarrantyStatusAttribute(): string
    {
        if (!$this->warranty_expiry_date) {
            return 'Unknown';
        }

        if ($this->is_warranty_valid) {
            $daysLeft = now()->diffInDays($this->warranty_expiry_date);
            return "Valid ({$daysLeft} days left)";
        }

        return 'Expired';
    }

    /**
     * Get warranty status color for UI.
     */
    public function getWarrantyStatusColorAttribute(): string
    {
        if (!$this->warranty_expiry_date) {
            return 'default';
        }
        return $this->is_warranty_valid ? 'success' : 'error';
    }

    /**
     * Get formatted amount with currency.
     */
    public function getFormattedAmountAttribute(): string
    {
        return '$' . number_format($this->amount, 2);
    }

    /**
     * Get formatted sale date.
     */
    public function getFormattedSaleDateAttribute(): string
    {
        return $this->sale_date ? $this->sale_date->format('M d, Y') : 'N/A';
    }

    /**
     * Get formatted warranty expiry date.
     */
    public function getFormattedWarrantyExpiryAttribute(): string
    {
        return $this->warranty_expiry_date ? 
            $this->warranty_expiry_date->format('M d, Y') : 'N/A';
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Check if warranty is valid (alias for is_warranty_valid).
     */
    public function isWarrantyValid(): bool
    {
        return $this->is_warranty_valid;
    }

    /**
     * Link this sale to a user account.
     */
    public function linkToUser(int $userId): self
    {
        $this->customer_id = $userId;
        $this->save();

        return $this;
    }

    /**
     * Check if this sale is linked to a user.
     */
    public function isLinkedToUser(): bool
    {
        return !is_null($this->customer_id);
    }

    /**
     * Get the customer name (either from user or email).
     */
    public function getCustomerNameAttribute(): string
    {
        if (!empty($this->attributes['customer_name'])) {
            return $this->attributes['customer_name'];
        }

        if ($this->customer) {
            return $this->customer->full_name;
        }

        // Extract name from email (before @)
        return explode('@', $this->customer_email)[0];
    }

    /**
     * Calculate days remaining in warranty.
     */
    public function getWarrantyDaysRemainingAttribute(): ?int
    {
        if (!$this->warranty_expiry_date) {
            return null;
        }

        if ($this->is_warranty_valid) {
            return now()->diffInDays($this->warranty_expiry_date);
        }

        return 0;
    }

    /**
     * Check if this sale has any RMA requests.
     */
    public function hasRmaRequests(): bool
    {
        return $this->rmaRequests()->exists();
    }
}
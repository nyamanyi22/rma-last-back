<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
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
        'warranty_expiry_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class , 'customer_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isWarrantyValid(): bool
    {
        if (!$this->warranty_expiry_date) {
            return false;
        }

        return $this->warranty_expiry_date->isFuture();
    }
}

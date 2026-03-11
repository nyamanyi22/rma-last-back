<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sku',
        'name',
        'description',
        'category',
        'brand',
        'default_warranty_months',
        'price',
        'stock_quantity',
        'specifications',
        'is_active',
    ];

    protected $casts = [
        'specifications' => 'array',
        'is_active' => 'boolean',
        'price' => 'decimal:2',
        'default_warranty_months' => 'integer',
    ];
    protected $attributes = [
        'is_active' => true,
        'default_warranty_months' => 12,
        'stock_quantity' => 0,
    ];


    // Relationships
    public function rmaRequests()
    {
        return $this->hasMany(RMARequest::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInStock($query)
    {
        return $query->where('stock_quantity', '>', 0);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByBrand($query, $brand)
    {
        return $query->where('brand', $brand);
    }

    // Accessors
    public function getFormattedPriceAttribute(): string
    {
        return '$' . number_format($this->price, 2);
    }

    public function getStockStatusAttribute(): string
    {
        if ($this->stock_quantity > 10)
            return 'In Stock';
        if ($this->stock_quantity > 0)
            return 'Low Stock';
        return 'Out of Stock';
    }

    public function getStockStatusColorAttribute(): string
    {
        if ($this->stock_quantity > 10)
            return 'success';
        if ($this->stock_quantity > 0)
            return 'warning';
        return 'error';
    }

    // Methods
    public function isInStock(): bool
    {
        return $this->stock_quantity > 0;
    }

    public function hasWarranty(): bool
    {
        return $this->default_warranty_months > 0;
    }
    public function calculateWarrantyExpiry($purchaseDate)
    {
        return \Carbon\Carbon::parse($purchaseDate)->addMonths($this->default_warranty_months);
    }


    public function getStockQuantityAttribute($value)
    {
        return $value ?? 0; // Default to 0 if null
    }

}

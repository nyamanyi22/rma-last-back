<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class WarrantyService
{
    /**
     * Validate warranty.
     * Accepts either a Sale object or Product + purchase date.
     *
     * @param Sale|Product $item
     * @param string|null $purchaseDate Required if $item is Product
     * @return array
     */
    public function validate(mixed $item, ?string $purchaseDate = null): array
    {
        if ($item instanceof Sale) {
            $saleDate = Carbon::parse($item->sale_date);
            $warrantyMonths = $item->warranty_months ?? 12;
            $expiryDate = $item->warranty_expiry_date
                ? Carbon::parse($item->warranty_expiry_date)
                : $saleDate->copy()->addMonths($warrantyMonths);
            $hasWarranty = (bool) $item->warranty_months;
        } elseif ($item instanceof Product && $purchaseDate) {
            $saleDate = Carbon::parse($purchaseDate);
            $warrantyMonths = $item->warranty_months ?? 12;
            $expiryDate = $saleDate->copy()->addMonths($warrantyMonths);
            $hasWarranty = $warrantyMonths > 0;
        } else {
            throw new \InvalidArgumentException('Invalid arguments for warranty validation.');
        }

        $now = Carbon::now();
        $isValid = $hasWarranty && $now->lessThanOrEqualTo($expiryDate);
        $status = !$hasWarranty
            ? 'no_warranty'
            : ($isValid ? 'active' : 'expired');

        return [
            'is_valid' => $isValid,
            'status' => $status,
            'expiry_date' => $expiryDate->toDateString(),
            'purchase_date' => $saleDate->toDateString(),
            'warranty_months' => $warrantyMonths,
            'days_remaining' => $isValid ? $now->diffInDays($expiryDate) : 0,
            'expired_at' => $isValid ? null : $expiryDate->toDateString(),
        ];
    }

    /**
     * Find sale by serial number or invoice number
     *
     * @param string|null $serialNumber
     * @param string|null $invoiceNumber
     * @return Sale|null
     */
    public function findSale(?string $serialNumber, ?string $invoiceNumber): ?Sale
    {
        if ($serialNumber) {
            $sale = Sale::where('serial_number', $serialNumber)->first();
            if ($sale) {
                Log::info('Sale found by serial number', ['serial' => $serialNumber, 'sale_id' => $sale->id]);
                return $sale;
            }
        }

        if ($invoiceNumber) {
            $sale = Sale::where('invoice_number', $invoiceNumber)->first();
            if ($sale) {
                Log::info('Sale found by invoice number', ['invoice' => $invoiceNumber, 'sale_id' => $sale->id]);
                return $sale;
            }
        }

        Log::warning('No sale found', ['serial' => $serialNumber, 'invoice' => $invoiceNumber]);
        return null;
    }

    /**
     * Check if under warranty
     *
     * @param Sale|Product $item
     * @param string|null $purchaseDate Required if $item is Product
     * @return bool
     */
    public function isUnderWarranty(mixed $item, ?string $purchaseDate = null): bool
    {
        return $this->validate($item, $purchaseDate)['is_valid'];
    }

    /**
     * Get human-readable warranty status
     *
     * @param Sale|Product $item
     * @param string|null $purchaseDate Required if $item is Product
     * @return string
     */
    public function getStatusText(mixed $item, ?string $purchaseDate = null): string
    {
        $result = $this->validate($item, $purchaseDate);

        return match ($result['status']) {
            'active' => '✅ Active until ' . $result['expiry_date'],
            'expired' => '❌ Expired on ' . $result['expired_at'],
            'no_warranty' => '⚠️ No warranty',
            default => 'Unknown',
        };
    }
}
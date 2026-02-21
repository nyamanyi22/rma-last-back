<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Sale;
use Carbon\Carbon;

class WarrantyService
{
    /**
     * Validate warranty for a given sale and product.
     *
     * @param Sale $sale
     * @return array
     */
    public function validate(Sale $sale): array
    {
        $saleDate = Carbon::parse($sale->sale_date);
        $expiryDate = $sale->warranty_expiry_date
            ?Carbon::parse($sale->warranty_expiry_date)
            : $saleDate->addMonths($sale->warranty_months);

        $isValid = $expiryDate->isFuture();

        return [
            'is_valid' => $isValid,
            'expiry_date' => $expiryDate->toDateString(),
            'purchase_date' => $saleDate->toDateString(),
            'warranty_months' => $sale->warranty_months,
        ];
    }

    /**
     * Check warranty status by serial number or receipt.
     * This is a helper for the RMA submission process.
     *
     * @param string|null $serialNumber
     * @param string|null $invoiceNumber
     * @return Sale|null
     */
    public function findSale(?string $serialNumber, ?string $invoiceNumber): ?Sale
    {
        if ($serialNumber) {
            return Sale::where('serial_number', $serialNumber)->first();
        }

        if ($invoiceNumber) {
            return Sale::where('invoice_number', $invoiceNumber)->first();
        }

        return null;
    }
}

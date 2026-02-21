<?php

namespace App\Services;

use App\Models\RMARequest;
use Carbon\Carbon;

class RMANumberGenerator
{
    /**
     * Generate a unique RMA number.
     * Format: RMA-YYYY-0001
     *
     * @return string
     */
    public static function generate(): string
    {
        $year = Carbon::now()->year;
        $prefix = "RMA-{$year}-";

        $lastRMA = RMARequest::where('rma_number', 'like', "{$prefix}%")
            ->orderBy('rma_number', 'desc')
            ->first();

        if (!$lastRMA) {
            $number = 1;
        }
        else {
            $lastNumber = (int)str_replace($prefix, '', $lastRMA->rma_number);
            $number = $lastNumber + 1;
        }

        return $prefix . str_pad($number, 4, '0', STR_PAD_LEFT);
    }
}

<?php

namespace App\Services;

use App\Models\RMARequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RMANumberGenerator
{
    public static function generate(): string
    {
        return self::generateForDate(Carbon::now());
    }

    public static function generateForDate(Carbon $date): string
    {
        $prefix = "RMA-" . $date->format('Ym') . "-";

        return DB::transaction(function () use ($prefix) {

            $lastRMA = RMARequest::where('rma_number', 'like', $prefix . '%')
                ->orderBy('rma_number', 'desc')
                ->lockForUpdate()
                ->first();

            $sequence = 1;

            if ($lastRMA) {
                // Extract last 4 digits safely using regex
                if (preg_match('/(\d{4})$/', $lastRMA->rma_number, $matches)) {
                    $sequence = ((int) $matches[1]) + 1;
                }
            }

            return $prefix . str_pad($sequence, 4, '0', STR_PAD_LEFT);
        });
    }
}
<?php

namespace App\Enums;

enum RMAType: string
{
    case REFUND = 'refund';
    case REPLACEMENT = 'replacement';
    case REPAIR = 'repair';

    public function label(): string
    {
        return match($this) {
            self::REFUND => 'Refund',
            self::REPLACEMENT => 'Replacement',
            self::REPAIR => 'Repair',
        };
    }
}
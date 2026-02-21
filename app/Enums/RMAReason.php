<?php

namespace App\Enums;

enum RMAReason: string
{
    case DEAD_ON_ARRIVAL = 'dead_on_arrival';
    case DEFECTIVE = 'defective';
    case WRONG_ITEM = 'wrong_item';
    case DAMAGED_IN_SHIPPING = 'damaged_in_shipping';
    case OTHER = 'other';

    public function label(): string
    {
        return match($this) {
            self::DEAD_ON_ARRIVAL => 'Dead on Arrival',
            self::DEFECTIVE => 'Defective',
            self::WRONG_ITEM => 'Wrong Item',
            self::DAMAGED_IN_SHIPPING => 'Damaged in Shipping',
            self::OTHER => 'Other',
        };
    }
}
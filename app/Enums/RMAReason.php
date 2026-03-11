<?php

namespace App\Enums;

enum RMAReason: string
{
    // Return reasons
    case SHIPPING_DAMAGE = 'shipping_damage';
    case WRONG_ITEM = 'wrong_item';
    case DEFECTIVE_ON_ARRIVAL = 'defective_on_arrival';
    case CUSTOMER_RETURN = 'customer_return';

    // Warranty/Repair reasons
    case PRODUCT_FAILURE = 'product_failure';
    case HARDWARE_DEFECT = 'hardware_defect';
    case SOFTWARE_ISSUE = 'software_issue';
    case PHYSICAL_DAMAGE = 'physical_damage';
    case PERFORMANCE_ISSUE = 'performance_issue';

    // Other
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::SHIPPING_DAMAGE => 'Shipping Damage',
            self::WRONG_ITEM => 'Wrong Item Received',
            self::DEFECTIVE_ON_ARRIVAL => 'Defective on Arrival (DOA)',
            self::CUSTOMER_RETURN => 'Customer Return',
            self::PRODUCT_FAILURE => 'Product Failure',
            self::HARDWARE_DEFECT => 'Hardware Defect',
            self::SOFTWARE_ISSUE => 'Software Issue',
            self::PHYSICAL_DAMAGE => 'Physical Damage',
            self::PERFORMANCE_ISSUE => 'Performance Issue',
            self::OTHER => 'Other',
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::SHIPPING_DAMAGE, self::WRONG_ITEM,
            self::DEFECTIVE_ON_ARRIVAL, self::CUSTOMER_RETURN => 'return',
            self::PRODUCT_FAILURE, self::HARDWARE_DEFECT,
            self::SOFTWARE_ISSUE, self::PHYSICAL_DAMAGE,
            self::PERFORMANCE_ISSUE => 'warranty',
            self::OTHER => 'other',
        };
    }
}
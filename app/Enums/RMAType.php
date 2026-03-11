<?php

namespace App\Enums;

enum RMAType: string
{
    case SIMPLE_RETURN = 'simple_return';
    case WARRANTY_REPAIR = 'warranty_repair';

    /**
     * Human readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::SIMPLE_RETURN => 'Simple Return',
            self::WARRANTY_REPAIR => 'Warranty / Repair',
        };
    }

    /**
     * Whether warranty validation is required
     */
    public function requiresWarrantyCheck(): bool
    {
        return match ($this) {
            self::SIMPLE_RETURN => false,
            self::WARRANTY_REPAIR => true,
        };
    }

    /**
     * Used for validation rules
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Used for dropdowns (API / frontend)
     */
    public static function options(): array
    {
        return array_map(
            fn($type) => [
                'value' => $type->value,
                'label' => $type->label()
            ],
            self::cases()
        );
    }
}
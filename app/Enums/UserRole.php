<?php

namespace App\Enums;

enum UserRole: string
{
    case CUSTOMER = 'customer';
    case CSR = 'csr';
    case ADMIN = 'admin';
    case SUPER_ADMIN = 'super_admin';

    public function label(): string
    {
        return match($this) {
            self::CUSTOMER => 'Customer',
            self::CSR => 'Customer Service Representative',
            self::ADMIN => 'Administrator',
            self::SUPER_ADMIN => 'Super Administrator',
        };
    }


    public function isStaff(): bool
    {
        return in_array($this, [self::CSR, self::ADMIN, self::SUPER_ADMIN]);
    }

    public function isAdmin(): bool
    {
        return in_array($this, [self::ADMIN, self::SUPER_ADMIN]);
    }

    public static function staffRoles(): array
    {
        return [self::CSR, self::ADMIN, self::SUPER_ADMIN];
    }

    public static function adminRoles(): array
    {
        return [self::ADMIN, self::SUPER_ADMIN];
    }
}
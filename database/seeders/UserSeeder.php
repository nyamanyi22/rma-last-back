<?php

namespace Database\Seeders;

use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing users (optional - be careful!)
        // User::truncate();

        User::updateOrCreate(['email' => 'admin@example.com'], [
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::SUPER_ADMIN,
            'phone' => '+1234567890',
            'country' => 'US',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        User::updateOrCreate(['email' => 'csr@example.com'], [
            'first_name' => 'CSR',
            'last_name' => 'Agent',
            'email' => 'csr@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::CSR,
            'phone' => '+1234567892',
            'country' => 'US',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        // Create Customers
        User::updateOrCreate(['email' => 'john@example.com'], [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::CUSTOMER,
            'phone' => '+1234567893',
            'country' => 'US',
            'address' => '123 Main St',
            'city' => 'New York',
            'postal_code' => '10001',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        User::updateOrCreate(['email' => 'jane@example.com'], [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::CUSTOMER,
            'phone' => '+1234567894',
            'country' => 'CA',
            'address' => '456 Oak Ave',
            'city' => 'Toronto',
            'postal_code' => 'M5V 2T6',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->command->info('Users seeded successfully!');
    }
}

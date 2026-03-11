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

        // Create Super Admin
        User::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'super@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::SUPER_ADMIN,
            'phone' => '+1234567890',
            'country' => 'US',
            'is_active' => true,
        ]);

        // Create Admin
        User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::ADMIN,
            'phone' => '+1234567891',
            'country' => 'US',
            'is_active' => true,
        ]);

        // Create CSR
        User::create([
            'first_name' => 'CSR',
            'last_name' => 'Agent',
            'email' => 'csr@example.com',
            'password' => Hash::make('password'),
            'role' => UserRole::CSR,
            'phone' => '+1234567892',
            'country' => 'US',
            'is_active' => true,
        ]);

        // Create Customers
        User::create([
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
        ]);

        User::create([
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
        ]);

        $this->command->info('Users seeded successfully!');
    }
}
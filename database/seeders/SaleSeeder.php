<?php

namespace Database\Seeders;

use App\Models\Sale;
use App\Models\User;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class SaleSeeder extends Seeder
{
    public function run(): void
    {
        // Get customer and product IDs
        $customer1 = User::where('email', 'john@example.com')->first();
        $customer2 = User::where('email', 'jane@example.com')->first();
        $products = Product::all();

        if (!$customer1 || !$customer2 || $products->isEmpty()) {
            $this->command->warn('Please run UserSeeder and ProductSeeder first!');
            return;
        }

        $sales = [
            // John's purchases
            [
                'invoice_number' => 'INV-2024-001',
                'customer_email' => $customer1->email,
                'customer_name' => $customer1->full_name,
                'customer_id' => $customer1->id,
                'product_id' => $products[0]->id, // Dell XPS
                'sale_date' => Carbon::now()->subMonths(2),
                'amount' => 1299.99,
                'quantity' => 1,
                'serial_number' => 'DXPS13-001',
                'warranty_months' => 12,
                'payment_method' => 'Credit Card',
                'notes' => 'First purchase',
            ],
            [
                'invoice_number' => 'INV-2024-002',
                'customer_email' => $customer1->email,
                'customer_name' => $customer1->full_name,
                'customer_id' => $customer1->id,
                'product_id' => $products[2]->id, // Samsung Monitor
                'sale_date' => Carbon::now()->subMonths(1),
                'amount' => 349.99,
                'quantity' => 1,
                'serial_number' => 'SAM27-002',
                'warranty_months' => 24,
                'payment_method' => 'PayPal',
                'notes' => 'Office monitor',
            ],

            // Jane's purchases
            [
                'invoice_number' => 'INV-2024-003',
                'customer_email' => $customer2->email,
                'customer_name' => $customer2->full_name,
                'customer_id' => $customer2->id,
                'product_id' => $products[1]->id, // HP Pavilion
                'sale_date' => Carbon::now()->subMonths(3),
                'amount' => 699.99,
                'quantity' => 1,
                'serial_number' => 'HPPAV-003',
                'warranty_months' => 12,
                'payment_method' => 'Credit Card',
                'notes' => 'Student laptop',
            ],
            [
                'invoice_number' => 'INV-2024-004',
                'customer_email' => $customer2->email,
                'customer_name' => $customer2->full_name,
                'customer_id' => $customer2->id,
                'product_id' => $products[3]->id, // Logitech Mouse
                'sale_date' => Carbon::now()->subWeeks(2),
                'amount' => 99.99,
                'quantity' => 2,
                'serial_number' => 'LOG-004, LOG-005',
                'warranty_months' => 12,
                'payment_method' => 'Cash',
                'notes' => 'Gift for family',
            ],
        ];

        foreach ($sales as $sale) {
            Sale::create($sale);
        }

        $this->command->info('Sales seeded successfully!');
    }
}
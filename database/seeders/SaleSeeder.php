<?php

namespace Database\Seeders;

use App\Models\Sale;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class SaleSeeder extends Seeder
{
    public function run(): void
    {
        $customer = User::where('email', 'customer@example.com')->first();
        $products = Product::all();

        if (!$customer || $products->isEmpty()) {
            return;
        }

        // Sale 1: Recent, warranty valid
        Sale::create([
            'invoice_number' => 'INV-2026-001',
            'customer_id' => $customer->id,
            'product_id' => $products->where('sku', 'LAP-DELL-XPS13')->first()->id,
            'sale_date' => Carbon::now()->subMonths(2),
            'amount' => 1200.00,
            'serial_number' => 'SN-DELL-123456',
            'warranty_months' => 12,
            'warranty_expiry_date' => Carbon::now()->addMonths(10),
        ]);

        // Sale 2: Old, warranty expired
        Sale::create([
            'invoice_number' => 'INV-2024-050',
            'customer_id' => $customer->id,
            'product_id' => $products->where('sku', 'MON-LG-27UL')->first()->id,
            'sale_date' => Carbon::now()->subYears(2),
            'amount' => 450.00,
            'serial_number' => 'SN-LG-987654',
            'warranty_months' => 12,
            'warranty_expiry_date' => Carbon::now()->subYears(1),
        ]);
    }
}

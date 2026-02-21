<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'sku' => 'LAP-DELL-XPS13',
                'name' => 'Dell XPS 13 Laptop',
                'description' => '13-inch premium laptop with InfinityEdge display.',
                'category' => 'Laptop',
                'brand' => 'Dell',
                'default_warranty_months' => 12,
                'price' => 1200.00,
                'stock_quantity' => 50,
            ],
            [
                'sku' => 'MON-LG-27UL',
                'name' => 'LG 27-inch 4K Monitor',
                'description' => '4K Ultra Fine display with color accuracy.',
                'category' => 'Monitor',
                'brand' => 'LG',
                'default_warranty_months' => 24,
                'price' => 450.00,
                'stock_quantity' => 30,
            ],
            [
                'sku' => 'ACC-LOGI-MXM3',
                'name' => 'Logitech MX Master 3',
                'description' => 'Advanced wireless mouse for productivity.',
                'category' => 'Accessory',
                'brand' => 'Logitech',
                'default_warranty_months' => 12,
                'price' => 99.00,
                'stock_quantity' => 100,
            ],
            [
                'sku' => 'DSK-HP-ELITE',
                'name' => 'HP EliteDesk 800 G6',
                'description' => 'Powerful business desktop PC.',
                'category' => 'Desktop',
                'brand' => 'HP',
                'default_warranty_months' => 36,
                'price' => 850.00,
                'stock_quantity' => 20,
            ],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }
}

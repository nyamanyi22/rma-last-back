<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    /**
     * Display a listing of products.
     */
    public function index(Request $request)
    {
        $query = Product::query();

        // Filter by category
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        // Filter by brand
        if ($request->has('brand')) {
            $query->where('brand', $request->brand);
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Search by name or SKU
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortField = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $products = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    /**
     * Store a newly created product.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sku' => 'required|string|unique:products,sku|max:50',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|string|max:100',
            'brand' => 'required|string|max:100',
            'default_warranty_months' => 'nullable|integer|min:0|max:120',
            'price' => 'nullable|numeric|min:0',
            'stock_quantity' => 'nullable|integer|min:0',
            'specifications' => 'nullable|json',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $product = Product::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
            'data' => $product
        ], 201);
    }

    /**
     * Display the specified product.
     */
    public function show(Product $product)
    {
        return response()->json([
            'success' => true,
            'data' => $product
        ]);
    }

    /**
     * Update the specified product.
     */
    public function update(Request $request, Product $product)
    {
        $validator = Validator::make($request->all(), [
            'sku' => ['required', 'string', 'max:50', Rule::unique('products')->ignore($product->id)],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|string|max:100',
            'brand' => 'required|string|max:100',
            'default_warranty_months' => 'nullable|integer|min:0|max:120',
            'price' => 'nullable|numeric|min:0',
            'stock_quantity' => 'nullable|integer|min:0',
            'specifications' => 'nullable|json',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $product->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
            'data' => $product
        ]);
    }

    /**
     * Remove the specified product.
     */
    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully'
        ]);
    }

    /**
     * Get all categories.
     */
    public function getCategories()
    {
        $categories = Product::select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    /**
     * Get all brands.
     */
    public function getBrands()
    {
        $brands = Product::select('brand')
            ->distinct()
            ->orderBy('brand')
            ->pluck('brand');

        return response()->json([
            'success' => true,
            'data' => $brands
        ]);
    }

    /**
     * Export products to CSV
     */
    public function export(Request $request)
    {
        $query = Product::query();

        // Filter by selected IDs if provided
        if ($request->has('ids') && is_array($request->ids)) {
            $query->whereIn('id', $request->ids);
        } else {
            // Apply search filters if no specific IDs are selected
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%");
                });
            }

            if ($request->has('category')) {
                $query->where('category', $request->category);
            }

            if ($request->has('brand')) {
                $query->where('brand', $request->brand);
            }
        }

        $products = $query->get();

        $filename = "products_export_" . date('Y-m-d_H-i-s') . ".csv";
        $headers = [
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ];

        $callback = function () use ($products) {
            $file = fopen('php://output', 'w');
            fputcsv($file, [
                'ID',
                'SKU',
                'Name',
                'Category',
                'Brand',
                'Price',
                'Stock',
                'Warranty (Months)',
                'Status',
                'Created At'
            ]);

            foreach ($products as $product) {
                fputcsv($file, [
                    $product->id,
                    $product->sku,
                    $product->name,
                    $product->category,
                    $product->brand,
                    $product->price,
                    $product->stock_quantity,
                    $product->default_warranty_months,
                    $product->is_active ? 'Active' : 'Inactive',
                    $product->created_at->format('Y-m-d')
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Bulk import products
     */
    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'products' => 'required|array',
            'products.*.sku' => 'required|string|max:50',
            'products.*.name' => 'required|string|max:255',
            'products.*.category' => 'required|string|max:100',
            'products.*.brand' => 'required|string|max:100',
            'products.*.description' => 'nullable|string',
            'products.*.price' => 'nullable|numeric|min:0',
            'products.*.stock_quantity' => 'nullable|integer|min:0',
            'products.*.default_warranty_months' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $imported = [];
        $updated = [];
        $failed = [];

        foreach ($request->products as $index => $productData) {
            try {
                // Check if SKU exists to update or create
                $product = Product::where('sku', $productData['sku'])->first();

                if ($product) {
                    $product->update($productData);
                    $updated[] = $product->id;
                } else {
                    $product = Product::create($productData);
                    $imported[] = $product->id;
                }
            } catch (\Exception $e) {
                $failed[] = [
                    'row' => $index + 1,
                    'sku' => $productData['sku'] ?? 'Unknown',
                    'error' => $e->getMessage()
                ];
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Imported " . count($imported) . " products, updated " . count($updated) . " products, " . count($failed) . " failed",
            'imported' => $imported,
            'updated' => $updated,
            'failed' => $failed
        ]);
    }
}
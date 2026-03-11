<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class SaleController extends Controller
{
    /**
     * Display a listing of sales with filters.
     */
    public function index(Request $request)
    {
        $query = Sale::with(['customer', 'product']);

        // Filter by invoice number
        if ($request->has('invoice')) {
            $query->byInvoice($request->invoice);
        }

        // Filter by customer email
        if ($request->has('email')) {
            $query->byEmail($request->email);
        }

        // Filter by customer ID
        if ($request->has('customer_id')) {
            $query->byCustomer($request->customer_id);
        }

        // Filter by product
        if ($request->has('product_id')) {
            $query->forProduct($request->product_id);
        }

        // Filter by date range
        if ($request->has('date_from') && $request->has('date_to')) {
            $query->betweenDates($request->date_from, $request->date_to);
        }

        // Filter by warranty status
        if ($request->has('warranty_status')) {
            if ($request->warranty_status === 'valid') {
                $query->warrantyValid();
            } elseif ($request->warranty_status === 'expired') {
                $query->warrantyExpired();
            }
        }

        // Search by invoice or email
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'LIKE', "%{$search}%")
                    ->orWhere('customer_email', 'LIKE', "%{$search}%")
                    ->orWhere('serial_number', 'LIKE', "%{$search}%");
            });
        }

        // Sort
        $allowedSorts = ['sale_date', 'amount', 'invoice_number', 'created_at'];

        $sortField = $request->get('sort_by', 'sale_date');
        $sortDirection = $request->get('sort_direction', 'desc');

        if (!in_array($sortField, $allowedSorts)) {
            $sortField = 'sale_date';
        }

        if (!in_array($sortDirection, ['asc', 'desc'])) {
            $sortDirection = 'desc';
        }

        $query->orderBy($sortField, $sortDirection);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $sales = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $sales
        ]);
    }

    /**
     * Store a newly created sale.
     */

    public function store(Request $request)
    {
        try {
            \Log::info('Creating sale with data:', $request->all());

            $validator = Validator::make($request->all(), [
                'invoice_number' => 'required|string|unique:sales,invoice_number|max:255',
                'customer_email' => 'required|email|max:255',
                'customer_name' => 'required|string|max:255',
                'customer_id' => 'nullable|exists:users,id',
                'product_id' => 'required|exists:products,id',
                'sale_date' => 'required|date',
                'amount' => 'required|numeric|min:0', // 👈 ADD THIS LINE
                'quantity' => 'required|integer|min:1',
                'serial_number' => 'nullable|string|max:255',
                'warranty_months' => 'nullable|integer|min:0|max:120',
                'payment_method' => 'nullable|string|max:100',
                'notes' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                \Log::warning('Validation failed:', $validator->errors()->toArray());
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // If customer_id provided, verify email matches
            if ($request->customer_id) {
                $user = User::find($request->customer_id);
                if ($user && $user->email !== $request->customer_email) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Customer email does not match the selected user'
                    ], 422);
                }
            }

            \Log::info('Validation passed, creating sale');

            $sale = Sale::create($validator->validated());

            // Load relationships for response
            $sale->load(['customer', 'product']);

            \Log::info('Sale created with ID: ' . $sale->id);

            return response()->json([
                'success' => true,
                'message' => 'Sale created successfully',
                'data' => $sale
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Sale creation failed: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Sale creation failed: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Display the specified sale.
     */
    public function show(Sale $sale)
    {
        $sale->load(['customer', 'product', 'rmaRequests']);

        return response()->json([
            'success' => true,
            'data' => $sale
        ]);
    }

    /**
     * Update the specified sale.
     */
    public function update(Request $request, Sale $sale)
    {
        $validator = Validator::make($request->all(), [
            'invoice_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('sales')->ignore($sale->id)
            ],
            'customer_email' => 'required|email|max:255',
            'customer_id' => 'nullable|exists:users,id',
            'product_id' => 'required|exists:products,id',
            'sale_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:1',
            'serial_number' => 'nullable|string|max:255',
            'warranty_months' => 'nullable|integer|min:0|max:120',
            'payment_method' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // If customer_id provided, verify email matches
        if ($request->customer_id) {
            $user = User::find($request->customer_id);
            if ($user && $user->email !== $request->customer_email) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer email does not match the selected user'
                ], 422);
            }
        }

        $sale->update($validator->validated());
        $sale->load(['customer', 'product']);

        return response()->json([
            'success' => true,
            'message' => 'Sale updated successfully',
            'data' => $sale
        ]);
    }

    /**
     * Remove the specified sale.
     */
    public function destroy(Sale $sale)
    {
        // Check if sale has RMA requests
        if ($sale->rmaRequests()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete sale with existing RMA requests'
            ], 422);
        }

        $sale->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sale deleted successfully'
        ]);
    }

    /**
     * Bulk import sales from CSV/JSON.
     */
    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sales' => 'required|array',
            'sales.*.invoice_number' => 'required|string|unique:sales,invoice_number',
            'sales.*.customer_email' => 'required|email',
            'sales.*.customer_name' => 'required|string|max:255',
            'sales.*.product_id' => 'required|exists:products,id',
            'sales.*.sale_date' => 'required|date',
            'sales.*.amount' => 'required|numeric|min:0',
            'sales.*.quantity' => 'required|integer|min:1',
            'sales.*.serial_number' => 'nullable|string',
            'sales.*.warranty_months' => 'nullable|integer|min:0|max:120',
            'sales.*.payment_method' => 'nullable|string',
            'sales.*.notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $imported = [];
        $failed = [];

        foreach ($request->sales as $index => $saleData) {
            try {
                // Try to find existing user by email
                $user = User::where('email', $saleData['customer_email'])->first();

                // Get product to calculate warranty expiry
                $product = Product::find((int) $saleData['product_id']);

                // Explicitly cast values to correct types
                $product_id = (int) $saleData['product_id'];
                $amount = (float) $saleData['amount'];
                $quantity = (int) $saleData['quantity'];
                $warranty_months = isset($saleData['warranty_months']) ? (int) $saleData['warranty_months'] : ($product->warranty_months ?? 12);

                // Parse date properly
                $saleDate = \Carbon\Carbon::parse($saleData['sale_date']);

                // Calculate warranty expiry date
                $warrantyExpiryDate = $saleDate->copy()->addMonths($warranty_months);

                $sale = Sale::create([
                    'invoice_number' => $saleData['invoice_number'],
                    'customer_email' => $saleData['customer_email'],
                    'customer_name' => $saleData['customer_name'],
                    'customer_id' => $user?->id,
                    'product_id' => $product_id,
                    'sale_date' => $saleDate->format('Y-m-d'),
                    'amount' => $amount,
                    'quantity' => $quantity,
                    'serial_number' => $saleData['serial_number'] ?? null,
                    'warranty_months' => $warranty_months,
                    'warranty_expiry_date' => $warrantyExpiryDate,
                    'payment_method' => $saleData['payment_method'] ?? null,
                    'notes' => $saleData['notes'] ?? null,
                ]);

                $imported[] = $sale->id;
            } catch (\Exception $e) {
                $failed[] = [
                    'row' => $index + 1,
                    'invoice' => $saleData['invoice_number'] ?? 'Unknown',
                    'error' => $e->getMessage()
                ];
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Imported " . count($imported) . " sales, " . count($failed) . " failed",
            'imported' => $imported,
            'failed' => $failed
        ]);
    }

    /**
     * Export sales to CSV.
     */
    public function export(Request $request)
    {
        $query = Sale::with(['customer', 'product']);

        // Apply same filters as index
        if ($request->has('date_from') && $request->has('date_to')) {
            $query->betweenDates($request->date_from, $request->date_to);
        }

        $sales = $query->get();

        $csvData = $sales->map(function ($sale) {
            return [
                'Invoice' => $sale->invoice_number,
                'Customer Email' => $sale->customer_email,
                'Customer Name' => $sale->customer?->full_name ?? 'N/A',
                'Product' => $sale->product?->name ?? 'N/A',
                'Sale Date' => $sale->sale_date->format('Y-m-d'),
                'Amount' => $sale->amount,
                'Serial Number' => $sale->serial_number ?? '',
                'Warranty Months' => $sale->warranty_months,
                'Warranty Expiry' => $sale->warranty_expiry_date?->format('Y-m-d') ?? '',
                'Status' => $sale->warranty_status,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $csvData
        ]);
    }

    /**
     * Get sales for the currently authenticated customer.
     */
    public function mySales(Request $request)
    {
        $user = $request->user();

        // Fetch sales linked by user ID OR by email
        $sales = Sale::with('product')
            ->where(function ($query) use ($user) {
                $query->where('customer_id', $user->id)
                    ->orWhere('customer_email', trim($user->email));
            })
            ->orderBy('sale_date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $sales
        ]);
    }

    /**
     * Link unlinked sales to user by email.
     */

    public function linkToUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'sale_id' => 'required|exists:sales,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();
        $sale = Sale::find($request->sale_id);

        $sale->customer_id = $user->id;
        $sale->save();

        return response()->json([
            'success' => true,
            'message' => 'Sale linked to user successfully',
            'data' => $sale->load(['customer', 'product'])
        ]);
    }
}
<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    /**
     * Get all customers (users with role 'customer')
     */
    public function index(Request $request)
    {
        $query = User::where('role', UserRole::CUSTOMER);

        // Search by name or email
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter by country
        if ($request->has('country')) {
            $query->where('country', $request->country);
        }

        // Sort
        $sortField = $request->get('sort_by', 'first_name');
        $sortDirection = $request->get('sort_direction', 'asc');
        $query->orderBy($sortField, $sortDirection);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $customers = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $customers
        ]);
    }

    /**
     * Create a new customer
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'country' => 'nullable|string|size:2',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:8',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        // Generate a random password if not provided
        if (empty($data['password'])) {
            $data['password'] = 'Welcome' . rand(100, 999);
        }

        $data['password'] = Hash::make($data['password']);
        $data['role'] = UserRole::CUSTOMER;
        $data['is_active'] = $data['is_active'] ?? true;

        $customer = User::create($data);

        // Remove password from response
        unset($customer->password);

        return response()->json([
            'success' => true,
            'message' => 'Customer created successfully',
            'data' => $customer
        ], 201);
    }

    /**
     * Get single customer
     */
    public function show($id)
    {
        $customer = User::where('role', UserRole::CUSTOMER)->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $customer
        ]);
    }

    /**
     * Update a customer
     */
    public function update(Request $request, $id)
    {
        $customer = User::where('role', UserRole::CUSTOMER)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($customer->id)],
            'phone' => 'nullable|string|max:20',
            'country' => 'nullable|string|size:2',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:8',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        // Hash password if provided
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $customer->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Customer updated successfully',
            'data' => $customer
        ]);
    }

    /**
     * Delete a customer
     */
    public function destroy($id)
    {
        $customer = User::where('role', UserRole::CUSTOMER)->findOrFail($id);

        // Check if customer has sales or RMAs
        if ($customer->sales()->exists() || $customer->rmaRequests()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete customer with existing sales or RMA requests'
            ], 422);
        }

        $customer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Customer deleted successfully'
        ]);
    }

    /**
     * Bulk delete customers
     */
    public function bulkDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if any have sales/RMAs
        $customers = User::whereIn('id', $request->ids)
            ->where('role', UserRole::CUSTOMER)
            ->get();

        $cannotDelete = [];
        foreach ($customers as $customer) {
            if ($customer->sales()->exists() || $customer->rmaRequests()->exists()) {
                $cannotDelete[] = $customer->id;
            }
        }

        if (!empty($cannotDelete)) {
            return response()->json([
                'success' => false,
                'message' => 'Some customers have sales or RMA requests and cannot be deleted',
                'cannot_delete' => $cannotDelete
            ], 422);
        }

        $deleted = User::whereIn('id', $request->ids)
            ->where('role', UserRole::CUSTOMER)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => "{$deleted} customers deleted successfully"
        ]);
    }

    /**
     * Bulk update customer status
     */
    public function bulkUpdateStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'exists:users,id',
            'is_active' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $updated = User::whereIn('id', $request->ids)
            ->where('role', UserRole::CUSTOMER)
            ->update(['is_active' => $request->is_active]);

        return response()->json([
            'success' => true,
            'message' => "{$updated} customers updated successfully"
        ]);
    }

    /**
     * Export customers to CSV
     */
    public function export(Request $request)
    {
        $query = User::where('role', UserRole::CUSTOMER);

        // Filter by selected IDs if provided
        if ($request->has('ids') && is_array($request->ids)) {
            $query->whereIn('id', $request->ids);
        } else {
            // Apply search filters if no specific IDs are selected
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            if ($request->has('from_date')) {
                $query->whereDate('created_at', '>=', $request->from_date);
            }

            if ($request->has('to_date')) {
                $query->whereDate('created_at', '<=', $request->to_date);
            }
        }

        $customers = $query->get();

        $filename = "customers_export_" . date('Y-m-d_H-i-s') . ".csv";
        $headers = [
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ];

        $callback = function () use ($customers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, [
                'ID',
                'First Name',
                'Last Name',
                'Email',
                'Phone',
                'Country',
                'City',
                'Address',
                'Postal Code',
                'Status',
                'Joined'
            ]);

            foreach ($customers as $customer) {
                fputcsv($file, [
                    $customer->id,
                    $customer->first_name,
                    $customer->last_name,
                    $customer->email,
                    $customer->phone,
                    $customer->country,
                    $customer->city,
                    $customer->address,
                    $customer->postal_code,
                    $customer->is_active ? 'Active' : 'Inactive',
                    $customer->created_at->format('Y-m-d')
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
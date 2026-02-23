<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Http\Request;

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

        // Pagination
        $perPage = $request->get('per_page', 100);
        $customers = $query->orderBy('first_name')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $customers
        ]);
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
}
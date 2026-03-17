<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\RMARequest;
use App\Models\Product;
use App\Models\Sale;
use App\Enums\UserRole;
use App\Enums\RMAStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SuperAdminController extends Controller
{
    // =========================================================================
    // DASHBOARD OVERVIEW
    // =========================================================================

    public function overview(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'users'    => $this->getUserStats(),
                'staff'    => $this->getStaffStats(),
                'rmas'     => $this->getRmaStats(),
                'products' => $this->getProductStats(),
                'sales'    => $this->getSalesStats(),
            ],
        ]);
    }

    private function getUserStats(): array
    {
        return [
            'total'       => User::customers()->count(),
            'active'      => User::customers()->active()->count(),
            'new_this_month' => User::customers()
                ->where('created_at', '>=', now()->startOfMonth())
                ->count(),
        ];
    }

    private function getStaffStats(): array
    {
        $staffRoles = array_map(fn($r) => $r->value, UserRole::staffRoles());

        $byRole = User::whereIn('role', $staffRoles)
            ->selectRaw('role, count(*) as count')
            ->groupBy('role')
            ->pluck('count', 'role');

        return [
            'total'  => User::staff()->count(),
            'active' => User::staff()->active()->count(),
            'by_role' => [
                'csr'         => $byRole->get(UserRole::CSR->value, 0),
                'admin'       => $byRole->get(UserRole::ADMIN->value, 0),
                'super_admin' => $byRole->get(UserRole::SUPER_ADMIN->value, 0),
            ],
        ];
    }

    private function getRmaStats(): array
    {
        return [
            'total'   => RMARequest::count(),
            'pending' => RMARequest::where('status', RMAStatus::PENDING->value)->count(),
            'in_progress' => RMARequest::whereIn('status', [
                RMAStatus::UNDER_REVIEW->value,
                RMAStatus::APPROVED->value,
                RMAStatus::IN_REPAIR->value,
            ])->count(),
            'completed' => RMARequest::where('status', RMAStatus::COMPLETED->value)->count(),
        ];
    }

    private function getProductStats(): array
    {
        return [
            'total'  => Product::count(),
            'active' => Product::where('is_active', true)->count(),
        ];
    }

    private function getSalesStats(): array
    {
        return [
            'total'         => Sale::count(),
            'this_month'    => Sale::where('created_at', '>=', now()->startOfMonth())->count(),
        ];
    }

    // =========================================================================
    // STAFF MANAGEMENT
    // =========================================================================

    /**
     * List all staff members (csr, admin, super_admin)
     */
    public function getStaff(Request $request): \Illuminate\Http\JsonResponse
    {
        $staffRoles = array_map(fn($r) => $r->value, UserRole::staffRoles());

        $query = User::whereIn('role', $staffRoles)->latest();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        $staff = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'success' => true,
            'data'    => $staff,
        ]);
    }

    /**
     * Create a new staff member
     */
    public function createStaff(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'email'      => 'required|email|unique:users,email',
            'role'       => ['required', Rule::in([
                UserRole::CSR->value,
                UserRole::ADMIN->value,
                UserRole::SUPER_ADMIN->value,
            ])],
            'password'   => ['required', Password::min(8)],
        ]);

        $staff = User::create([
            'first_name' => $validated['first_name'],
            'last_name'  => $validated['last_name'],
            'email'      => $validated['email'],
            'role'       => $validated['role'],
            'password'   => Hash::make($validated['password']),
            'is_active'  => true,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Staff member created successfully.',
            'data'    => $staff,
        ], 201);
    }

    /**
     * Update an existing staff member
     */
    public function updateStaff(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $staff = User::findOrFail($id);

        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:100',
            'last_name'  => 'sometimes|string|max:100',
            'email'      => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($id)],
            'role'       => ['sometimes', Rule::in([
                UserRole::CSR->value,
                UserRole::ADMIN->value,
                UserRole::SUPER_ADMIN->value,
            ])],
            'is_active'  => 'sometimes|boolean',
            'password'   => ['sometimes', 'nullable', Password::min(8)],
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $staff->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Staff member updated successfully.',
            'data'    => $staff->fresh(),
        ]);
    }

    /**
     * Delete a staff member (cannot delete yourself)
     */
    public function deleteStaff(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        if ($request->user()->id === $id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own account.',
            ], 403);
        }

        $staff = User::findOrFail($id);
        $staff->delete();

        return response()->json([
            'success' => true,
            'message' => 'Staff member deleted successfully.',
        ]);
    }
}

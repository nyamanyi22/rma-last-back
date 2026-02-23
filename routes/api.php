<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\Admin\ProductController;
use App\Http\Controllers\Api\Admin\SaleController; // 👈 ADD THIS IMPORT!
use App\Http\Controllers\Api\Admin\CustomerController;
use Illuminate\Support\Facades\Route;


// Public routes (no authentication required)
Route::post('/register', [AuthController::class , 'register']);
Route::post('/login', [AuthController::class , 'login']);
Route::post('/staff/login', [AuthController::class , 'staffLogin']);

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {

    // Auth routes
    Route::post('/logout', [AuthController::class , 'logout']);
    Route::get('/me', [AuthController::class , 'me']);

    // Profile routes (Global)
    Route::put('/profile', [ProfileController::class , 'update']);

    // 👇 CUSTOMER SALES - Any authenticated user can see their own purchases
    Route::get('/my-sales', [SaleController::class , 'mySales']);

    // Customer routes (role: customer)
    Route::middleware('role:customer')->prefix('customer')->group(function () {
            Route::get('/dashboard', function () {
                    return response()->json(['message' => 'Customer dashboard']);
                }
                );

                // Customer's own profile management
                Route::prefix('profile')->group(function () {
                    Route::get('/', [ProfileController::class , 'show']);
                    Route::put('/', [ProfileController::class , 'update']);
                    Route::delete('/', [ProfileController::class , 'destroy']);
                }
                );
            }
            );

            // Staff routes (role: csr, admin, super_admin)
            Route::middleware('role:csr,admin,super_admin')->prefix('admin')->group(function () {
            Route::get('/dashboard', function () {
                    return response()->json(['message' => 'Admin dashboard']);
                }
                );

                // Product Management
                Route::prefix('products')->group(function () {
                    // Lookup routes (MUST come before {product})
                    Route::get('/categories', [ProductController::class , 'getCategories']);
                    Route::get('/brands', [ProductController::class , 'getBrands']);

                    // Bulk actions
                    Route::post('/bulk-delete', [ProductController::class , 'bulkDelete']);
                    Route::post('/bulk-status', [ProductController::class , 'bulkUpdateStatus']);

                    // Standard CRUD
                    Route::get('/', [ProductController::class , 'index']);
                    Route::post('/', [ProductController::class , 'store']);
                    Route::get('/{product}', [ProductController::class , 'show']);
                    Route::put('/{product}', [ProductController::class , 'update']);
                    Route::delete('/{product}', [ProductController::class , 'destroy']);
                }
                );

                // 👇 SALES MANAGEMENT ROUTES (Admin only)
                Route::prefix('sales')->group(function () {
                    // List all sales (with filters)
                    Route::get('/', [SaleController::class , 'index']);

                    // Create new sale
                    Route::post('/', [SaleController::class , 'store']);

                    // Bulk import sales
                    Route::post('/import', [SaleController::class , 'import']);

                    // Export sales to CSV
                    Route::get('/export', [SaleController::class , 'export']);

                    // Link unlinked sales to user by email
                    Route::post('/link-to-user', [SaleController::class , 'linkToUser']);

                    // Single sale operations
                    Route::get('/{sale}', [SaleController::class , 'show']);
                    Route::put('/{sale}', [SaleController::class , 'update']);
                    Route::delete('/{sale}', [SaleController::class , 'destroy']);
                }
                );

                // 👇 CUSTOMER MANAGEMENT ROUTES (Admin only)
                Route::prefix('customers')->group(function () {
                    Route::get('/', [CustomerController::class , 'index']); // /admin/customers
                    Route::get('/{id}', [CustomerController::class , 'show']); // /admin/customers/5
                }
                );
            }
            );

            // Super Admin only routes
            Route::middleware('role:super_admin')->prefix('super-admin')->group(function () {
            Route::get('/dashboard', function () {
                    return response()->json(['message' => 'Super Admin dashboard']);
                }
                );
            }
            );
        });
<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\Admin\ProductController;
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
                    // Lookup routes (MUST come before {product} to avoid matching as ID)
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

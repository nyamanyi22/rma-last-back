<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\Admin\ProductController;
use App\Http\Controllers\Api\Admin\SaleController;
use App\Http\Controllers\Api\Admin\CustomerController;
use App\Http\Controllers\Api\Client\RMAController;
use App\Http\Controllers\Api\Admin\AdminRMAController;
use App\Http\Controllers\Api\Admin\ReportController;
use Illuminate\Support\Facades\Route;


// Public routes (no authentication required)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/staff/login', [AuthController::class, 'staffLogin']);

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {

    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // PRODUCT ROUTES - Accessible to all authenticated users
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{product}', [ProductController::class, 'show']);

    // Profile routes (Global)
    Route::put('/profile', [ProfileController::class, 'update']);


    // Customer routes (role: customer)
    Route::middleware('role:customer')->prefix('customer')->group(function () {
        Route::get('/dashboard', function () {
            return response()->json(['message' => 'Customer dashboard']);
        });

        // Customer's own profile management
        Route::prefix('profile')->group(function () {
            Route::get('/', [ProfileController::class, 'show']);
            Route::put('/', [ProfileController::class, 'update']);
            Route::delete('/', [ProfileController::class, 'destroy']);
        });

        // CUSTOMER SALES 
        Route::get('/my-sales', [SaleController::class, 'mySales']);

        // CUSTOMER RMA ROUTES
        Route::get('/my-rmas', [RMAController::class, 'myRmas']);
        Route::post('/rma/submit', [RMAController::class, 'store']);
        Route::get('/rma/attachment', [RMAController::class, 'downloadAttachment']);
        Route::get('/rma/{id}', [RMAController::class, 'show']);
        Route::post('/rma/{id}/cancel', [RMAController::class, 'cancel']);

        Route::prefix('rma/attachments')->group(function () {
            Route::get('/{id}', [RMAController::class, 'getAttachment']);      // View attachment details
            Route::get('/{id}/download', [RMAController::class, 'downloadAttachment']); // Download
        });
    });

    // Staff routes (role: csr, admin, super_admin)
    Route::middleware('role:csr,admin,super_admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', function () {
            return response()->json(['message' => 'Admin dashboard']);
        });

        // Product Management
        Route::prefix('products')->group(function () {
            // Lookup routes (MUST come before {product})
            Route::get('/categories', [ProductController::class, 'getCategories']);
            Route::get('/brands', [ProductController::class, 'getBrands']);

            // Bulk actions
            Route::post('/bulk-delete', [ProductController::class, 'bulkDelete']);
            Route::post('/bulk-status', [ProductController::class, 'bulkUpdateStatus']);

            // Standard CRUD
            Route::get('/', [ProductController::class, 'index']);
            Route::post('/', [ProductController::class, 'store']);
            Route::get('/{product}', [ProductController::class, 'show']);
            Route::put('/{product}', [ProductController::class, 'update']);
            Route::delete('/{product}', [ProductController::class, 'destroy']);
        });

        // SALES MANAGEMENT ROUTES (Admin only)
        Route::prefix('sales')->group(function () {
            // List all sales (with filters)
            Route::get('/', [SaleController::class, 'index']);

            // Create new sale
            Route::post('/', [SaleController::class, 'store']);

            // Bulk import sales
            Route::post('/import', [SaleController::class, 'import']);

            // Export sales to CSV
            Route::get('/export', [SaleController::class, 'export']);

            // Link unlinked sales to user by email
            Route::post('/link-to-user', [SaleController::class, 'linkToUser']);

            // Single sale operations
            Route::get('/{sale}', [SaleController::class, 'show']);
            Route::put('/{sale}', [SaleController::class, 'update']);
            Route::delete('/{sale}', [SaleController::class, 'destroy']);
        });

        // CUSTOMER MANAGEMENT ROUTES (Admin only)
        Route::prefix('customers')->group(function () {
            Route::get('/', [CustomerController::class, 'index']);
            Route::post('/', [CustomerController::class, 'store']);
            Route::get('/export', [CustomerController::class, 'export']);
            Route::post('/bulk-delete', [CustomerController::class, 'bulkDelete']);
            Route::post('/bulk-status', [CustomerController::class, 'bulkUpdateStatus']);
            Route::get('/{id}', [CustomerController::class, 'show']);
            Route::put('/{id}', [CustomerController::class, 'update']);
            Route::delete('/{id}', [CustomerController::class, 'destroy']);
        });

        // ADMIN RMA ROUTES
        Route::prefix('rma')->group(function () {
            // Dashboard stats
            Route::get('/stats', [AdminRMAController::class, 'stats']);

            // Attachments (Move this BEFORE wildcard routes)
            Route::get('/attachment', [AdminRMAController::class, 'downloadAttachment']);

            // List all RMAs with filters
            Route::get('/', [AdminRMAController::class, 'index']);

            // Bulk operations
            Route::post('/bulk-delete', [AdminRMAController::class, 'bulkDelete']);
            Route::post('/bulk-status', [AdminRMAController::class, 'bulkUpdateStatus']);
            // ATTACHMENT ROUTES - Place BEFORE wildcard routes!
            Route::prefix('attachments')->group(function () {
                Route::get('/{id}', [AdminRMAController::class, 'getAttachment']);        // Get attachment details
                Route::delete('/{id}', [AdminRMAController::class, 'deleteAttachment']); // Delete attachment
                Route::get('/{id}/download', [AdminRMAController::class, 'downloadAttachment']); // Download
                Route::get('/stats/{rmaId}', [AdminRMAController::class, 'getCompressionStats']); // Compression stats
            });

            // Single RMA operations
            Route::get('/{id}', [AdminRMAController::class, 'show']);
            Route::put('/{id}', [AdminRMAController::class, 'update']);
            Route::delete('/{id}', [AdminRMAController::class, 'destroy']);

            // Assignment
            Route::post('/{id}/assign', [AdminRMAController::class, 'assign']);

            // Comments
            Route::get('/{id}/comments', [AdminRMAController::class, 'getComments']);
            Route::post('/{id}/comments', [AdminRMAController::class, 'addComment']);
            // Comments
            Route::get('/{id}/comments', [AdminRMAController::class, 'getComments']);
            Route::post('/{id}/comments', [AdminRMAController::class, 'addComment']);

            // Shipping
            Route::put('/{id}/shipping', [AdminRMAController::class, 'updateShipping']);
        });

        // ADMIN REPORTS ROUTES
        Route::prefix('reports')->group(function () {
            Route::get('/overview', [ReportController::class, 'getDashboardOverview']);
            Route::get('/export', [ReportController::class, 'exportRmasToCsv']);
        });
    });

    // Super Admin only routes
    Route::middleware('role:super_admin')->prefix('super-admin')->group(function () {
        Route::get('/dashboard', function () {
            return response()->json(['message' => 'Super Admin dashboard']);
        });
    });
});
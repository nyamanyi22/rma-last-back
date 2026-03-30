<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\Admin\ProductController;
use App\Http\Controllers\Api\Admin\SaleController;
use App\Http\Controllers\Api\Admin\CustomerController;
use App\Http\Controllers\Api\Client\RMAController;
use App\Http\Controllers\Api\Admin\AdminRMAController;
use App\Http\Controllers\Api\Admin\ReportController;
use App\Http\Controllers\Api\Admin\SuperAdminController;
use App\Http\Controllers\Api\Admin\NotificationController;
use App\Http\Controllers\Api\Admin\SettingsController;
use Illuminate\Support\Facades\Route;


// Public routes (no authentication required)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/staff/login', [AuthController::class, 'staffLogin']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/verify-email', [AuthController::class, 'verify']);
Route::post('/resend-verification', [AuthController::class, 'resendVerificationEmail']);

// Token-based downloads (outside sanctum middleware as window.open doesn't send headers)
Route::get('/customer/rma/attachments/{id}/download', [RMAController::class, 'downloadAttachment']);
Route::get('/admin/rma/attachments/{id}/download', [AdminRMAController::class, 'downloadAttachment']);

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {

    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // PRODUCT ROUTES - Accessible to all authenticated users
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{product}', [ProductController::class, 'show']);

    // Profile routes (Global)
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);


    // Customer routes (role: customer)
    Route::middleware('role:customer')->prefix('customer')->group(function () {
        Route::get('/dashboard', function () {
            return response()->json(['message' => 'Customer dashboard']);
        });

        // Customer's own profile management
        // (Now handled by global /profile routes)

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

        // Return Policy
        Route::get('/return-policy', [SettingsController::class, 'getReturnPolicy']);
    });

    // Staff routes (role: csr, admin, super_admin)
    Route::middleware('role:csr,admin,super_admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', function () {
            return response()->json(['message' => 'Admin dashboard']);
        });

        Route::middleware('role:admin,super_admin')->group(function () {
            // Product Management
            Route::prefix('products')->group(function () {
                // Lookup routes (MUST come before {product})
                Route::get('/categories', [ProductController::class, 'getCategories']);
                Route::get('/brands', [ProductController::class, 'getBrands']);

                // Bulk actions
                Route::post('/bulk-delete', [ProductController::class, 'bulkDelete']);
                Route::post('/bulk-status', [ProductController::class, 'bulkUpdateStatus']);
                Route::post('/import', [ProductController::class, 'import']);
                Route::get('/export', [ProductController::class, 'export']);

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
                Route::post('/import', [CustomerController::class, 'import']);
                Route::get('/export', [CustomerController::class, 'export']);
                Route::post('/bulk-delete', [CustomerController::class, 'bulkDelete']);
                Route::post('/bulk-status', [CustomerController::class, 'bulkUpdateStatus']);
                Route::get('/{id}', [CustomerController::class, 'show']);
                Route::put('/{id}', [CustomerController::class, 'update']);
                Route::delete('/{id}', [CustomerController::class, 'destroy']);
            });
        });

        // ADMIN RMA ROUTES
        Route::prefix('rma')->group(function () {
            // Dashboard stats
            Route::get('/stats', [AdminRMAController::class, 'stats']);

            // Attachments (Move this BEFORE wildcard routes)
            Route::get('/attachment', [AdminRMAController::class, 'downloadAttachment']);

            // List all RMAs with filters
            Route::get('/', [AdminRMAController::class, 'index']);

            // Create new RMA (Admin)
            Route::post('/', [AdminRMAController::class, 'store']);
            Route::post('/create', [AdminRMAController::class, 'adminCreate']);

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

        // NOTIFICATIONS
        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationController::class, 'index']);
            Route::get('/all', [NotificationController::class, 'all']);
            Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
            Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
            Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
            Route::delete('/{id}', [NotificationController::class, 'destroy']);
            Route::delete('/clear-all', [NotificationController::class, 'clearAll']);
        });

        // ADMIN REPORTS ROUTES
        Route::middleware('role:admin,super_admin')->group(function () {
            Route::prefix('reports')->group(function () {
                Route::get('/overview', [ReportController::class, 'getDashboardOverview']);
                Route::get('/export', [ReportController::class, 'exportRmasToCsv']);
            });
        });

    });

    // Super Admin only routes
    Route::middleware('role:super_admin')->prefix('super-admin')->group(function () {
        // Dashboard overview stats
        Route::get('/overview', [SuperAdminController::class, 'overview']);

        // Staff management (CRUD)
        Route::prefix('staff')->group(function () {
            Route::get('/', [SuperAdminController::class, 'getStaff']);
            Route::post('/', [SuperAdminController::class, 'createStaff']);
            Route::put('/{id}', [SuperAdminController::class, 'updateStaff']);
            Route::delete('/{id}', [SuperAdminController::class, 'deleteStaff']);
        });

        // System Settings
        Route::prefix('settings')->group(function () {
            Route::get('/', [SettingsController::class, 'index']);
            Route::post('/', [SettingsController::class, 'update']);
            Route::get('/return-policy', [SettingsController::class, 'getReturnPolicy']);
            Route::post('/return-policy', [SettingsController::class, 'updateReturnPolicy']);
        });

        Route::get('/system-info', [SettingsController::class, 'getSystemInfo']);
    });
});
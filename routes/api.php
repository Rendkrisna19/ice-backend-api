<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\V1\AuthController;
use App\Http\Controllers\API\V1\OutletController;
use App\Http\Controllers\API\V1\ProductController;
use App\Http\Controllers\API\V1\Customer\OrderController as CustomerOrderController;
use App\Http\Controllers\API\V1\Merchant\OrderController as MerchantOrderController;
use App\Http\Controllers\API\V1\Driver\ShiftController;
use App\Http\Controllers\API\V1\Admin\ManagementController;
use App\Http\Controllers\API\V1\Admin\AdminReportController;
use App\Http\Controllers\API\V1\Merchant\ProductController as MerchantProductController;
use App\Http\Controllers\API\V1\ProfileController;
use App\Http\Controllers\API\V1\Merchant\ReportController;
use App\Http\Controllers\API\V1\Merchant\PosController;
use App\Http\Controllers\API\V1\Merchant\SettingController;

// ==========================================
// PUBLIC ROUTES
// ==========================================
Route::prefix('v1')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/register/request-otp', [AuthController::class, 'requestRegisterOtp']);
    Route::post('/auth/register/verify-otp', [AuthController::class, 'verifyRegisterOtp']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::get('/outlets', [OutletController::class, 'index']);
    Route::get('/outlets/search', [OutletController::class, 'search']);
    Route::get('/outlets/{outlet}', [OutletController::class, 'show']);
    Route::get('/outlets/{outlet}/products', [OutletController::class, 'getProducts']);

    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/search', [ProductController::class, 'search']);
    Route::get('/products/category', [ProductController::class, 'byCategory']);
    Route::get('/products/{product}', [ProductController::class, 'show']);

    Route::post('/outlets/{outlet}/media', [OutletController::class, 'uploadMedia']);

    Route::get('/config/pricing', function () {
        return response()->json(['data' => \App\Models\SystemConfig::getCurrent()]);
    });
});

// ==========================================
// PROTECTED ROUTES (Auth Required)
// ==========================================
use App\Http\Controllers\ChatController;

Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // CHAT CUSTOMER-DRIVER (umum, bisa diakses customer & driver yang terkait transaksi)
    Route::get('/chat/{transaction_id}', [ChatController::class, 'index']); // ambil pesan
    Route::post('/chat', [ChatController::class, 'store']); // kirim pesan

    // Auth & Profile
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'getUser']);
    Route::delete('/auth/delete-account', [AuthController::class, 'deleteAccount']);
    Route::post('/auth/profile/update', [ProfileController::class, 'update']);
    Route::post('/auth/profile/photo', [ProfileController::class, 'updatePhoto']);

    // --- CUSTOMER ROUTES ---
    Route::prefix('customer')->group(function () {
        Route::get('/orders', [CustomerOrderController::class, 'index']);
        Route::post('/orders/validate-checkout', [CustomerOrderController::class, 'validateCheckout']);
        Route::post('/orders', [CustomerOrderController::class, 'store']);
        Route::get('/orders/{order}', [CustomerOrderController::class, 'show']);
        Route::post('/orders/{order}/cancel', [CustomerOrderController::class, 'cancel']);
        Route::get('/outlets/{outlet}/products', [CustomerOrderController::class, 'getOutletProducts']);
    });

    // --- MERCHANT ROUTES ---
    Route::prefix('merchant')->group(function () {

        // POS
        Route::get('/pos/products', [PosController::class, 'getProducts']);
        Route::post('/pos/orders', [PosController::class, 'storeOrder']);
        Route::post('/pos/orders/{id}/complete', [PosController::class, 'completePayment']);
        Route::get('/pos/configs', [PosController::class, 'getConfigs']);
        Route::get('/pos/active-orders', [PosController::class, 'getActiveOrders']);

        // Order Management
        Route::get('/orders', [MerchantOrderController::class, 'index']);
        Route::get('/orders/kanban', [MerchantOrderController::class, 'getKanbanOrders']);
        Route::get('/orders/counts', [MerchantOrderController::class, 'getOrderCounts']);
        Route::get('/orders/{id}', [MerchantOrderController::class, 'show']);
        Route::post('/orders/{id}/status', [MerchantOrderController::class, 'updateStatus']); // Fitur Baru
        Route::post('/orders/{id}/assign', [MerchantOrderController::class, 'assignDriver']); // Fitur Baru
        Route::post('/orders/{id}/reject', [MerchantOrderController::class, 'rejectOrder']);

        // Reporting
        Route::get('/reports/daily', [ReportController::class, 'getDailyReport']);
        Route::get('/reports/daily/excel', [ReportController::class, 'exportExcel']);
        Route::get('/reports/daily/pdf', [ReportController::class, 'exportPdf']);

        // Product Management (Merchant)
        Route::get('/products', [MerchantProductController::class, 'index']);
        Route::post('/products', [MerchantProductController::class, 'store']);
        Route::post('/products/{id}', [MerchantProductController::class, 'update']);
        Route::delete('/products/{id}', [MerchantProductController::class, 'destroy']);
        Route::post('/products/{id}/toggle', [MerchantProductController::class, 'toggleStatus']);
        Route::post('/menu/toggle-availability', [MerchantOrderController::class, 'toggleMenuAvailability']);

        // Outlet Management
        Route::get('/drivers/available', [MerchantOrderController::class, 'getAvailableDrivers']);
        Route::get('/outlet/status', [MerchantOrderController::class, 'getOutletStatus']);
        Route::post('/outlet/toggle', [MerchantOrderController::class, 'toggleOutletStatus']);
        Route::get('/rejected-orders', [ManagementController::class, 'getRejectedOrders']);

        //settings route merchant
        Route::get('/settings/operational-hours', [SettingController::class, 'getOperationalHours']);
        Route::put('/settings/operational-hours', [SettingController::class, 'updateOperationalHours']);
    });

    // --- DRIVER ROUTES ---
    Route::prefix('driver')->middleware('ability:driver')->group(function () {
        Route::post('/clock-in', [ShiftController::class, 'clockIn']);
        Route::post('/clock-out', [ShiftController::class, 'clockOut']);
        Route::get('/status', [ShiftController::class, 'getStatus']);
        Route::get('/orders', [ShiftController::class, 'getAssignedOrders']);
        Route::get('/orders/active', [ShiftController::class, 'getActiveJobs']);
        Route::post('/orders/{order}/start', [ShiftController::class, 'startDelivery']);
        Route::post('/orders/{order}/complete', [ShiftController::class, 'completeDelivery']);
        Route::post('/change-password', [ShiftController::class, 'changePassword']);
    });

    // --- ADMIN ROUTES ---
    Route::prefix('admin')->group(function () {
        Route::get('/dashboard', [ManagementController::class, 'getDashboardOverview']);

        // Refunds
        Route::get('/refunds', [ManagementController::class, 'getRefundOrders']);
        Route::post('/refunds/{id}/process', [ManagementController::class, 'processRefund']);

        // Outlets
        Route::get('/outlets', [ManagementController::class, 'listOutlets']);
        Route::post('/outlets', [ManagementController::class, 'createOutlet']);
        Route::put('/outlets/{outlet}', [ManagementController::class, 'updateOutlet']);
        Route::delete('/outlets/{outlet}', [ManagementController::class, 'deleteOutlet']);

        // Pricing
        Route::get('/pricing', [ManagementController::class, 'getPricingConfig']);
        Route::put('/pricing', [ManagementController::class, 'updatePricingConfig']);
        Route::post('/pricing/simulate', [ManagementController::class, 'simulatePricing']);

        // Reporting & Analytics
        Route::get('/analytics', [AdminReportController::class, 'getAnalytics']);
        Route::get('/analytics/export/excel', [AdminReportController::class, 'exportExcel']);
        Route::get('/analytics/export/pdf', [AdminReportController::class, 'exportPdf']);

        // Products (Menu)
        Route::get('/outlets/{outlet}/products', [ManagementController::class, 'getOutletProducts']);
        Route::post('/outlets/{outlet}/products', [ManagementController::class, 'createProduct']);
        Route::post('/products', [ManagementController::class, 'createGlobalProduct']);
        Route::post('/products/{product}/update', [ManagementController::class, 'updateProduct']);
        Route::delete('/products/{product}', [ManagementController::class, 'deleteProduct']);

        // Drivers
        Route::get('/drivers', [ManagementController::class, 'getAllDrivers']);
        Route::get('/outlets/{outlet}/drivers', [ManagementController::class, 'getOutletDrivers']);
        Route::post('/outlets/{outlet}/drivers', [ManagementController::class, 'createDriver']);
        Route::put('/drivers/{driver}', [ManagementController::class, 'updateDriver']);
        Route::delete('/drivers/{driver}', [ManagementController::class, 'deleteDriver']);

        // Customers
        Route::get('/customers', [ManagementController::class, 'getCustomers']);
        Route::post('/customers/{user}/toggle-block', [ManagementController::class, 'toggleBlockCustomer']);
        Route::delete('/customers/{user}', [ManagementController::class, 'deleteCustomer']);
        Route::get('/rejected-orders', [ManagementController::class, 'getRejectedOrders']);
    });
});

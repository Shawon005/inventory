<?php

use App\Http\Controllers\Api\Customer\AuthController;
use App\Http\Controllers\Api\Customer\AttendanceController;
use App\Http\Controllers\Api\Customer\DashboardController;
use App\Http\Controllers\Api\Customer\LocationController;
use App\Http\Controllers\Api\Customer\LeaveRequestController;
use App\Http\Controllers\Api\Customer\ProfileController;
use App\Http\Controllers\Api\Customer\ReportController;
use App\Http\Controllers\Api\Customer\SaleController;
use App\Http\Controllers\Api\Customer\ShopVisitController;
use App\Http\Controllers\Api\Customer\StockController;
use App\Http\Controllers\Api\ShopOwnerController;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('shop-owners', [ShopOwnerController::class, 'index']);
Route::post('shop-owners', [ShopOwnerController::class, 'store']);

/*
|--------------------------------------------------------------------------
| Customer Mobile API Routes (Sanctum Token Auth)
|--------------------------------------------------------------------------
*/
Route::prefix('customer')->group(function () {
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'customer.sr', 'throttle:customer_api'])->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index']);
        Route::get('stock', [StockController::class, 'index']);
        Route::post('sale', [SaleController::class, 'sell']);
        Route::put('sale/{id}', [SaleController::class, 'update']);
        Route::put('sale/{id}/status', [SaleController::class, 'changeStatus']);
        Route::post('sale/{id}/return', [SaleController::class, 'returnSale']);
        Route::delete('sale/{id}', [SaleController::class, 'destroy']);
        Route::get('sales-history', [SaleController::class, 'history']);
        Route::post('location/update', [LocationController::class, 'update']);
        Route::post('shop-visits', [ShopVisitController::class, 'store']);
        Route::post('attendance', [AttendanceController::class, 'store']);
        Route::get('attendance/details', [AttendanceController::class, 'details']);
        Route::get('leave-requests', [LeaveRequestController::class, 'index']);
        Route::post('leave-requests', [LeaveRequestController::class, 'store']);
        Route::get('profile', [ProfileController::class, 'show']);
        Route::get('app-report', [ReportController::class, 'appReport']);
        Route::get('daily-sales', [ReportController::class, 'dailySales']);
        Route::get('weekly-sales', [ReportController::class, 'weeklySales']);
        Route::get('monthly-sales', [ReportController::class, 'monthlySales']);
    });
});

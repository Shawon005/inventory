<?php

use App\Http\Controllers\Api\Customer\AuthController;
use App\Http\Controllers\Api\Customer\DashboardController;
use App\Http\Controllers\Api\Customer\LocationController;
use App\Http\Controllers\Api\Customer\ProfileController;
use App\Http\Controllers\Api\Customer\SaleController;
use App\Http\Controllers\Api\Customer\StockController;
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

/*
|--------------------------------------------------------------------------
| Customer Mobile API Routes (Sanctum Token Auth)
|--------------------------------------------------------------------------
*/
Route::prefix('customer')->group(function () {
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'throttle:customer_api'])->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index']);
        Route::get('stock', [StockController::class, 'index']);
        Route::post('sell', [SaleController::class, 'sell']);
        Route::get('sales-history', [SaleController::class, 'history']);
        Route::post('location/update', [LocationController::class, 'update']);
        Route::get('profile', [ProfileController::class, 'show']);
    });
});

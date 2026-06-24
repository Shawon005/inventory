<?php

namespace App\Http\Controllers\Api\Customer;

use App\Customer;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\CustomerDashboardResource;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    /**
     * Get customer dashboard data.
     */
    public function index(): JsonResponse
    {
        $customer = auth()->user();
        $stockCustomerId = (int) ($customer->resolved_stock_owner_id ?? $customer->id);
        $stockCustomer = Customer::with('stocks.product')->find($stockCustomerId);

        if ($stockCustomer) {
            $customer->setRelation('stocks', $stockCustomer->stocks);
        } else {
            $customer->load('stocks.product');
        }
        $customer->loadMissing('sales');

        return response()->json([
            'success' => true,
            'data' => new CustomerDashboardResource($customer),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api\Customer;

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
        $customer = auth()->user()->load('stocks');

        return response()->json([
            'success' => true,
            'data' => new CustomerDashboardResource($customer),
        ]);
    }
}

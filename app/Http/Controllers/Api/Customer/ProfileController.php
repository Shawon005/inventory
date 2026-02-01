<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\CustomerResource;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    /**
     * Get customer profile.
     */
    public function show(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new CustomerResource(auth()->user()),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api\Customer;

use App\Customer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CustomerLoginRequest;
use App\Http\Resources\Api\CustomerResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Customer login - returns Sanctum token.
     */
    public function login(CustomerLoginRequest $request): JsonResponse
    {
        $customer = Customer::where('email', $request->email)->first();

        if (! $customer || ! Hash::check($request->password, $customer->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($customer->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => ['Your account is inactive. Please contact admin.'],
            ]);
        }

        $customer->tokens()->delete();

        $token = $customer->createToken('mobile-app')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'customer' => new CustomerResource($customer),
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    /**
     * Logout - revoke current token.
     */
    public function logout(): JsonResponse
    {
        request()->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }
}

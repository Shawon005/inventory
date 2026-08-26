<?php

namespace App\Http\Middleware;

use App\Customer;
use Closure;

class EnsureSalesRepresentativeApiUser
{
    public function handle($request, Closure $next)
    {
        $customer = $request->user();

        if (! $customer || $customer->employee_type !== Customer::TYPE_SR) {
            return response()->json([
                'success' => false,
                'message' => 'This employee does not have a mobile user account.',
            ], 403);
        }

        return $next($request);
    }
}

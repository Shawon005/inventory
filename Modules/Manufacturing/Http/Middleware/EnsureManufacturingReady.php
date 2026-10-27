<?php

namespace Modules\Manufacturing\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Schema;

class EnsureManufacturingReady
{
    public function handle($request, Closure $next)
    {
        $user = $request->user();
        abort_unless($user && $user->business_id, 403);
        if ($request->session()->has('user.business_id')) {
            abort_unless((int) $request->session()->get('user.business_id') === (int) $user->business_id, 403);
        }
        abort_unless(Schema::hasTable('mfg_production_orders') && Schema::hasTable('inventory_postings'), 503, 'Manufacturing database migrations have not been installed.');
        return $next($request);
    }
}

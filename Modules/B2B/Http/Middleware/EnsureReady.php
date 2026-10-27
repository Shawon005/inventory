<?php

namespace Modules\B2B\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Schema;

class EnsureReady
{
    public function handle($request, Closure $next)
    {
        abort_unless(Schema::hasTable('b2b_sale_details'), 503, 'Install the B2B migration before opening this workspace.');
        return $next($request);
    }
}

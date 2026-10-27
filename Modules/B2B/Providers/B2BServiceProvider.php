<?php

namespace Modules\B2B\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class B2BServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'b2b');
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        Route::middleware('web')->group(__DIR__.'/../Routes/web.php');
    }
}

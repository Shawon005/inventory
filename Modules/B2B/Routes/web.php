<?php

use Illuminate\Support\Facades\Route;
use Modules\B2B\Http\Controllers\WorkspaceController as Workspace;
use Modules\B2B\Http\Middleware\EnsureReady;

Route::prefix('b2b')->name('b2b.')->middleware(['setData', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin', EnsureReady::class])->group(function () {
    Route::get('/', [Workspace::class, 'page'])->name('dashboard');
    foreach (['products', 'inventory', 'transfers', 'customers', 'pos', 'sales', 'dues', 'reports', 'movements'] as $screen) { Route::get($screen, [Workspace::class, 'page'])->defaults('screen', $screen)->name($screen); }
    Route::get('data/{resource}', [Workspace::class, 'data'])->whereIn('resource', ['products', 'customers', 'sales', 'transfers', 'movements', 'summary'])->name('data');
    Route::patch('products/{id}', [Workspace::class, 'product'])->whereNumber('id')->name('products.configure');
    Route::post('transfers', [Workspace::class, 'transfer'])->name('transfers.store');
    Route::get('transfers/{id}', [Workspace::class, 'transferDetails'])->whereNumber('id')->name('transfers.show');
    Route::post('customers', [Workspace::class, 'customer'])->name('customers.store');
    Route::post('sales', [Workspace::class, 'checkout'])->name('sales.store');
    Route::post('sales/{id}/payments', [Workspace::class, 'payment'])->whereNumber('id')->name('payments.store');
    Route::post('sales/{id}/returns', [Workspace::class, 'returnItems'])->whereNumber('id')->name('returns.store');
    Route::get('invoices/{id}', [Workspace::class, 'invoice'])->whereNumber('id')->name('invoices.show');
    Route::get('reports/export', [Workspace::class, 'export'])->name('reports.export');
});

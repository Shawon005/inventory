<?php

use Illuminate\Support\Facades\Route;
use Modules\Manufacturing\Http\Controllers\ManufacturingController as Manufacturing;
use Modules\Manufacturing\Http\Controllers\ManufacturingPageController as Pages;
use Modules\Manufacturing\Http\Middleware\EnsureManufacturingReady;

Route::prefix('manufacturing')->name('manufacturing.')->middleware([
    'setData', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin', EnsureManufacturingReady::class,
])->group(function () {
    Route::get('/', [Manufacturing::class, 'index'])->name('dashboard');
    Route::get('raw-materials', [Pages::class, 'materials'])->name('materials');
    Route::get('components', [Pages::class, 'components'])->name('components');
    Route::get('history', [Pages::class, 'history'])->name('history');
    Route::get('history/data', [Manufacturing::class, 'history'])->name('history.data');
    Route::get('lookups', [Manufacturing::class, 'lookups'])->name('lookups');
    Route::get('products/{id}/manufacturing', [Pages::class, 'product'])->whereNumber('id')->name('products.details');
    Route::get('products/{id}/details', [Manufacturing::class, 'productDetails'])->whereNumber('id')->name('products.details.data');
    Route::get('reports/export', [Manufacturing::class, 'export'])->name('reports.export');
    Route::get('products', [Manufacturing::class, 'products'])->name('products.index');
    Route::patch('products/{id}', [Manufacturing::class, 'configureProduct'])->whereNumber('id')->name('products.configure');
    Route::get('boms', [Manufacturing::class, 'bomIndex'])->name('boms.index');
    Route::get('boms/{id}', [Manufacturing::class, 'bomShow'])->whereNumber('id')->name('boms.show');
    Route::post('boms', [Manufacturing::class, 'bomStore'])->name('boms.store');
    Route::post('boms/{id}/versions', [Manufacturing::class, 'bomRevise'])->whereNumber('id')->name('boms.revise');
    Route::patch('boms/{id}/active', [Manufacturing::class, 'bomActive'])->whereNumber('id')->name('boms.active');
    Route::get('bom-versions/{versionId}/requirements', [Manufacturing::class, 'requirements'])->whereNumber('versionId')->name('boms.requirements');
    Route::get('production-orders', [Manufacturing::class, 'orderIndex'])->name('orders.index');
    Route::get('production-orders/{id}', [Manufacturing::class, 'orderShow'])->whereNumber('id')->name('orders.show');
    Route::get('production-orders/{id}/requirements', [Manufacturing::class, 'orderRequirements'])->whereNumber('id')->name('orders.requirements');
    Route::post('production-orders', [Manufacturing::class, 'orderStore'])->name('orders.store');
    Route::patch('production-orders/{id}', [Manufacturing::class, 'orderUpdate'])->whereNumber('id')->name('orders.update');
    Route::post('production-orders/{id}/plan', [Manufacturing::class, 'plan'])->whereNumber('id')->name('orders.plan');
    Route::post('production-orders/{id}/start', [Manufacturing::class, 'start'])->whereNumber('id')->name('orders.start');
    Route::post('production-orders/{id}/cancel', [Manufacturing::class, 'cancel'])->whereNumber('id')->name('orders.cancel');
    Route::prefix('production-orders/{id}/batches/{batchId}')->whereNumber(['id', 'batchId'])->group(function () {
        Route::post('receive', [Manufacturing::class, 'receive'])->name('batches.receive');
        Route::post('return-materials', [Manufacturing::class, 'materialReturn'])->name('batches.return-materials');
        Route::post('scrap', [Manufacturing::class, 'scrap'])->name('batches.scrap');
        Route::post('reverse', [Manufacturing::class, 'reverse'])->name('batches.reverse');
    });
    Route::get('stock-movements', [Manufacturing::class, 'movements'])->name('movements');
    Route::post('stock-adjustments', [Manufacturing::class, 'adjustStock'])->name('stock-adjustments.store');
    Route::get('reports', [Manufacturing::class, 'report'])->name('reports');
});

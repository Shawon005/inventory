<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Keep the scaffold's authenticated identity endpoint for compatibility.
// Production commands use the CSRF-protected web JSON routes until an API tenant context is provided.
Route::middleware(['auth:api', 'can:manufacturing.view'])->get('/manufacturing', function (Request $request) {
    return $request->user();
});

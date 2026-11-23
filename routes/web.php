<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::view('/admin/{path?}', 'admin')
    ->where('path', '.*')
    ->name('admin');

// The bundled storefront (a Vue SPA that uses only the Storefront API) answers every other
// address, except the API, Sanctum, stored files and the health check.
Route::view('/{path?}', 'storefront')
    ->where('path', '^(?!(admin|api|sanctum|storage)(/|$)|up$).*')
    ->name('storefront');

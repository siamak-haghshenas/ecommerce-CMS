<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::view('/admin/{path?}', 'admin')
    ->where('path', '.*')
    ->name('admin');

Route::view('/{path?}', 'storefront')
    ->where('path', '^(?!api|sanctum|up|admin|build|storage).*$')
    ->name('storefront');

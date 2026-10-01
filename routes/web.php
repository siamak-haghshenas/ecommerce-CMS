<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::view('/admin/{path?}', 'admin')
    ->where('path', '.*')
    ->name('admin');

// This project is a headless CMS: there is no built-in storefront, so the root opens the admin panel.
Route::redirect('/', '/admin');

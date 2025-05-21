<?php

use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ProductController::class, 'appView'])->name('app');

Route::get('/suggestions', [ProductController::class, 'suggestions'])
    ->name('product.suggestions');

Route::get('/api/analogs', [ProductController::class, 'apiAnalogs'])
    ->name('api.analogs');

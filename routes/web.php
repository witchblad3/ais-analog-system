<?php

use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ProductController::class, 'home'])->name('product.home');
Route::get('product/{id}/analogs', [ProductController::class, 'showAnalogs'])->name('product.analogs');
Route::post('/upload-csv', [ProductController::class, 'uploadCsv'])->name('product.uploadCsv');





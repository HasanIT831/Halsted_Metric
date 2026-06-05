<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HalsteadController;

Route::get(
    '/',
    [HalsteadController::class, 'index']
);

Route::post(
    '/hitung',
    [HalsteadController::class, 'hitung']
)->name('halstead.hitung');
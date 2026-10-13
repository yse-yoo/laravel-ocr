<?php

use App\Http\Controllers\FoodController;
use App\Http\Controllers\OcrController;
use Illuminate\Support\Facades\Route;

Route::get('/', [OcrController::class, 'index'])->name('ocr');
Route::post('/ocr', [OcrController::class, 'store'])->name('ocr.store');

Route::get('/food', [FoodController::class, 'index'])->name('food.index');
Route::post('/food', [FoodController::class, 'store'])->name('food.store');
Route::delete('/food/{food}', [FoodController::class, 'destroy'])->name('food.destroy');

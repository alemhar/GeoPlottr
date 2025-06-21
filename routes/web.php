<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

use App\Http\Controllers\MeasurementController;
Route::get('/', [MeasurementController::class, 'index'])->name('home');
Route::post('/measurements', [MeasurementController::class, 'store'])->name('measurements.store');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

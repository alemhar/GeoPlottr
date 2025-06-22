<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

use App\Http\Controllers\MeasurementController;

// Main application routes
Route::get('/', [MeasurementController::class, 'index'])->middleware(['auth', 'verified'])->name('home');
Route::post('/measurements', [MeasurementController::class, 'store'])->name('measurements.store');

// API routes for individual measurements
Route::prefix('api')->group(function () {
    Route::get('/measurements/{id}', [MeasurementController::class, 'apiShow'])->name('api.measurements.show');
    Route::put('/measurements/{id}', [MeasurementController::class, 'update'])->name('api.measurements.update');
});

// Route::get('dashboard', function () {
//     return Inertia::render('Dashboard');
// })->middleware(['auth', 'verified'])->name('dashboard');

Route::get('dashboard', [MeasurementController::class, 'index'])->name('dashboard');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

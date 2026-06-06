<?php

use App\Http\Controllers\Web\CatalogController;
use App\Http\Controllers\Web\OnboardingApplicationController;
use App\Http\Middleware\EnsureApprovedReseller;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome');

// Public reseller onboarding application (pre-auth).
Route::get('/apply', [OnboardingApplicationController::class, 'create'])->name('apply');
Route::post('/apply', [OnboardingApplicationController::class, 'store'])
    ->middleware('throttle:5,60') // a few submissions per IP per hour
    ->name('apply.store');
Route::inertia('/apply/success', 'Onboarding/Success')->name('apply.success');

// Gated catalog: authenticated + approved reseller (or internal staff) + view_catalog.
Route::middleware(['auth', EnsureApprovedReseller::class, 'can:view_catalog'])->group(function (): void {
    Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');
    Route::get('/catalog/{product:uuid}', [CatalogController::class, 'show'])->name('catalog.show');
});

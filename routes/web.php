<?php

use App\Http\Controllers\Web\OnboardingApplicationController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome');

// Public reseller onboarding application (pre-auth).
Route::get('/apply', [OnboardingApplicationController::class, 'create'])->name('apply');
Route::post('/apply', [OnboardingApplicationController::class, 'store'])
    ->middleware('throttle:5,60') // a few submissions per IP per hour
    ->name('apply.store');
Route::inertia('/apply/success', 'Onboarding/Success')->name('apply.success');

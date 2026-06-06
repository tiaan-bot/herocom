<?php

use App\Http\Controllers\Web\Auth\ForgotPasswordController;
use App\Http\Controllers\Web\Auth\LoginController;
use App\Http\Controllers\Web\Auth\ResetPasswordController;
use App\Http\Controllers\Web\Auth\SetPasswordController;
use App\Http\Controllers\Web\CatalogController;
use App\Http\Controllers\Web\OnboardingApplicationController;
use App\Http\Middleware\EnsureApprovedReseller;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome');

// Reseller-portal auth (hand-wired Inertia; no self-registration — apply via /apply).
Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'store']);
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->middleware('throttle:6,1')->name('password.email');
Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
Route::post('/reset-password', [ResetPasswordController::class, 'store'])->middleware('throttle:6,1')->name('password.update');

// First-time set-password: signed, 7-day link from the welcome email.
Route::get('/set-password/{user:uuid}', [SetPasswordController::class, 'create'])->name('password.set');
Route::post('/set-password/{user:uuid}', [SetPasswordController::class, 'store'])->name('password.set.store');
Route::post('/set-password/{user:uuid}/resend', [SetPasswordController::class, 'resend'])
    ->middleware('throttle:3,10')->name('password.set.resend');

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

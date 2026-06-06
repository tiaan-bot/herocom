<?php

use App\Http\Controllers\Web\Auth\ForgotPasswordController;
use App\Http\Controllers\Web\Auth\LoginController;
use App\Http\Controllers\Web\Auth\ResetPasswordController;
use App\Http\Controllers\Web\Auth\SetPasswordController;
use App\Http\Controllers\Web\CartController;
use App\Http\Controllers\Web\CatalogController;
use App\Http\Controllers\Web\CheckoutController;
use App\Http\Controllers\Web\InvoiceController;
use App\Http\Controllers\Web\OnboardingApplicationController;
use App\Http\Controllers\Web\OrderController;
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

// Gated portal: authenticated + approved reseller (or internal staff).
Route::middleware(['auth', EnsureApprovedReseller::class])->group(function (): void {
    // Browsing the catalogue + viewing the cart needs view_catalog.
    Route::middleware('can:view_catalog')->group(function (): void {
        Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog.index');
        Route::get('/catalog/{product:uuid}', [CatalogController::class, 'show'])->name('catalog.show');
        Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    });

    // My Orders (reseller-facing, scoped to the user's company).
    Route::middleware('can:view_orders')->group(function (): void {
        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order:uuid}', [OrderController::class, 'show'])->name('orders.show');
    });

    // My Invoices (Zoho-mirrored, scoped to the user's company; drafts hidden).
    Route::middleware('can:view_invoices')->group(function (): void {
        Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/{invoice:uuid}', [InvoiceController::class, 'show'])->name('invoices.show');
    });

    // Mutating the cart + checking out needs place_orders (reseller_viewer is read-only).
    Route::middleware('can:place_orders')->group(function (): void {
        Route::post('/cart', [CartController::class, 'store'])->name('cart.store');
        Route::patch('/cart/items/{cartItem}', [CartController::class, 'update'])->name('cart.items.update');
        Route::delete('/cart/items/{cartItem}', [CartController::class, 'destroy'])->name('cart.items.destroy');

        Route::get('/checkout', [CheckoutController::class, 'create'])->name('checkout');
        Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
        Route::get('/checkout/success', [CheckoutController::class, 'success'])->name('checkout.success');
    });
});

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => true,
        ]);
    }

    public function store(LoginRequest $request): \Symfony\Component\HttpFoundation\Response
    {
        $request->authenticate();
        $request->session()->regenerate();

        $user = $request->user();

        // Internal staff (no company) → Filament admin panel. Filament is not an
        // Inertia page, so a normal redirect makes the Inertia client render its
        // HTML in an error modal; Inertia::location() forces a full-page visit so
        // the panel boots at its own URL.
        if (! ($user instanceof User && $user->company_id !== null)) {
            return Inertia::location(route('filament.admin.pages.dashboard'));
        }

        // Resellers (company users) → catalogue (an Inertia page).
        return redirect()->intended('/catalog');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}

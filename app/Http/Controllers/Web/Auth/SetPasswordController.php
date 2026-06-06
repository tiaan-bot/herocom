<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Auth;

use App\Domain\Identity\Actions\SetUserPasswordAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\OnboardingWelcomeNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Inertia\Inertia;
use Inertia\Response;

class SetPasswordController extends Controller
{
    public function create(Request $request, User $user): Response|RedirectResponse
    {
        if ($user->password_set_at !== null) {
            return redirect('/login')->with('status', 'Your password is already set — please log in.');
        }

        if (! $request->hasValidSignature()) {
            return Inertia::render('Auth/SetPasswordExpired', [
                'resendUrl' => route('password.set.resend', ['user' => $user->uuid]),
            ]);
        }

        return Inertia::render('Auth/SetPassword', [
            'name' => $user->name,
            'submitUrl' => $request->fullUrl(),
        ]);
    }

    public function store(Request $request, User $user, SetUserPasswordAction $setPassword): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        if ($user->password_set_at !== null) {
            return redirect('/login')->with('status', 'Your password is already set — please log in.');
        }

        $request->validate([
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $setPassword->execute($user, (string) $request->string('password'));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/catalog');
    }

    public function resend(Request $request, User $user): RedirectResponse
    {
        if ($user->password_set_at === null) {
            $user->notify(new OnboardingWelcomeNotification);
        }

        return redirect('/login')->with('status', 'If your account still needs a password, a new link has been sent to your email.');
    }
}

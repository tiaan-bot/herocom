<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactMessageRequest;
use App\Mail\ContactMessageReceived;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Marketing/Contact');
    }

    public function send(StoreContactMessageRequest $request): RedirectResponse
    {
        // Honeypot tripped: a real visitor never fills `website`. Pretend success
        // (so the bot sees no signal) and queue nothing. No DB persistence either way.
        if ($request->isHoneypotTripped()) {
            return back()->with('success', true);
        }

        $data = $request->validated();

        Mail::to(config('mail.marketing_contact_to'))->queue(new ContactMessageReceived(
            senderName: $data['name'],
            company: $data['company'] ?? null,
            email: $data['email'],
            phone: $data['phone'] ?? null,
            messageBody: $data['message'],
        ));

        return back()->with('success', true);
    }
}

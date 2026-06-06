<?php

declare(strict_types=1);

use App\Mail\ContactMessageReceived;
use Illuminate\Support\Facades\Mail;

function validContactPayload(array $overrides = []): array
{
    return array_replace([
        'name' => 'Thandi Dlamini',
        'company' => 'Acme Traders',
        'email' => 'thandi@acme.co.za',
        'phone' => '082 123 4567',
        'message' => 'Please set up a reseller account for us.',
    ], $overrides);
}

it('queues the mailable to the configured recipient with reply-to set to the sender', function () {
    Mail::fake();

    $this->from('/contact')
        ->post('/contact', validContactPayload())
        ->assertRedirect('/contact')
        ->assertSessionHasNoErrors();

    Mail::assertQueued(ContactMessageReceived::class, function (ContactMessageReceived $mail): bool {
        $replyTo = collect($mail->envelope()->replyTo);

        return $mail->hasTo('sales@herocom.co.za')
            && $mail->email === 'thandi@acme.co.za'
            && $mail->senderName === 'Thandi Dlamini'
            && $replyTo->contains(fn ($address): bool => $address->address === 'thandi@acme.co.za');
    });
});

it('returns validation errors and queues nothing for invalid input', function () {
    Mail::fake();

    $this->from('/contact')
        ->post('/contact', ['name' => '', 'email' => 'not-an-email', 'message' => ''])
        ->assertRedirect('/contact')
        ->assertSessionHasErrors(['name', 'email', 'message']);

    Mail::assertNothingQueued();
});

it('silently accepts but drops a submission when the honeypot is filled', function () {
    Mail::fake();

    $this->from('/contact')
        ->post('/contact', validContactPayload(['website' => 'http://spam.example']))
        ->assertRedirect('/contact')
        ->assertSessionHasNoErrors(); // looks like success to the bot

    Mail::assertNothingQueued();
});

it('throttles a 6th submission within a minute with a 429', function () {
    Mail::fake();

    foreach (range(1, 5) as $i) {
        $this->from('/contact')
            ->post('/contact', validContactPayload(['email' => "buyer{$i}@acme.co.za"]))
            ->assertRedirect('/contact');
    }

    $this->from('/contact')
        ->post('/contact', validContactPayload())
        ->assertStatus(429);
});

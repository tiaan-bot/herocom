<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Marketing;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Marketing/Contact');
    }

    // POST /contact (send) is built in Pass 3 — validation, honeypot, rate limit, queued mail.
}

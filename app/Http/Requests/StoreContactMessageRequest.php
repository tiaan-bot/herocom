<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContactMessageRequest extends FormRequest
{
    /**
     * Public marketing endpoint — gated by the `marketing-contact` throttle on the
     * route, not by an ability.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'company' => ['nullable', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:254'],
            'phone' => ['nullable', 'string', 'max:30'],
            'message' => ['required', 'string', 'max:2000'],
            // Honeypot: bots fill it, humans never see it. Validated lax here; the
            // controller silently drops the request when it is filled.
            'website' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function isHoneypotTripped(): bool
    {
        return filled($this->input('website'));
    }
}

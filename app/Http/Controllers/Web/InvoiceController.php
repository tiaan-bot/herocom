<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Billing\Models\Invoice;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->user($request);

        $invoices = Invoice::query()
            ->where('company_id', $user->company_id)
            ->visible()
            ->orderByDesc('invoice_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->through(fn (Invoice $invoice): array => [
                'uuid' => $invoice->uuid,
                'number' => $invoice->invoice_number,
                'date' => $invoice->invoice_date->toDateString(),
                'due_date' => $invoice->due_date?->toDateString(),
                'status' => $invoice->status->value,
                'total' => (float) $invoice->total,
                'balance' => (float) $invoice->balance,
                'currency' => $invoice->currency,
            ]);

        return Inertia::render('Invoices/Index', ['invoices' => $invoices]);
    }

    public function show(Request $request, Invoice $invoice): Response
    {
        Gate::authorize('view', $invoice);
        $invoice->load('order');

        return Inertia::render('Invoices/Show', [
            'invoice' => [
                'number' => $invoice->invoice_number,
                'status' => $invoice->status->value,
                'date' => $invoice->invoice_date->toDateString(),
                'due_date' => $invoice->due_date?->toDateString(),
                'currency' => $invoice->currency,
                'subtotal_ex_vat' => (float) $invoice->subtotal_ex_vat,
                'tax_total' => (float) $invoice->tax_total,
                'total' => (float) $invoice->total,
                'balance' => (float) $invoice->balance,
                'order_number' => $invoice->order?->order_number,
                // Zoho's customer-facing pay link; only meaningful while a balance is owed.
                'payment_url' => $invoice->payment_url,
                'can_pay' => (float) $invoice->balance > 0 && filled($invoice->payment_url),
            ],
        ]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}

<?php

declare(strict_types=1);

use App\Domain\Billing\Actions\SyncInvoicesFromZoho;
use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Onboarding\Models\Company;
use App\Domain\Ordering\Models\Order;
use App\Domain\Shared\Zoho\Models\ZohoToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'zoho.client_id' => 'cid',
        'zoho.client_secret' => 'secret',
        'zoho.organization_id' => 'org-1',
        'zoho.accounts_domain' => 'accounts.zoho.com',
        'zoho.api_domain' => 'www.zohoapis.com',
    ]);
    ZohoToken::query()->create([
        'refresh_token' => 'r',
        'access_token' => 'valid',
        'access_token_expires_at' => now()->addHour(),
    ]);
    Http::preventStrayRequests();
});

// The Zoho list endpoint is a light summary — only invoice_id + customer_id matter here.
function zohoSummary(array $overrides = []): array
{
    return array_replace([
        'invoice_id' => '5001',
        'customer_id' => 'cust-1',
        'total' => 115,
        'balance' => 115,
    ], $overrides);
}

// The detail endpoint carries sub_total/tax_total and the salesorders linkage.
function zohoDetail(array $overrides = []): array
{
    return array_replace([
        'invoice_id' => '5001',
        'invoice_number' => 'INV-000001',
        'customer_id' => 'cust-1',
        'status' => 'sent',
        'date' => '2026-06-01',
        'due_date' => '2026-06-30',
        'sub_total' => 100,
        'tax_total' => 15,
        'total' => 115,
        'balance' => 115,
        'currency_code' => 'ZAR',
        'invoice_url' => 'https://invoice.zoho.com/pay/abc',
        'last_modified_time' => '2026-06-01T10:00:00+0200',
        'salesorders' => [],
    ], $overrides);
}

/**
 * Fake the list (sequence: one page then empty) and detail endpoints. The detail
 * pattern is registered first so it wins over the list pattern for /invoices/{id}.
 */
function fakeInvoiceSync(array $summaries, array $detailOverrides = []): void
{
    Http::fake([
        '*/books/v3/invoices/*' => Http::response(['invoice' => zohoDetail($detailOverrides)]),
        '*/books/v3/invoices?*' => Http::sequence()
            ->push(['invoices' => $summaries])
            ->push(['invoices' => []]),
    ]);
}

function syncInvoices(bool $full = true): void
{
    app(SyncInvoicesFromZoho::class)->execute($full);
}

it('syncs an invoice with VAT-inclusive totals from the detail payload', function () {
    Company::factory()->approved()->create(['zoho_customer_id' => 'cust-1']);
    fakeInvoiceSync([zohoSummary()]);

    syncInvoices();

    $invoice = Invoice::query()->sole();
    expect($invoice->invoice_number)->toBe('INV-000001')
        ->and($invoice->status)->toBe(InvoiceStatus::Sent)
        ->and((float) $invoice->subtotal_ex_vat)->toBe(100.0)
        ->and((float) $invoice->tax_total)->toBe(15.0)
        ->and((float) $invoice->total)->toBe(115.0)
        ->and((float) $invoice->balance)->toBe(115.0)
        ->and($invoice->payment_url)->toBe('https://invoice.zoho.com/pay/abc');
});

it('is idempotent across runs', function () {
    Company::factory()->approved()->create(['zoho_customer_id' => 'cust-1']);
    Http::fake([
        '*/books/v3/invoices/*' => Http::response(['invoice' => zohoDetail()]),
        '*/books/v3/invoices?*' => Http::sequence()
            ->push(['invoices' => [zohoSummary()]])->push(['invoices' => []])
            ->push(['invoices' => [zohoSummary()]])->push(['invoices' => []]),
    ]);

    syncInvoices();
    $first = Invoice::query()->sole();
    syncInvoices();

    expect(Invoice::query()->count())->toBe(1)
        ->and(Invoice::query()->sole()->id)->toBe($first->id);
});

it('skips and counts invoices for non-portal Zoho customers', function () {
    Company::factory()->approved()->create(['zoho_customer_id' => 'cust-1']);
    fakeInvoiceSync([zohoSummary(['customer_id' => 'stranger'])]);

    $result = app(SyncInvoicesFromZoho::class)->execute(full: true);

    expect(Invoice::query()->count())->toBe(0)
        ->and($result->skipped)->toBe(1)
        ->and($result->synced)->toBe(0);
});

it('matches the linked order via the sales order reference', function () {
    $company = Company::factory()->approved()->create(['zoho_customer_id' => 'cust-1']);
    $order = Order::factory()->create(['company_id' => $company->id, 'order_number' => 'HD-000050']);
    fakeInvoiceSync([zohoSummary()], ['salesorders' => [['reference_number' => 'HD-000050']]]);

    syncInvoices();

    expect(Invoice::query()->sole()->order_id)->toBe($order->id);
});

it('maps an unrecognised status to Unknown', function () {
    Company::factory()->approved()->create(['zoho_customer_id' => 'cust-1']);
    fakeInvoiceSync([zohoSummary()], ['status' => 'viewed']);

    syncInvoices();

    expect(Invoice::query()->sole()->status)->toBe(InvoiceStatus::Unknown);
});

it('zoho:sync-invoices --full reports synced and skipped counts', function () {
    Company::factory()->approved()->create(['zoho_customer_id' => 'cust-1']);
    fakeInvoiceSync([zohoSummary(), zohoSummary(['invoice_id' => '5002', 'customer_id' => 'stranger'])]);

    $this->artisan('zoho:sync-invoices', ['--full' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('Synced 1 invoice(s); skipped 1');

    expect(Invoice::query()->count())->toBe(1);
});

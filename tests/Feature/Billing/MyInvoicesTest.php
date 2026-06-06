<?php

declare(strict_types=1);

use App\Domain\Billing\Models\Invoice;
use App\Domain\Ordering\Models\Order;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('lists only the buyer\'s own company invoices', function () {
    $a = buyer();
    $b = buyer();
    Invoice::factory()->count(2)->create(['company_id' => $a->company_id]);
    Invoice::factory()->create(['company_id' => $b->company_id]);

    $this->actingAs($a)->get('/invoices')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Invoices/Index')->has('invoices.data', 2));
});

it('hides draft invoices from the portal list', function () {
    $a = buyer();
    Invoice::factory()->create(['company_id' => $a->company_id]);
    Invoice::factory()->draft()->create(['company_id' => $a->company_id]);

    $this->actingAs($a)->get('/invoices')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('invoices.data', 1));
});

it('forbids viewing another company\'s invoice', function () {
    $a = buyer();
    $b = buyer();
    $invoice = Invoice::factory()->create(['company_id' => $b->company_id]);

    $this->actingAs($a)->get("/invoices/{$invoice->uuid}")->assertForbidden();
});

it('forbids viewing a draft invoice even for its own company', function () {
    $a = buyer();
    $invoice = Invoice::factory()->draft()->create(['company_id' => $a->company_id]);

    $this->actingAs($a)->get("/invoices/{$invoice->uuid}")->assertForbidden();
});

it('shows an invoice detail with a pay link when a balance is owed', function () {
    $a = buyer();
    $order = Order::factory()->create(['company_id' => $a->company_id]);
    $invoice = Invoice::factory()->create([
        'company_id' => $a->company_id,
        'order_id' => $order->id,
        'balance' => 1982.60,
        'payment_url' => 'https://invoice.zoho.com/pay/abc',
    ]);

    $this->actingAs($a)->get("/invoices/{$invoice->uuid}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Invoices/Show')
            ->where('invoice.number', $invoice->invoice_number)
            ->where('invoice.order_number', $order->order_number)
            ->where('invoice.can_pay', true)
            ->where('invoice.payment_url', 'https://invoice.zoho.com/pay/abc'));
});

it('does not offer a pay link once the invoice is paid', function () {
    $a = buyer();
    $invoice = Invoice::factory()->paid()->create([
        'company_id' => $a->company_id,
        'payment_url' => 'https://invoice.zoho.com/pay/abc',
    ]);

    $this->actingAs($a)->get("/invoices/{$invoice->uuid}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('invoice.can_pay', false));
});

it('redirects a guest to login', function () {
    $invoice = Invoice::factory()->create();
    $this->get("/invoices/{$invoice->uuid}")->assertRedirect('/login');
});

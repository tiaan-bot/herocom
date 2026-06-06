<?php

declare(strict_types=1);

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\DataTransferObjects\InvoiceSyncResult;
use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Onboarding\Models\Company;
use App\Domain\Ordering\Models\Order;
use App\Domain\Shared\Zoho\ZohoClient;
use Carbon\CarbonImmutable;
use Psr\Log\LoggerInterface;

/**
 * One-way sync of Zoho Books invoices into the local mirror. Each invoice maps
 * to a portal Company via its zoho_customer_id; invoices for Zoho customers with
 * no portal company are skipped (counted + logged), not stored.
 */
final class SyncInvoicesFromZoho
{
    public function __construct(
        private readonly ZohoClient $zoho,
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(bool $full = false): InvoiceSyncResult
    {
        $now = CarbonImmutable::now();
        $filters = $full ? [] : $this->incrementalFilter();

        // Cache portal companies by Zoho customer id for O(1) resolution.
        $companies = Company::query()
            ->whereNotNull('zoho_customer_id')
            ->pluck('id', 'zoho_customer_id');

        $synced = 0;
        $skipped = 0;
        $page = 1;

        do {
            $invoices = $this->zoho->listInvoices($page, $filters);

            foreach ($invoices as $invoice) {
                $zohoInvoiceId = (string) ($invoice['invoice_id'] ?? '');
                $customerId = (string) ($invoice['customer_id'] ?? '');

                // The list omits sub_total/tax_total/salesorders — skip non-portal
                // customers cheaply here, then fetch the detail for the rest.
                if ($zohoInvoiceId === '' || ! isset($companies[$customerId])) {
                    $skipped++;

                    continue;
                }

                $detail = $this->zoho->getInvoice($zohoInvoiceId);

                Invoice::query()->updateOrCreate(
                    ['zoho_invoice_id' => $zohoInvoiceId],
                    $this->attributes($detail, (int) $companies[$customerId], $customerId, $now),
                );

                $synced++;
            }

            $page++;
        } while ($invoices !== []);

        if ($skipped > 0) {
            $this->logger->info('Invoice sync skipped invoices for non-portal Zoho customers.', ['skipped' => $skipped]);
        }

        return new InvoiceSyncResult($synced, $skipped);
    }

    /**
     * @return array<string, string>
     */
    private function incrementalFilter(): array
    {
        $since = Invoice::query()->max('zoho_last_modified_at');

        if ($since === null) {
            return [];
        }

        return ['last_modified_time' => CarbonImmutable::parse($since)->toIso8601String()];
    }

    /**
     * @param  array<string, mixed>  $invoice  The full invoice detail payload.
     * @return array<string, mixed>
     */
    private function attributes(array $invoice, int $companyId, string $customerId, CarbonImmutable $now): array
    {
        return [
            'company_id' => $companyId,
            'order_id' => $this->matchOrderId($invoice),
            'zoho_customer_id' => $customerId,
            'invoice_number' => (string) ($invoice['invoice_number'] ?? ''),
            'status' => InvoiceStatus::fromZoho($invoice['status'] ?? null),
            'invoice_date' => $invoice['date'] ?? $now->toDateString(),
            'due_date' => $invoice['due_date'] ?? null,
            'subtotal_ex_vat' => $invoice['sub_total'] ?? 0,
            'tax_total' => $invoice['tax_total'] ?? 0,
            'total' => $invoice['total'] ?? 0,
            'balance' => $invoice['balance'] ?? 0,
            'currency' => $invoice['currency_code'] ?? 'ZAR',
            // Verified against a live payload: customer-facing pay link.
            'payment_url' => $invoice['invoice_url'] ?? null,
            'zoho_last_modified_at' => isset($invoice['last_modified_time'])
                ? CarbonImmutable::parse((string) $invoice['last_modified_time'])
                : null,
            'last_synced_at' => $now,
        ];
    }

    /**
     * Link to a portal Order via the linked sales order's reference_number — that
     * carries our HD-order_number (the invoice's own reference_number is the Zoho
     * SO number, not ours). Only present on the detail payload's `salesorders`.
     *
     * @param  array<string, mixed>  $invoice
     */
    private function matchOrderId(array $invoice): ?int
    {
        /** @var array<int, array<string, mixed>> $salesOrders */
        $salesOrders = $invoice['salesorders'] ?? [];
        $reference = $salesOrders[0]['reference_number'] ?? null;

        if (blank($reference)) {
            return null;
        }

        return Order::query()->where('order_number', $reference)->value('id');
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Listeners;

use App\Domain\Onboarding\Actions\EnsureZohoCustomerAction;
use App\Domain\Ordering\Enums\ZohoPushStatus;
use App\Domain\Ordering\Events\OrderPlaced;
use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Domain\Shared\Zoho\Exceptions\ZohoException;
use App\Domain\Shared\Zoho\ZohoClient;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

final class PushOrderToZoho implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 5;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 60, 300, 900, 3600];

    public function __construct(
        private readonly ZohoClient $zoho,
        private readonly EnsureZohoCustomerAction $ensureCustomer,
    ) {}

    public function handle(OrderPlaced $event): void
    {
        $order = $event->order->fresh(['company', 'items']);

        // Idempotency anchor: a pushed order already has its sales-order id.
        if ($order === null || filled($order->zoho_salesorder_id)) {
            return;
        }

        try {
            // Company may have no Zoho customer yet (approved before the Zoho pass,
            // or a failed company push) — create it first.
            $customerId = $this->ensureCustomer->execute($order->company);

            $salesOrder = $this->zoho->createSalesOrder([
                'customer_id' => $customerId,
                'reference_number' => $order->order_number,
                'line_items' => $order->items->map(fn (OrderItem $item): array => [
                    'item_id' => $item->zoho_item_id,
                    'name' => $item->name,
                    'rate' => (float) $item->unit_price, // the discounted price the reseller saw
                    'quantity' => (float) $item->quantity,
                ])->all(),
                'notes' => $order->customer_note,
            ]);

            $salesOrderId = (string) ($salesOrder['salesorder_id'] ?? '');
            if ($salesOrderId === '') {
                throw ZohoException::missing('salesorder_id');
            }

            $order->update([
                'zoho_salesorder_id' => $salesOrderId,
                'zoho_push_status' => ZohoPushStatus::Pushed,
                'zoho_pushed_at' => CarbonImmutable::now(),
                'zoho_push_error' => null,
            ]);
        } catch (ZohoException $e) {
            $order->update([
                'zoho_push_status' => ZohoPushStatus::Failed,
                'zoho_push_error' => $e->getMessage(),
            ]);

            throw $e; // let the queue retry with backoff
        }
    }

    public function failed(OrderPlaced $event, \Throwable $exception): void
    {
        Order::query()->whereKey($event->order->getKey())->update([
            'zoho_push_status' => ZohoPushStatus::Failed,
            'zoho_push_error' => $exception->getMessage(),
        ]);
    }
}

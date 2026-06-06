<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Actions;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Services\CompanyPriceCalculator;
use App\Domain\Ordering\DataTransferObjects\PlaceOrderData;
use App\Domain\Ordering\Enums\CartStatus;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Enums\ZohoPushStatus;
use App\Domain\Ordering\Events\OrderPlaced;
use App\Domain\Ordering\Exceptions\OrderException;
use App\Domain\Ordering\Models\CartItem;
use App\Domain\Ordering\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\DatabaseManager;

/**
 * Snapshots the open cart into an immutable order, closes the cart, and raises
 * OrderPlaced (Zoho push + confirmation email run as queued listeners).
 */
final class PlaceOrderAction
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly GetOrCreateOpenCart $carts,
        private readonly CompanyPriceCalculator $pricing,
        private readonly Dispatcher $events,
    ) {}

    public function execute(User $user, PlaceOrderData $data): Order
    {
        $company = $user->company;
        if ($company === null) {
            throw OrderException::missingCompany();
        }

        return $this->db->transaction(function () use ($user, $company, $data): Order {
            $cart = $this->carts->execute($user);
            $cart->load('items.product');

            $lines = $cart->items->filter(
                fn (CartItem $item): bool => $item->product->status === ProductStatus::Active && (float) $item->quantity > 0,
            );

            if ($lines->isEmpty()) {
                throw OrderException::emptyCart();
            }

            $discount = (float) $company->discount_percent;

            $order = Order::create([
                'company_id' => $company->getKey(),
                'placed_by' => $user->getKey(),
                'status' => OrderStatus::Placed,
                'currency' => 'ZAR',
                'discount_percent_applied' => $discount,
                'subtotal_ex_vat' => 0,
                'delivery_address_line1' => $data->deliveryAddressLine1,
                'delivery_address_line2' => $data->deliveryAddressLine2,
                'delivery_city' => $data->deliveryCity,
                'delivery_province' => $data->deliveryProvince,
                'delivery_postal_code' => $data->deliveryPostalCode,
                'delivery_country_code' => $data->deliveryCountryCode,
                'customer_note' => $data->customerNote,
                'zoho_push_status' => ZohoPushStatus::Pending,
            ]);

            $order->update(['order_number' => 'HD-'.str_pad((string) $order->getKey(), 6, '0', STR_PAD_LEFT)]);

            $subtotal = 0.0;

            foreach ($lines as $item) {
                $product = $item->product;
                $quantity = (float) $item->quantity;
                $unitPrice = $this->pricing->netPrice((float) $product->rate, $discount);
                $lineTotal = round($quantity * $unitPrice, 4);
                $subtotal += $lineTotal;

                $order->items()->create([
                    'product_id' => $product->getKey(),
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'quantity' => $quantity,
                    'unit_price_list' => (float) $product->rate,
                    'unit_price' => $unitPrice,
                    'currency' => $product->rate_currency,
                    'line_total_ex_vat' => $lineTotal,
                    'zoho_item_id' => $product->zoho_item_id,
                ]);
            }

            $order->update(['subtotal_ex_vat' => round($subtotal, 4)]);
            $cart->update(['status' => CartStatus::Converted]);

            $this->events->dispatch(new OrderPlaced($order));

            return $order->refresh()->load('items');
        });
    }
}

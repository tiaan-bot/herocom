<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Services\CompanyPriceCalculator;
use App\Domain\Ordering\Actions\GetOrCreateOpenCart;
use App\Domain\Ordering\Actions\PlaceOrderAction;
use App\Domain\Ordering\Exceptions\OrderException;
use App\Domain\Ordering\Models\CartItem;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CompanyPriceCalculator $pricing,
        private readonly GetOrCreateOpenCart $carts,
    ) {}

    public function create(Request $request): Response|RedirectResponse
    {
        $user = $this->user($request);
        $company = $user->company;
        abort_if($company === null, 403); // checkout is reseller-only

        $cart = $this->carts->execute($user);
        $cart->load('items.product');

        $discount = (float) $company->discount_percent;

        $lines = $cart->items
            ->filter(fn (CartItem $item): bool => $item->product->status === ProductStatus::Active)
            ->values()
            ->map(function (CartItem $item) use ($discount): array {
                $unit = $this->pricing->netPrice((float) $item->product->rate, $discount);

                return [
                    'name' => $item->product->name,
                    'sku' => $item->product->sku,
                    'quantity' => (float) $item->quantity,
                    'unit_price' => $unit,
                    'line_total' => round((float) $item->quantity * $unit, 2),
                ];
            });

        if ($lines->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        return Inertia::render('Checkout/Index', [
            'lines' => $lines->all(),
            'subtotal' => round((float) $lines->sum('line_total'), 2),
            'currency' => 'ZAR',
            'defaultAddress' => [
                'delivery_address_line1' => $company->address_line1,
                'delivery_address_line2' => $company->address_line2,
                'delivery_city' => $company->city,
                'delivery_province' => $company->province,
                'delivery_postal_code' => $company->postal_code,
                'delivery_country_code' => $company->country_code,
            ],
        ]);
    }

    public function store(StoreOrderRequest $request, PlaceOrderAction $placeOrder): RedirectResponse
    {
        try {
            $order = $placeOrder->execute($this->user($request), $request->toData());
        } catch (OrderException $e) {
            return redirect()->route('cart.index')->with('error', $e->getMessage());
        }

        return redirect()->route('checkout.success')->with('order', [
            'number' => $order->order_number,
            'uuid' => $order->uuid,
        ]);
    }

    public function success(Request $request): Response|RedirectResponse
    {
        $order = $request->session()->get('order');

        if (! is_array($order)) {
            return redirect()->route('catalog.index');
        }

        return Inertia::render('Checkout/Success', ['order' => $order]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}

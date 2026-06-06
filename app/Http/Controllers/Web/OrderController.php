<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Ordering\Models\Order;
use App\Domain\Ordering\Models\OrderItem;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $this->user($request);

        $orders = Order::query()
            ->where('company_id', $user->company_id)
            ->latest()
            ->paginate(15)
            ->through(fn (Order $order): array => [
                'uuid' => $order->uuid,
                'number' => $order->order_number,
                'date' => $order->created_at?->toDateString(),
                'status' => $order->status->value,
                'total' => (float) $order->subtotal_ex_vat,
                'currency' => $order->currency,
            ]);

        return Inertia::render('Orders/Index', ['orders' => $orders]);
    }

    public function show(Request $request, Order $order): Response
    {
        Gate::authorize('view', $order);
        $order->load('items');

        return Inertia::render('Orders/Show', [
            'order' => [
                'number' => $order->order_number,
                'status' => $order->status->value,
                'date' => $order->created_at?->toDateString(),
                'currency' => $order->currency,
                'subtotal' => (float) $order->subtotal_ex_vat,
                'note' => $order->customer_note,
                'delivery' => [
                    'line1' => $order->delivery_address_line1,
                    'line2' => $order->delivery_address_line2,
                    'city' => $order->delivery_city,
                    'province' => $order->delivery_province,
                    'postal_code' => $order->delivery_postal_code,
                ],
                'lines' => $order->items->map(fn (OrderItem $item): array => [
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'quantity' => (float) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'line_total' => (float) $item->line_total_ex_vat,
                ])->all(),
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

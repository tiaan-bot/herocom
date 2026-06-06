<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Services\CompanyPriceCalculator;
use App\Domain\Ordering\Actions\AddToCart;
use App\Domain\Ordering\Actions\GetOrCreateOpenCart;
use App\Domain\Ordering\Actions\RemoveCartItem;
use App\Domain\Ordering\Actions\UpdateCartItemQuantity;
use App\Domain\Ordering\Enums\CartStatus;
use App\Domain\Ordering\Models\CartItem;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function __construct(
        private readonly CompanyPriceCalculator $pricing,
        private readonly GetOrCreateOpenCart $carts,
    ) {}

    public function index(Request $request): Response
    {
        $cart = $this->carts->execute($this->user($request));
        $cart->load('items.product');
        $discount = $this->companyDiscount($request);

        $lines = $cart->items
            ->sortBy(fn (CartItem $item): string => $item->product->name)
            ->values()
            ->map(fn (CartItem $item): array => $this->presentLine($item, $discount));

        $subtotal = $lines->where('available', true)->sum('line_total');

        return Inertia::render('Cart/Index', [
            'lines' => $lines->all(),
            'subtotal' => round((float) $subtotal, 2),
            'currency' => 'ZAR',
            'hasUnavailable' => $lines->contains('available', false),
        ]);
    }

    public function store(Request $request, AddToCart $addToCart): RedirectResponse
    {
        $validated = $request->validate([
            'product' => ['required', 'string', 'exists:products,uuid'],
            'quantity' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        $product = Product::query()->where('uuid', $validated['product'])->firstOrFail();

        if ($product->status !== ProductStatus::Active) {
            return back()->with('error', 'That product is no longer available.');
        }

        $addToCart->execute($this->user($request), $product, (float) ($validated['quantity'] ?? 1));

        return back()->with('status', 'Added to cart.');
    }

    public function update(Request $request, CartItem $cartItem, UpdateCartItemQuantity $action): RedirectResponse
    {
        $this->authorizeOwnership($request, $cartItem);

        $validated = $request->validate([
            'quantity' => ['required', 'numeric', 'min:0'],
        ]);

        $action->execute($cartItem, (float) $validated['quantity']);

        return back();
    }

    public function destroy(Request $request, CartItem $cartItem, RemoveCartItem $action): RedirectResponse
    {
        $this->authorizeOwnership($request, $cartItem);

        $action->execute($cartItem);

        return back();
    }

    private function authorizeOwnership(Request $request, CartItem $cartItem): void
    {
        abort_unless(
            $cartItem->cart->user_id === $this->user($request)->getKey()
                && $cartItem->cart->status === CartStatus::Open,
            403,
        );
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function companyDiscount(Request $request): float
    {
        $user = $this->user($request);

        return $user->company !== null ? (float) $user->company->discount_percent : 0.0;
    }

    /**
     * @return array<string, mixed>
     */
    private function presentLine(CartItem $item, float $discount): array
    {
        $product = $item->product;
        $quantity = (float) $item->quantity;
        $unitPrice = $this->pricing->netPrice((float) $product->rate, $discount);

        return [
            'id' => $item->id,
            'product_uuid' => $product->uuid,
            'name' => $product->name,
            'sku' => $product->sku,
            'image_url' => $product->image_url,
            'quantity' => $quantity,
            'list_price' => (float) $product->rate,
            'your_price' => $unitPrice,
            'line_total' => round($quantity * $unitPrice, 2),
            'currency' => $product->rate_currency,
            'available' => $product->status === ProductStatus::Active,
        ];
    }
}

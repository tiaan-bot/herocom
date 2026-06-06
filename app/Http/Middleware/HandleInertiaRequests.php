<?php

namespace App\Http\Middleware;

use App\Domain\Ordering\Enums\CartStatus;
use App\Domain\Ordering\Models\CartItem;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user instanceof User ? [
                    'name' => $user->name,
                    'email' => $user->email,
                    'company' => $user->company?->legal_name,
                    'can' => [
                        'place_orders' => $user->can('place_orders'),
                    ],
                ] : null,
            ],
            'cartCount' => fn (): int => $this->openCartItemCount($user),
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }

    private function openCartItemCount(?Authenticatable $user): int
    {
        if (! $user instanceof User || $user->company_id === null) {
            return 0;
        }

        return CartItem::query()
            ->whereHas('cart', fn ($query) => $query
                ->where('user_id', $user->getKey())
                ->where('status', CartStatus::Open))
            ->count();
    }
}

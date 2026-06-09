<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Marketing;

use App\Domain\Catalog\Models\Product;
use App\Http\Controllers\Controller;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ProductsController extends Controller
{
    /**
     * The marketing page's fixed category cards. Counts are resolved against the
     * synced catalogue's `category` values (exact label match).
     *
     * @var list<string>
     */
    private const CATEGORY_LABELS = ['Surveillance', 'Networking', 'Power', 'Peripherals', 'Storage'];

    private const FEATURED_LIMIT = 8;

    public function index(): Response
    {
        return Inertia::render('Marketing/Products', [
            'featured' => $this->featured(),
            'categoryCounts' => $this->categoryCounts(),
        ]);
    }

    /**
     * Up to 8 active products: those flagged featured (by name) first, then topped
     * up with the most recently synced products. No pricing — gated behind sign-in.
     *
     * @return array<int, array{name: string, sku: string|null, brand: string|null, cat: string|null}>
     */
    private function featured(): array
    {
        // Featured implies synced — only portal-visible products are shown publicly.
        $featured = Product::query()->active()->where('sync_to_portal', true)->featured()
            ->orderBy('name')
            ->limit(self::FEATURED_LIMIT)
            ->get();

        if ($featured->count() < self::FEATURED_LIMIT) {
            $fill = Product::query()->active()->where('sync_to_portal', true)
                ->whereNotIn('id', $featured->modelKeys())
                ->orderByDesc('last_synced_at')
                ->orderByDesc('id')
                ->limit(self::FEATURED_LIMIT - $featured->count())
                ->get();

            $featured = $featured->concat($fill);
        }

        return $featured->map(fn (Product $product): array => [
            'name' => $product->name,
            'sku' => $product->sku,
            'brand' => $product->brand,
            'cat' => $product->category,
        ])->values()->all();
    }

    /**
     * Real active-product counts per marketing category label.
     *
     * @return array<string, int>
     */
    private function categoryCounts(): array
    {
        /** @var Collection<string, int> $counts */
        $counts = Product::query()->active()
            ->whereIn('category', self::CATEGORY_LABELS)
            ->selectRaw('category, count(*) as aggregate')
            ->groupBy('category')
            ->pluck('aggregate', 'category');

        return collect(self::CATEGORY_LABELS)
            ->mapWithKeys(fn (string $label): array => [$label => (int) $counts->get($label, 0)])
            ->all();
    }
}

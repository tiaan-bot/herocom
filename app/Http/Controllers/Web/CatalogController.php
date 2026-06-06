<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Enums\StockBand;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Services\CompanyPriceCalculator;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function __construct(
        private readonly CompanyPriceCalculator $pricing,
    ) {}

    public function index(Request $request): Response
    {
        $discount = $this->companyDiscount($request);
        $threshold = (int) config('catalog.low_stock_threshold', 5);

        $query = Product::query()->active();

        if (filled($search = trim((string) $request->string('q')))) {
            $like = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $q) use ($like): void {
                $q->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(sku) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(brand) LIKE ?', [$like]);
            });
        }

        if (filled($brand = $request->string('brand')->toString())) {
            $query->where('brand', $brand);
        }

        if (filled($category = $request->string('category')->toString())) {
            $query->where('category', $category);
        }

        if ($request->boolean('in_stock')) {
            $query->where('stock_on_hand', '>', 0);
        }

        match ($request->string('sort')->toString()) {
            'price' => $query->orderBy('rate'),
            'price_desc' => $query->orderByDesc('rate'),
            default => $query->orderBy('name'),
        };

        $products = $query->paginate((int) config('catalog.per_page', 24))
            ->withQueryString()
            ->through(fn (Product $product): array => $this->present($product, $discount, $threshold));

        return Inertia::render('Catalog/Index', [
            'products' => $products,
            'filters' => [
                'q' => $search,
                'brand' => $brand,
                'category' => $category,
                'in_stock' => $request->boolean('in_stock'),
                'sort' => $request->string('sort')->toString(),
            ],
            'brands' => Product::query()->active()->whereNotNull('brand')->distinct()->orderBy('brand')->pluck('brand'),
            'categories' => Product::query()->active()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
        ]);
    }

    public function show(Request $request, Product $product): Response
    {
        abort_unless($product->status === ProductStatus::Active, 404);

        $discount = $this->companyDiscount($request);
        $threshold = (int) config('catalog.low_stock_threshold', 5);

        return Inertia::render('Catalog/Show', [
            'product' => [
                ...$this->present($product, $discount, $threshold),
                'description' => $product->description,
            ],
        ]);
    }

    private function companyDiscount(Request $request): float
    {
        $user = $request->user();

        return $user instanceof User && $user->company !== null
            ? (float) $user->company->discount_percent
            : 0.0;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Product $product, float $discount, int $threshold): array
    {
        $listPrice = (float) $product->rate;

        return [
            'uuid' => $product->uuid,
            'name' => $product->name,
            'sku' => $product->sku,
            'brand' => $product->brand,
            'category' => $product->category,
            'unit' => $product->unit,
            'image_url' => $product->image_url,
            'currency' => $product->rate_currency,
            'list_price' => $listPrice,
            'your_price' => $this->pricing->netPrice($listPrice, $discount),
            'stock_band' => StockBand::for((float) $product->stock_on_hand, $threshold)->value,
        ];
    }
}

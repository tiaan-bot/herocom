<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Catalog\Models\Product;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a product's mirrored image from the private catalogue disk. Public
 * (no auth) but gated on portal visibility — the URL is stable and cache-busted
 * by the `?v=` document id, so it is safe to cache forever (CDN-friendly).
 */
class ProductImageController extends Controller
{
    public function __invoke(Product $product): StreamedResponse
    {
        abort_unless($product->sync_to_portal && $product->image_path !== null, 404);

        $disk = Storage::disk((string) config('catalog.image_disk', 'r2_catalog'));

        abort_unless($disk->exists($product->image_path), 404);

        $path = $product->image_path;

        return response()->stream(function () use ($disk, $path): void {
            $stream = $disk->readStream($path);
            if ($stream !== null) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $product->image_mime ?? 'application/octet-stream',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}

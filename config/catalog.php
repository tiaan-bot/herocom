<?php

declare(strict_types=1);

return [

    /*
    | Stock at or below this (and above zero) shows as "Low stock"; zero shows
    | "Out of stock". Resellers see a band, never our exact holdings.
    */
    'low_stock_threshold' => (int) env('CATALOG_LOW_STOCK_THRESHOLD', 5),

    // Products per page in the catalog grid.
    'per_page' => (int) env('CATALOG_PER_PAGE', 24),

];

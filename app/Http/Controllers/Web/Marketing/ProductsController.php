<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Marketing;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class ProductsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Marketing/Products');
    }
}

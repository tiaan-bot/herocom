<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;

it('serves the products page at /products with the Marketing/Products component', function () {
    $this->get('/products')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Marketing/Products'));
});

// Page content is hardcoded in the Vue component (client-rendered, no SSR), so we
// assert the absence of CGIC at the response level per §8.
it('does not mention CGIC anywhere on the products page', function () {
    $this->get('/products')->assertDontSee('CGIC', false);
});

<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;

it('serves the contact page at /contact with the Marketing/Contact component', function () {
    $this->get('/contact')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Marketing/Contact'));
});

// Page content is hardcoded in the Vue component (client-rendered, no SSR), so we
// assert the absence of CGIC at the response level per §8.
it('does not mention CGIC anywhere on the contact page', function () {
    $this->get('/contact')->assertDontSee('CGIC', false);
});

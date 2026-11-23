<?php

declare(strict_types=1);

it('serves the storefront SPA for the root and any shop path', function (string $path) {
    $this->withoutVite();

    $this->get($path)->assertOk()->assertViewIs('storefront');
})->with(['/', '/product/red-shirt', '/category/shoes?page=2']);

it('serves the admin panel SPA for any admin path', function () {
    $this->withoutVite();

    $this->get('/admin/customers/42')->assertOk()->assertViewIs('admin');
});

it('leaves API addresses to the API', function () {
    $this->getJson('/api/v1/unknown')->assertNotFound()->assertJsonStructure(['message']);
});

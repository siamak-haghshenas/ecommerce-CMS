<?php

declare(strict_types=1);

it('redirects the root URL to the admin panel because there is no built-in storefront', function () {
    $this->get('/')->assertRedirect('/admin');
});

it('serves the admin panel SPA for any admin path', function () {
    $this->get('/admin/customers/42')->assertOk()->assertViewIs('admin');
});

<?php

declare(strict_types=1);

namespace App\Data\Cart;

use App\Models\Cart;

/**
 * The cart a request works on. `cart` is unsaved when there is none yet and none was opened
 * (an empty cart); `newToken` is the token of a guest cart opened by this request, which the
 * storefront keeps and sends back in `X-Cart-Token`.
 */
final readonly class ResolvedCart
{
    public function __construct(
        public Cart $cart,
        public ?string $newToken = null,
    ) {}
}

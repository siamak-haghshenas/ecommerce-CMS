<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Storefront\Shipping;

use App\Actions\Shipping\QuoteShippingAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Storefront\Shipping\QuoteShippingRequest;
use App\Http\Resources\V1\Storefront\ShippingQuoteResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;

class ShippingQuoteController extends Controller
{
    /**
     * The shipping methods the logged-in customer can choose for their cart and city, with
     * their fees (or a coupon error code). Nothing is saved.
     */
    public function __invoke(QuoteShippingRequest $request, QuoteShippingAction $quote, #[CurrentUser] User $customer): ShippingQuoteResource
    {
        return new ShippingQuoteResource($quote->handle($customer, $request->cityId(), $request->cartItems(), $request->couponCode()));
    }
}

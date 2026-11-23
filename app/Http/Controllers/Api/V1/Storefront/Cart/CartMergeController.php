<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Storefront\Cart;

use App\Actions\Cart\MergeCartsAction;
use App\Actions\Cart\SummarizeCartAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Storefront\Cart\MergeCartRequest;
use App\Http\Resources\V1\Storefront\CartResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;

class CartMergeController extends Controller
{
    /**
     * After a login: move the guest cart of `cart_token` into the customer's cart and return it.
     * Safe to repeat (an unknown token changes nothing).
     */
    public function __invoke(MergeCartRequest $request, MergeCartsAction $merge, SummarizeCartAction $summarize, #[CurrentUser] User $customer): CartResource
    {
        return new CartResource($summarize->handle($merge->handle($customer, $request->cartToken())));
    }
}

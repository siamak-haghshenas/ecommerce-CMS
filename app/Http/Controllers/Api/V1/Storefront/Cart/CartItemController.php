<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Storefront\Cart;

use App\Actions\Cart\AddCartItemAction;
use App\Actions\Cart\RemoveCartItemAction;
use App\Actions\Cart\ResolveCartAction;
use App\Actions\Cart\SetCartItemQuantityAction;
use App\Actions\Cart\SummarizeCartAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Storefront\Cart\AddCartItemRequest;
use App\Http\Requests\Api\V1\Storefront\Cart\SetCartItemQuantityRequest;
use App\Http\Resources\V1\Storefront\CartResource;
use App\Models\Cart;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;

class CartItemController extends Controller
{
    /**
     * Put units of a variant in the cart (on top of the ones there). A guest without a cart gets
     * one, and its token in `meta.cart_token`.
     */
    public function store(AddCartItemRequest $request, ResolveCartAction $resolve, AddCartItemAction $add, SummarizeCartAction $summarize, #[CurrentUser] ?User $customer): CartResource
    {
        $resolved = $resolve->handle($customer, $request->header(Cart::TOKEN_HEADER), open: true);
        $add->handle($resolved->cart, $request->variantId(), $request->quantity());

        return (new CartResource($summarize->handle($resolved->cart)))->withNewToken($resolved->newToken);
    }

    /**
     * Set a line's quantity (404 when the variant isn't in the cart).
     */
    public function update(SetCartItemQuantityRequest $request, int $variant, ResolveCartAction $resolve, SetCartItemQuantityAction $set, SummarizeCartAction $summarize, #[CurrentUser] ?User $customer): CartResource
    {
        $cart = $resolve->handle($customer, $request->header(Cart::TOKEN_HEADER))->cart;
        $set->handle($cart, $variant, $request->quantity());

        return new CartResource($summarize->handle($cart));
    }

    /**
     * Take a variant out of the cart.
     */
    public function destroy(Request $request, int $variant, ResolveCartAction $resolve, RemoveCartItemAction $remove, SummarizeCartAction $summarize, #[CurrentUser] ?User $customer): CartResource
    {
        $cart = $resolve->handle($customer, $request->header(Cart::TOKEN_HEADER))->cart;
        $remove->handle($cart, $variant);

        return new CartResource($summarize->handle($cart));
    }
}

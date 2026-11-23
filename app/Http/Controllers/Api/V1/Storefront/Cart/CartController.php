<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Storefront\Cart;

use App\Actions\Cart\ClearCartAction;
use App\Actions\Cart\ResolveCartAction;
use App\Actions\Cart\SummarizeCartAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Storefront\CartResource;
use App\Models\Cart;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;

class CartController extends Controller
{
    /**
     * The logged-in customer's cart, or the guest cart of `X-Cart-Token` (empty when there is
     * none yet; reading never opens one).
     */
    public function show(Request $request, ResolveCartAction $resolve, SummarizeCartAction $summarize, #[CurrentUser] ?User $customer): CartResource
    {
        $cart = $resolve->handle($customer, $request->header(Cart::TOKEN_HEADER))->cart;

        return new CartResource($summarize->handle($cart));
    }

    /**
     * Empty the cart (lines and coupon).
     */
    public function destroy(Request $request, ResolveCartAction $resolve, ClearCartAction $clear, SummarizeCartAction $summarize, #[CurrentUser] ?User $customer): CartResource
    {
        $cart = $resolve->handle($customer, $request->header(Cart::TOKEN_HEADER))->cart;
        $clear->handle($cart);

        return new CartResource($summarize->handle($cart));
    }
}

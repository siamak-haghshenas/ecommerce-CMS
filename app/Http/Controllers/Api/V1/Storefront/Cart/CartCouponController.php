<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Storefront\Cart;

use App\Actions\Cart\ApplyCartCouponAction;
use App\Actions\Cart\RemoveCartCouponAction;
use App\Actions\Cart\ResolveCartAction;
use App\Actions\Cart\SummarizeCartAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Storefront\Cart\ApplyCartCouponRequest;
use App\Http\Resources\V1\Storefront\CartResource;
use App\Models\Cart;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;

class CartCouponController extends Controller
{
    /**
     * Put a coupon on the customer's cart (a guest gets 422 `cart_login_required`; a code that
     * can't be used gets its coupon error code and is not saved).
     */
    public function update(ApplyCartCouponRequest $request, ResolveCartAction $resolve, ApplyCartCouponAction $apply, SummarizeCartAction $summarize, #[CurrentUser] ?User $customer): CartResource
    {
        $cart = $resolve->handle($customer, $request->header(Cart::TOKEN_HEADER), open: $customer !== null)->cart;
        $apply->handle($cart, $request->couponCode());

        return new CartResource($summarize->handle($cart));
    }

    public function destroy(Request $request, ResolveCartAction $resolve, RemoveCartCouponAction $remove, SummarizeCartAction $summarize, #[CurrentUser] ?User $customer): CartResource
    {
        $cart = $resolve->handle($customer, $request->header(Cart::TOKEN_HEADER))->cart;
        $remove->handle($cart);

        return new CartResource($summarize->handle($cart));
    }
}

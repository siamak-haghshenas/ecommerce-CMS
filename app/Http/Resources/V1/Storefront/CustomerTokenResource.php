<?php

declare(strict_types=1);

namespace App\Http\Resources\V1\Storefront;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Sanctum\NewAccessToken;

/**
 * Returned by every customer login: the Bearer token the storefront sends as
 * "Authorization: Bearer <token>", plus the customer.
 */
class CustomerTokenResource extends JsonResource
{
    public function __construct(private readonly User $customer, private readonly NewAccessToken $token)
    {
        parent::__construct($customer);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $this->token->accessToken->expires_at?->toIso8601String(),
            'customer' => new UserResource($this->customer),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\SaleEvents;

use App\Data\SaleEvents\SaleEventPreview;
use App\Models\SaleEvent;
use App\Models\User;
use App\Support\PreviewToken;
use App\Support\Settings;

final readonly class PreviewSaleEventAction
{
    public function __construct(private Settings $settings) {}

    /**
     * A token that shows the event's landing page whatever its state (switched off, disabled,
     * scheduled), and the storefront address that shows it.
     */
    public function handle(User $actor, SaleEvent $saleEvent): SaleEventPreview
    {
        $expiresAt = now()->toImmutable()->addMinutes(PreviewToken::LIFETIME_MINUTES);
        $token = PreviewToken::create(PreviewToken::SALE_EVENT, $saleEvent->id, $actor->id, $expiresAt);
        $template = $this->settings->storefrontPreviewUrl();

        return new SaleEventPreview(
            token: $token,
            url: $template === null ? null : str_replace('{token}', rawurlencode($token), $template),
            expiresAt: $expiresAt,
        );
    }
}

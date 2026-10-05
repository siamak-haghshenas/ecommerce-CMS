<?php

declare(strict_types=1);

namespace App\Actions\Pages;

use App\Data\Pages\PagePreview;
use App\Models\Page;
use App\Models\User;
use App\Support\PreviewToken;
use App\Support\Settings;

final readonly class PreviewPageAction
{
    public function __construct(private Settings $settings) {}

    /**
     * A preview token for the staff member, and the storefront address that shows it.
     */
    public function handle(User $actor, Page $page): PagePreview
    {
        $expiresAt = now()->toImmutable()->addMinutes(PreviewToken::LIFETIME_MINUTES);
        $token = PreviewToken::create(PreviewToken::PAGE, $page->id, $actor->id, $expiresAt);
        $template = $this->settings->storefrontPreviewUrl();

        return new PagePreview(
            token: $token,
            url: $template === null ? null : str_replace('{token}', rawurlencode($token), $template),
            expiresAt: $expiresAt,
        );
    }
}

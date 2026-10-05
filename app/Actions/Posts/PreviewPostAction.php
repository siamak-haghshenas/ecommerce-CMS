<?php

declare(strict_types=1);

namespace App\Actions\Posts;

use App\Data\Posts\PostPreview;
use App\Models\Post;
use App\Models\User;
use App\Support\PreviewToken;
use App\Support\Settings;

final readonly class PreviewPostAction
{
    public function __construct(private Settings $settings) {}

    /**
     * A preview token for the staff member, and the storefront address that shows it.
     */
    public function handle(User $actor, Post $post): PostPreview
    {
        $expiresAt = now()->toImmutable()->addMinutes(PreviewToken::LIFETIME_MINUTES);
        $token = PreviewToken::create(PreviewToken::POST, $post->id, $actor->id, $expiresAt);
        $template = $this->settings->storefrontPreviewUrl();

        return new PostPreview(
            token: $token,
            url: $template === null ? null : str_replace('{token}', rawurlencode($token), $template),
            expiresAt: $expiresAt,
        );
    }
}

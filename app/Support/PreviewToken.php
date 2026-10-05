<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * A token that lets a storefront show one page as a staff member sees it in the editor (drafts,
 * scheduled pages, unsaved changes). It carries the page, the staff member and an expiry, signed
 * with the app key; nothing is stored, so a token can't be revoked before it expires.
 */
final class PreviewToken
{
    public const int LIFETIME_MINUTES = 60;

    public static function create(int $pageId, int $userId, CarbonImmutable $expiresAt): string
    {
        $payload = self::encode((string) json_encode(['p' => $pageId, 'u' => $userId, 'e' => $expiresAt->getTimestamp()]));

        return $payload.'.'.self::encode(self::signature($payload));
    }

    /**
     * The token's page and staff member, or null when it is forged, malformed or expired.
     *
     * @return array{page_id: int, user_id: int}|null
     */
    public static function read(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 2 || ! hash_equals(self::encode(self::signature($parts[0])), $parts[1])) {
            return null;
        }

        $claims = json_decode((string) base64_decode(strtr($parts[0], '-_', '+/'), true), true);

        if (! is_array($claims) || ! is_int($claims['p'] ?? null) || ! is_int($claims['u'] ?? null) || ! is_int($claims['e'] ?? null)) {
            return null;
        }

        return $claims['e'] < now()->getTimestamp() ? null : ['page_id' => $claims['p'], 'user_id' => $claims['u']];
    }

    private static function signature(string $payload): string
    {
        return hash_hmac('sha256', 'page-preview|'.$payload, (string) config('app.key'), true);
    }

    private static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * A token that lets a storefront show one page or post as a staff member sees it in the editor
 * (drafts, scheduled items, unsaved changes). It carries what it shows (`page` or `post` and the
 * id), the staff member and an expiry, signed with the app key; nothing is stored, so a token
 * can't be revoked before it expires.
 */
final class PreviewToken
{
    public const int LIFETIME_MINUTES = 60;

    public const string PAGE = 'page';

    public const string POST = 'post';

    public static function create(string $kind, int $id, int $userId, CarbonImmutable $expiresAt): string
    {
        $payload = self::encode((string) json_encode(['k' => $kind, 'p' => $id, 'u' => $userId, 'e' => $expiresAt->getTimestamp()]));

        return $payload.'.'.self::encode(self::signature($payload));
    }

    /**
     * The token's item and staff member, or null when it is forged, malformed, expired or for
     * another kind of item.
     *
     * @return array{id: int, user_id: int}|null
     */
    public static function read(string $token, string $kind): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 2 || ! hash_equals(self::encode(self::signature($parts[0])), $parts[1])) {
            return null;
        }

        $claims = json_decode((string) base64_decode(strtr($parts[0], '-_', '+/'), true), true);

        if (! is_array($claims) || ! is_int($claims['p'] ?? null) || ! is_int($claims['u'] ?? null) || ! is_int($claims['e'] ?? null)) {
            return null;
        }

        // Tokens made before posts existed carry no kind: they are page tokens.
        if (($claims['k'] ?? self::PAGE) !== $kind || $claims['e'] < now()->getTimestamp()) {
            return null;
        }

        return ['id' => $claims['p'], 'user_id' => $claims['u']];
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

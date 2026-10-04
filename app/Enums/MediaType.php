<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The kinds of files the media library accepts (config/media.php lists their content types and sizes).
 */
enum MediaType: string
{
    case Image = 'image';
    case Document = 'document';
    case Video = 'video';

    /**
     * The type a content type belongs to, or null when the library doesn't accept it.
     */
    public static function fromMimeType(string $mimeType): ?self
    {
        foreach (self::cases() as $type) {
            if (in_array($mimeType, $type->mimeTypes(), true)) {
                return $type;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function mimeTypes(): array
    {
        return array_values((array) config("media.types.{$this->value}.mime_types", []));
    }

    public function maxBytes(): int
    {
        return (int) config("media.types.{$this->value}.max_kb", 0) * 1024;
    }
}

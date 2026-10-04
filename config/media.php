<?php

declare(strict_types=1);

/**
 * The media library: which files it accepts and how they are handled.
 */
return [

    /*
     * Accepted files per type: real content types (checked on the server, not the file name) and
     * the maximum size in kilobytes. SVG is not accepted: it can carry scripts.
     */
    'types' => [
        'image' => [
            'mime_types' => ['image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/gif'],
            'max_kb' => (int) env('MEDIA_MAX_IMAGE_KB', 10 * 1024),
        ],
        'document' => [
            'mime_types' => ['application/pdf'],
            'max_kb' => (int) env('MEDIA_MAX_DOCUMENT_KB', 20 * 1024),
        ],
        'video' => [
            'mime_types' => ['video/mp4', 'video/webm'],
            'max_kb' => (int) env('MEDIA_MAX_VIDEO_KB', 200 * 1024),
        ],
    ],

    /*
     * Files are uploaded in chunks of this size, so no request reaches PHP's upload limits.
     */
    'chunk_kb' => (int) env('MEDIA_CHUNK_KB', 1024),

    /*
     * Images larger than this (longest side, pixels) are scaled down on upload. Images are also
     * re-saved, which removes location (GPS) and camera details. Animated GIFs are kept as they are.
     */
    'max_image_dimension' => (int) env('MEDIA_MAX_IMAGE_DIMENSION', 2560),

    /*
     * Deleted files stay in the trash this many days before they are removed for good.
     */
    'trash_days' => (int) env('MEDIA_TRASH_DAYS', 30),

    /*
     * Unfinished uploads are removed after this many hours.
     */
    'upload_expiry_hours' => 24,

];

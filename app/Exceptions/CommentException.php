<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * A comment or a moderation step refused by a rule, rendered with a machine-readable code.
 */
final class CommentException extends Exception implements ShouldntReport
{
    private function __construct(
        public readonly string $errorCode,
        public readonly int $status = Response::HTTP_CONFLICT,
    ) {
        parent::__construct($errorCode);
    }

    /**
     * The post doesn't take comments (switched off in the post editor).
     */
    public static function closed(): self
    {
        return new self('comments_closed');
    }

    /**
     * The comment already has the status asked for (and, when rejected, the same reason).
     */
    public static function statusUnchanged(): self
    {
        return new self('comment_status_unchanged');
    }

    /**
     * Nobody moderates their own comment.
     */
    public static function ownComment(): self
    {
        return new self('comment_own', Response::HTTP_FORBIDDEN);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => __("comments.errors.{$this->errorCode}"),
            'code' => $this->errorCode,
        ], $this->status);
    }
}

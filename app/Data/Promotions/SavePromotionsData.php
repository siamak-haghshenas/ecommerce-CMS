<?php

declare(strict_types=1);

namespace App\Data\Promotions;

/**
 * One save of the promotions page: new and changed rows, and the ids of rows removed.
 */
final readonly class SavePromotionsData
{
    /**
     * @param  list<PromotionRowData>  $rows
     * @param  list<int>  $deletedIds
     */
    public function __construct(
        public array $rows,
        public array $deletedIds,
    ) {}
}

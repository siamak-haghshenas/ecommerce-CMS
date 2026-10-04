<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Seo\ScoreSeoAction;
use App\Enums\SeoContentType;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('seo:score {--type= : Only this content type (category, brand, product, page)}')]
#[Description('Recalculate the stored SEO and readability scores (e.g. after changing the rules)')]
class ScoreSeoCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ScoreSeoAction $scoreSeo): int
    {
        $option = $this->option('type');
        $types = $option === null ? SeoContentType::cases() : array_filter([SeoContentType::tryFrom((string) $option)]);

        if ($types === []) {
            $this->components->error("Unknown type [{$option}].");

            return self::FAILURE;
        }

        foreach ($types as $type) {
            $count = 0;

            $type->modelClass()::query()->lazyById()->each(function ($item) use ($scoreSeo, &$count): void {
                $scoreSeo->score($item);
                $count++;
            });

            $this->components->info("Scored {$count} {$type->value} item(s).");
        }

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Pricing\RefreshPricesAction;
use App\Models\ProductVariant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('prices:refresh {--due : Only products whose sale or sale event starts or ends now}')]
#[Description('Store the current price of product variants (sale prices and sale events).')]
class RefreshPricesCommand extends Command
{
    public function handle(RefreshPricesAction $refresh): int
    {
        $productIds = $this->option('due')
            ? ProductVariant::query()->priceDue()->distinct()->pluck('product_id')->map(fn (mixed $id): int => (int) $id)->all()
            : null;
        $changed = $refresh->handle($productIds);

        $this->components->info("Updated {$changed} variant price(s).");

        return self::SUCCESS;
    }
}

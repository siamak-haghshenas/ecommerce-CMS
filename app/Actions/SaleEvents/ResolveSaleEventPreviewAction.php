<?php

declare(strict_types=1);

namespace App\Actions\SaleEvents;

use App\Models\SaleEvent;
use App\Support\PreviewToken;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class ResolveSaleEventPreviewAction
{
    /**
     * The event a token shows, whatever its state (landing page switched off, disabled, scheduled).
     *
     * @throws ModelNotFoundException<SaleEvent> for invalid or expired tokens
     */
    public function handle(string $token): SaleEvent
    {
        $claims = PreviewToken::read($token, PreviewToken::SALE_EVENT) ?? throw (new ModelNotFoundException)->setModel(SaleEvent::class);

        return SaleEvent::query()->with(['bannerAssets.media', 'ogImageAssets.media'])->findOrFail($claims['id']);
    }
}

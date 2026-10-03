<?php

declare(strict_types=1);

namespace App\Actions\Seo;

use App\Contracts\SeoSubject;
use App\Support\Seo\SeoAnalyzer;
use App\Support\Seo\SeoText;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Stores the SEO and readability scores the admin panel showed live, using the same rules.
 * Works for every content type implementing SeoSubject (categories, brands, ...).
 */
final readonly class ScoreSeoAction
{
    public function __construct(private SeoAnalyzer $analyzer) {}

    /**
     * Score the item, plus the other items of its type sharing its current or previous focus
     * keyphrase: their duplicate-keyphrase check depends on it.
     */
    public function handle(Model&SeoSubject $item, ?string $previousKeyphrase = null): void
    {
        $this->score($item);

        foreach (array_filter([$item->getAttribute('focus_keyphrase'), $previousKeyphrase]) as $keyphrase) {
            $this->scoreSharing($item, (string) $keyphrase, (int) $item->getKey());
        }
    }

    /**
     * Re-score the items of the same type as $like using this keyphrase (e.g. after $like was deleted).
     */
    public function scoreSharing(Model&SeoSubject $like, string $keyphrase, ?int $exceptId = null): void
    {
        foreach ($this->usingKeyphrase($like, $keyphrase, $exceptId) as $item) {
            $this->score($item);
        }
    }

    public function score(Model&SeoSubject $item): void
    {
        $item->loadMissing('media');

        $keyphrase = (string) $item->getAttribute('focus_keyphrase');
        $usedBy = $keyphrase === ''
            ? []
            : $this->usingKeyphrase($item, $keyphrase, (int) $item->getKey())
                ->map(fn (Model $other): string => (string) $other->getAttribute('name'))
                ->values()
                ->all();

        $input = $item->seoInput($usedBy);

        $item->forceFill([
            'seo_score' => $this->analyzer->analyzeSeo($input)->score,
            'readability_score' => $this->analyzer->analyzeReadability($input->description)->score,
        ]);

        // Saving (not saveQuietly) keeps model events running, e.g. the category tree cache refresh.
        if ($item->isDirty()) {
            $item->save();
        }
    }

    /**
     * Keyphrases are compared normalized, so «كفش» typed on an Arabic keyboard equals «کفش».
     *
     * @return Collection<int, Model&SeoSubject>
     */
    private function usingKeyphrase(Model&SeoSubject $like, string $keyphrase, ?int $exceptId): Collection
    {
        /** @var Collection<int, Model&SeoSubject> $items */
        $items = $like->newQuery()
            ->whereNotNull('focus_keyphrase')
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->orderBy($like->getKeyName())
            ->get()
            ->toBase();

        return $items
            ->filter(fn (Model $other): bool => SeoText::sameKeyphrase($keyphrase, (string) $other->getAttribute('focus_keyphrase')))
            ->values();
    }
}

<?php

namespace App\Services;

use App\Models\Question;
use Illuminate\Support\Facades\File;

class OfficialSourceService
{
    /** @var array<string, array{label: string, url: string}>|null */
    private ?array $catalog = null;

    /** @return list<array{label: string, url: string}> */
    public function forQuestion(Question $question): array
    {
        $byUrl = collect($this->catalog())->keyBy('url');

        return array_values(collect($question->source_urls ?? [])
            ->unique()
            ->map(fn (string $url): ?array => $byUrl->get($url))
            ->filter()
            ->values()
            ->all());
    }

    public function isOfficialUrl(string $url): bool
    {
        return parse_url($url, PHP_URL_SCHEME) === 'https'
            && collect($this->catalog())->contains(
                fn (array $source): bool => $source['url'] === $url,
            );
    }

    /** @return array<string, array{label: string, url: string}> */
    private function catalog(): array
    {
        return $this->catalog ??= File::json(database_path('seeders/data/official-sources.json'));
    }
}

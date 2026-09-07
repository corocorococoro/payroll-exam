<?php

namespace App\Models;

use App\Services\QuestionDependencyReviewService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property int $fiscal_year
 * @property array<string, mixed> $content
 * @property int $sort_order
 */
#[Fillable(['slug', 'name', 'fiscal_year', 'content', 'sort_order'])]
class ReferenceSheet extends Model
{
    protected static function booted(): void
    {
        static::updated(function (ReferenceSheet $sheet): void {
            if ($sheet->wasChanged(['content', 'name', 'slug', 'fiscal_year'])) {
                app(QuestionDependencyReviewService::class)->invalidate(Question::query()
                    ->whereIn('fiscal_year', [$sheet->fiscal_year, $sheet->getOriginal('fiscal_year')])
                    ->where(fn ($query) => $query
                        ->whereJsonContains('reference_sheet_slugs', $sheet->slug)
                        ->orWhereJsonContains('reference_sheet_slugs', $sheet->getOriginal('slug'))));
            }
        });
        static::deleted(function (ReferenceSheet $sheet): void {
            app(QuestionDependencyReviewService::class)->invalidate(Question::query()
                ->where('fiscal_year', $sheet->fiscal_year)->whereJsonContains('reference_sheet_slugs', $sheet->slug));
        });
    }

    protected function casts(): array
    {
        return [
            'content' => 'array',
        ];
    }
}

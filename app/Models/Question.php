<?php

namespace App\Models;

use App\Enums\Difficulty;
use App\Enums\QuestionReviewStatus;
use App\Enums\QuestionType;
use App\Enums\QuestionVariantRole;
use App\Services\QuestionRevisionService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $unit_id
 * @property int|null $lesson_id
 * @property string|null $source_id
 * @property string|null $concept_key
 * @property string|null $learning_objective
 * @property QuestionVariantRole|null $variant_role
 * @property string|null $misconception_key
 * @property QuestionType $type
 * @property string $category
 * @property Difficulty $difficulty
 * @property QuestionReviewStatus $review_status
 * @property string $verification_status
 * @property string $scope_status
 * @property string $exam_role
 * @property string $study_tier
 * @property int $content_revision
 * @property string|null $content_hash
 * @property string|null $reviewed_content_hash
 * @property int $fiscal_year
 * @property string $question_text
 * @property array<int, array{key: string, text: string}>|null $choices
 * @property array<string, mixed> $answer
 * @property string $explanation
 * @property string|null $common_mistake
 * @property array<string, string>|null $distractor_feedback
 * @property array<string, mixed>|null $calc_params
 * @property list<string>|null $reference_sheet_slugs
 * @property list<string>|null $source_urls
 * @property string|null $review_notes
 * @property CarbonImmutable|null $reviewed_at
 * @property CarbonImmutable|null $review_due_at
 * @property bool $is_active
 */
#[Fillable([
    'unit_id', 'lesson_id', 'source_id', 'concept_key', 'learning_objective', 'variant_role',
    'misconception_key', 'type', 'category', 'difficulty',
    'review_status', 'verification_status', 'scope_status', 'exam_role', 'study_tier', 'content_revision', 'content_hash', 'reviewed_content_hash', 'fiscal_year',
    'question_text', 'choices', 'answer', 'explanation', 'common_mistake', 'distractor_feedback',
    'calc_params', 'reference_sheet_slugs', 'source_urls', 'review_notes', 'reviewed_at',
    'review_due_at', 'is_active',
])]
class Question extends Model
{
    protected static function booted(): void
    {
        static::updated(function (Question $question): void {
            if ($question->wasChanged('content_revision')) {
                app(QuestionRevisionService::class)->invalidate($question);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'difficulty' => Difficulty::class,
            'review_status' => QuestionReviewStatus::class,
            'variant_role' => QuestionVariantRole::class,
            'choices' => 'array',
            'answer' => 'array',
            'distractor_feedback' => 'array',
            'calc_params' => 'array',
            'reference_sheet_slugs' => 'array',
            'source_urls' => 'array',
            'reviewed_at' => 'immutable_datetime',
            'review_due_at' => 'immutable_datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<Question>  $query
     * @return Builder<Question>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('review_status', QuestionReviewStatus::Approved->value)
            ->whereNotNull('content_hash')
            ->whereColumn('content_hash', 'reviewed_content_hash')
            ->whereNotNull('concept_key')
            ->whereNotNull('learning_objective')
            ->whereNotNull('variant_role')
            ->whereNotNull('source_urls')
            ->whereNotNull('reviewed_at')
            ->where('review_due_at', '>=', now());
    }

    /**
     * 初見模試のために確保した問題を除く、通常学習の問題バンク。
     *
     * @param  Builder<Question>  $query
     * @return Builder<Question>
     */
    public function scopePracticeBank(Builder $query): Builder
    {
        return $query->published()->whereDoesntHave(
            'mockExamQuestions.mockExam',
            fn (Builder $mockExam): Builder => $mockExam->where('is_published', true),
        );
    }

    /**
     * 模試問題はそのユーザーが一度採点を終えた後だけ補強学習へ解放する。
     *
     * @param  Builder<Question>  $query
     * @return Builder<Question>
     */
    public function scopePracticeAvailableFor(Builder $query, User $user): Builder
    {
        return $query->published()->where(function (Builder $available) use ($user): void {
            $available
                ->whereDoesntHave(
                    'mockExamQuestions.mockExam',
                    fn (Builder $mockExam): Builder => $mockExam->where('is_published', true),
                )
                ->orWhereHas(
                    'progresses',
                    fn (Builder $progress): Builder => $progress->where('user_id', $user->id),
                );
        });
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /** @return HasMany<MockExamQuestion, $this> */
    public function mockExamQuestions(): HasMany
    {
        return $this->hasMany(MockExamQuestion::class);
    }

    /** @return HasMany<UserQuestionProgress, $this> */
    public function progresses(): HasMany
    {
        return $this->hasMany(UserQuestionProgress::class);
    }

    public function isCalculation(): bool
    {
        return $this->exam_role === 'calculation' || $this->calc_params !== null;
    }

    /**
     * 正誤や解説を含む学習内容のハッシュ。メタデータだけの更新では版を上げない。
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function contentHash(array $attributes): string
    {
        $content = [];

        foreach (['type', 'question_text', 'choices', 'answer', 'explanation', 'common_mistake', 'distractor_feedback', 'calc_params'] as $key) {
            $content[$key] = $attributes[$key] ?? null;
        }

        return hash('sha256', json_encode(
            self::canonicalizeForHash($content),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
        ));
    }

    private static function canonicalizeForHash(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(self::canonicalizeForHash(...), $value);
    }

    /**
     * 解答を判定する。choice は選択肢キー、numeric は数値（カンマ・全角許容）。
     */
    public function checkAnswer(string $given): bool
    {
        if ($this->type === QuestionType::Choice) {
            return strtoupper(trim($given)) === strtoupper((string) ($this->answer['choice'] ?? ''));
        }

        $normalized = str_replace([',', '，', '円', ' ', '　'], '', mb_convert_kana(trim($given), 'n'));

        if (! is_numeric($normalized)) {
            return false;
        }

        return abs((float) $normalized - (float) ($this->answer['value'] ?? NAN)) < 0.001;
    }
}

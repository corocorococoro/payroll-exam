<?php

namespace App\Models;

use App\Enums\Difficulty;
use App\Enums\QuestionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $unit_id
 * @property int|null $lesson_id
 * @property string|null $source_id
 * @property QuestionType $type
 * @property string $category
 * @property Difficulty $difficulty
 * @property int $fiscal_year
 * @property string $question_text
 * @property array<int, array{key: string, text: string}>|null $choices
 * @property array<string, mixed> $answer
 * @property string $explanation
 * @property string|null $common_mistake
 * @property array<string, mixed>|null $calc_params
 * @property list<string>|null $reference_sheet_slugs
 * @property bool $is_active
 */
#[Fillable([
    'unit_id', 'lesson_id', 'source_id', 'type', 'category', 'difficulty', 'fiscal_year',
    'question_text', 'choices', 'answer', 'explanation', 'common_mistake',
    'calc_params', 'reference_sheet_slugs', 'is_active',
])]
class Question extends Model
{
    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'difficulty' => Difficulty::class,
            'choices' => 'array',
            'answer' => 'array',
            'calc_params' => 'array',
            'reference_sheet_slugs' => 'array',
            'is_active' => 'boolean',
        ];
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

    public function isCalculation(): bool
    {
        return $this->calc_params !== null;
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

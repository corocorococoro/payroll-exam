<?php

namespace App\Services;

use App\Enums\Difficulty;
use App\Enums\QuestionReviewStatus;
use App\Enums\QuestionType;
use App\Enums\QuestionVariantRole;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;

class QuestionImportService
{
    public function import(string $path, string $extension): int
    {
        $rows = strtolower($extension) === 'json' ? $this->jsonRows($path) : $this->csvRows($path);

        return DB::transaction(function () use ($rows): int {
            $count = 0;
            $normalizedTexts = [];

            foreach ($rows as $index => $row) {
                $data = $this->normalize($row);
                $validated = Validator::make($data, [
                    'unit' => ['required', 'string', 'exists:units,slug'],
                    'lesson' => ['nullable', 'string'],
                    'source_id' => ['required', 'string', 'max:100'],
                    'concept_key' => ['required', 'string', 'max:100'],
                    'learning_objective' => ['required', 'string'],
                    'variant_role' => ['required', Rule::enum(QuestionVariantRole::class)],
                    'misconception_key' => ['nullable', 'string', 'max:100'],
                    'type' => ['required', Rule::enum(QuestionType::class)],
                    'category' => ['required', 'string'],
                    'difficulty' => ['required', Rule::enum(Difficulty::class)],
                    'fiscal_year' => ['required', 'integer'],
                    'question_text' => ['required', 'string'],
                    'choices' => ['nullable', 'array'],
                    'answer' => ['required', 'array'],
                    'explanation' => ['required', 'string'],
                    'distractor_feedback' => ['nullable', 'array'],
                    'source_urls' => ['required', 'array', 'min:1'],
                    'source_urls.*' => ['required', 'url'],
                ])->validate();

                $normalizedText = $this->normalizeQuestionText($validated['question_text']);
                if (isset($normalizedTexts[$normalizedText])) {
                    throw new RuntimeException('行'.($index + 1).": 同一問題文がファイル内にあります（{$normalizedTexts[$normalizedText]}）。");
                }
                $normalizedTexts[$normalizedText] = $validated['source_id'];

                $duplicate = Question::query()
                    ->published()
                    ->where('source_id', '!=', $validated['source_id'])
                    ->get(['source_id', 'question_text'])
                    ->first(fn (Question $question): bool => $this->normalizeQuestionText($question->question_text) === $normalizedText);

                if ($duplicate !== null) {
                    throw new RuntimeException('行'.($index + 1).": 公開問題{$duplicate->source_id}と同一の問題文です。");
                }

                $unit = Unit::where('slug', $validated['unit'])->firstOrFail();
                $lessonId = null;

                if (! empty($validated['lesson'])) {
                    $lessonId = Lesson::where('unit_id', $unit->id)
                        ->where('slug', $validated['lesson'])->value('id');

                    if ($lessonId === null) {
                        throw new RuntimeException('行'.($index + 1).": lesson {$validated['lesson']} が見つかりません。");
                    }
                }

                $content = [
                    'type' => $validated['type'],
                    'question_text' => $validated['question_text'],
                    'choices' => $validated['choices'] ?? null,
                    'answer' => $validated['answer'],
                    'explanation' => $validated['explanation'],
                    'common_mistake' => $data['common_mistake'] ?? null,
                    'distractor_feedback' => $validated['distractor_feedback'] ?? null,
                    'calc_params' => $data['calc_params'] ?? null,
                ];
                $contentHash = Question::contentHash($content);
                $existing = Question::where('source_id', $validated['source_id'])->first();
                $contentChanged = $existing !== null && $existing->content_hash !== $contentHash;

                Question::updateOrCreate(['source_id' => $validated['source_id']], [
                    'unit_id' => $unit->id,
                    'lesson_id' => $lessonId,
                    'concept_key' => $validated['concept_key'],
                    'learning_objective' => $validated['learning_objective'],
                    'variant_role' => $validated['variant_role'],
                    'misconception_key' => $validated['misconception_key'] ?? null,
                    'type' => $content['type'],
                    'category' => $validated['category'],
                    'difficulty' => $validated['difficulty'],
                    'review_status' => $existing === null
                        ? QuestionReviewStatus::Draft
                        : ($contentChanged ? QuestionReviewStatus::InReview : $existing->review_status),
                    'content_revision' => $existing === null
                        ? 1
                        : $existing->content_revision + ($contentChanged ? 1 : 0),
                    'content_hash' => $contentHash,
                    'reviewed_content_hash' => $existing?->reviewed_content_hash,
                    'fiscal_year' => $validated['fiscal_year'],
                    'question_text' => $content['question_text'],
                    'choices' => $content['choices'],
                    'answer' => $content['answer'],
                    'explanation' => $content['explanation'],
                    'common_mistake' => $content['common_mistake'],
                    'distractor_feedback' => $content['distractor_feedback'],
                    'calc_params' => $content['calc_params'],
                    'reference_sheet_slugs' => $data['reference_sheet_slugs'] ?? [],
                    'source_urls' => $validated['source_urls'],
                    'review_notes' => $data['review_notes'] ?? $existing?->review_notes,
                    'reviewed_at' => $contentChanged ? null : $existing?->reviewed_at,
                    'review_due_at' => $contentChanged ? null : $existing?->review_due_at,
                    'is_active' => $contentChanged || $existing === null ? false : $existing->is_active,
                ]);
                $count++;
            }

            return $count;
        });
    }

    /** @return list<array<string, mixed>> */
    private function jsonRows(string $path): array
    {
        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($data) || ! array_is_list($data)) {
            throw new RuntimeException('JSONは問題オブジェクトの配列にしてください。');
        }

        return $data;
    }

    /** @return list<array<string, mixed>> */
    private function csvRows(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException('CSVを開けません。');
        }

        $headers = fgetcsv($handle);
        if ($headers === false) {
            throw new RuntimeException('CSVヘッダーがありません。');
        }
        $headers = array_map(fn ($header): string => (string) $header, $headers);

        $rows = [];
        while (($values = fgetcsv($handle)) !== false) {
            $row = array_combine($headers, array_slice(array_pad($values, count($headers), null), 0, count($headers)));
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalize(array $row): array
    {
        foreach (['choices', 'answer', 'distractor_feedback', 'calc_params', 'reference_sheet_slugs'] as $key) {
            $csvKey = $key.'_json';
            if (isset($row[$csvKey]) && is_string($row[$csvKey]) && $row[$csvKey] !== '') {
                $row[$key] = json_decode($row[$csvKey], true, 512, JSON_THROW_ON_ERROR);
            }
        }

        $row['fiscal_year'] = (int) ($row['fiscal_year'] ?? 2026);
        $row['is_active'] = filter_var($row['is_active'] ?? true, FILTER_VALIDATE_BOOL);

        return $row;
    }

    private function normalizeQuestionText(string $text): string
    {
        $normalized = mb_strtolower(mb_convert_kana($text, 'asKV'));

        return preg_replace('/[\s　、。,.・「」『』（）()！？!?:：;；]/u', '', $normalized) ?? $normalized;
    }
}

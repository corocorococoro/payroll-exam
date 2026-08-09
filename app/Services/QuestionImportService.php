<?php

namespace App\Services;

use App\Enums\Difficulty;
use App\Enums\QuestionType;
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

            foreach ($rows as $index => $row) {
                $data = $this->normalize($row);
                $validated = Validator::make($data, [
                    'unit' => ['required', 'string', 'exists:units,slug'],
                    'lesson' => ['nullable', 'string'],
                    'source_id' => ['required', 'string', 'max:100'],
                    'type' => ['required', Rule::enum(QuestionType::class)],
                    'category' => ['required', 'string'],
                    'difficulty' => ['required', Rule::enum(Difficulty::class)],
                    'fiscal_year' => ['required', 'integer'],
                    'question_text' => ['required', 'string'],
                    'choices' => ['nullable', 'array'],
                    'answer' => ['required', 'array'],
                    'explanation' => ['required', 'string'],
                ])->validate();

                $unit = Unit::where('slug', $validated['unit'])->firstOrFail();
                $lessonId = null;

                if (! empty($validated['lesson'])) {
                    $lessonId = Lesson::where('unit_id', $unit->id)
                        ->where('slug', $validated['lesson'])->value('id');

                    if ($lessonId === null) {
                        throw new RuntimeException('行'.($index + 1).": lesson {$validated['lesson']} が見つかりません。");
                    }
                }

                Question::updateOrCreate(['source_id' => $validated['source_id']], [
                    'unit_id' => $unit->id,
                    'lesson_id' => $lessonId,
                    'type' => $validated['type'],
                    'category' => $validated['category'],
                    'difficulty' => $validated['difficulty'],
                    'fiscal_year' => $validated['fiscal_year'],
                    'question_text' => $validated['question_text'],
                    'choices' => $validated['choices'] ?? null,
                    'answer' => $validated['answer'],
                    'explanation' => $validated['explanation'],
                    'common_mistake' => $data['common_mistake'] ?? null,
                    'calc_params' => $data['calc_params'] ?? null,
                    'reference_sheet_slugs' => $data['reference_sheet_slugs'] ?? [],
                    'is_active' => $data['is_active'] ?? true,
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
        foreach (['choices', 'answer', 'calc_params', 'reference_sheet_slugs'] as $key) {
            $csvKey = $key.'_json';
            if (isset($row[$csvKey]) && is_string($row[$csvKey]) && $row[$csvKey] !== '') {
                $row[$key] = json_decode($row[$csvKey], true, 512, JSON_THROW_ON_ERROR);
            }
        }

        $row['fiscal_year'] = (int) ($row['fiscal_year'] ?? 2026);
        $row['is_active'] = filter_var($row['is_active'] ?? true, FILTER_VALIDATE_BOOL);

        return $row;
    }
}

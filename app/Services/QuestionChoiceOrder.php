<?php

namespace App\Services;

use App\Enums\QuestionType;
use Illuminate\Support\Facades\File;
use RuntimeException;

class QuestionChoiceOrder
{
    /**
     * 正解位置の偏りから答えを推測できないよう、選択肢を決定的に再配置する。
     *
     * @param  array<string, mixed>  $question
     * @return array<string, mixed>
     */
    public function normalizeChoiceOrder(array $question, ?string $targetCorrectKey): array
    {
        if ($question['type'] !== QuestionType::Choice->value) {
            return $question;
        }

        $keys = ['A', 'B', 'C', 'D'];
        if (! in_array($targetCorrectKey, $keys, true)) {
            throw new RuntimeException("Question {$question['id']}: correct-choice target is missing");
        }
        $originalCorrectKey = $question['answer']['choice'];
        /** @var list<array{key: string, text: string}> $choices */
        $choices = $question['choices'];
        $correctChoice = null;
        $distractors = [];

        foreach ($choices as $choice) {
            if ($choice['key'] === $originalCorrectKey) {
                $correctChoice = $choice;
            } else {
                $distractors[] = $choice;
            }
        }

        if ($correctChoice === null) {
            throw new RuntimeException("Question {$question['id']}: correct choice not found");
        }
        $oldToNewKeys = [];
        $reordered = [];
        $distractorIndex = 0;

        foreach ($keys as $newKey) {
            $choice = $newKey === $targetCorrectKey
                ? $correctChoice
                : $distractors[$distractorIndex++];
            $oldToNewKeys[$choice['key']] = $newKey;
            $choice['key'] = $newKey;
            $reordered[] = $choice;
        }

        $feedback = [];
        /** @var array<string, string> $sourceFeedback */
        $sourceFeedback = $question['distractor_feedback'] ?? [];
        foreach ($sourceFeedback as $oldKey => $message) {
            $feedback[$oldToNewKeys[$oldKey]] = $message;
        }
        ksort($feedback);

        $question['choices'] = $reordered;
        $question['answer']['choice'] = $targetCorrectKey;
        $question['distractor_feedback'] = $feedback;

        return $question;
    }

    /**
     * 公開模試は各正解位置を10問ずつにし、残りを現在の最少位置へ配って
     * 問題バンク全体も均等にする。正本IDと固定シードから再現可能に決定する。
     *
     * @param  list<array<string, mixed>>  $questions
     * @return array<string, string>
     */
    public function choiceTargets(array $questions): array
    {
        $keys = ['A', 'B', 'C', 'D'];
        $targets = [];
        $counts = array_fill_keys($keys, 0);

        foreach (File::json(database_path('seeders/data/mock-exams.json')) as $exam) {
            /** @var list<array{question_id: string, position: int}> $items */
            $items = $exam['questions'];
            $shuffled = collect($items)
                ->sortBy(fn (array $item): string => hash('sha256', 'choice-order-v2|'.$exam['slug'].'|'.$item['question_id']))
                ->values();
            foreach ($shuffled as $index => $item) {
                $questionId = (string) $item['question_id'];
                $target = match ($index % count($keys)) {
                    0 => 'A',
                    1 => 'B',
                    2 => 'C',
                    3 => 'D',
                    default => throw new RuntimeException("Mock exam position must be positive: {$item['position']}"),
                };

                if (isset($targets[$questionId]) && $targets[$questionId] !== $target) {
                    throw new RuntimeException("Question {$questionId}: 模試間で正解位置が競合しています。");
                }

                if (! isset($targets[$questionId])) {
                    $targets[$questionId] = $target;
                    $counts[$target]++;
                }
            }
        }

        foreach ($questions as $question) {
            $questionId = (string) $question['id'];
            if ($question['type'] !== QuestionType::Choice->value || isset($targets[$questionId])) {
                continue;
            }

            $minimum = min($counts);
            $leastUsed = array_values(array_filter(
                $keys,
                fn (string $key): bool => $counts[$key] === $minimum,
            ));
            $target = $leastUsed[0] ?? throw new RuntimeException('正解位置の割当てに失敗しました。');
            $targets[$questionId] = $target;
            $counts[$target]++;
        }

        return $targets;
    }
}

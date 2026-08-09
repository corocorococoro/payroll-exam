<?php

namespace App\Services;

use App\Enums\AttemptContext;
use App\Models\MockExamAttempt;
use App\Models\QuestionAttempt;
use Illuminate\Support\Facades\DB;

class MockExamService
{
    /**
     * @return array{score: int, passed: bool, section_scores: array<string, array{correct: int, total: int, earned: int, max: int, accuracy: int}>}
     */
    public function finish(MockExamAttempt $attempt): array
    {
        return DB::transaction(function () use ($attempt): array {
            $attempt = MockExamAttempt::query()
                ->lockForUpdate()
                ->findOrFail($attempt->id);
            $attempt->load('mockExam.examQuestions.question');

            if ($attempt->finished_at !== null) {
                return [
                    'score' => $attempt->score ?? 0,
                    'passed' => ($attempt->score ?? 0) >= $attempt->mockExam->passing_score,
                    'section_scores' => $attempt->section_scores ?? [],
                ];
            }

            $answers = $attempt->answers ?? [];
            $score = 0;
            /** @var array<string, array{correct: int, total: int, earned: int, max: int, accuracy: int}> $sections */
            $sections = [];

            foreach ($attempt->mockExam->examQuestions as $examQuestion) {
                $question = $examQuestion->question;
                $given = $answers[$question->id] ?? null;
                $correct = $given !== null && $question->checkAnswer($given);

                if ($correct) {
                    $score += $examQuestion->points;
                }

                $category = $question->category;
                $sections[$category] ??= ['correct' => 0, 'total' => 0, 'earned' => 0, 'max' => 0, 'accuracy' => 0];
                $sections[$category]['total']++;
                $sections[$category]['max'] += $examQuestion->points;

                if ($correct) {
                    $sections[$category]['correct']++;
                    $sections[$category]['earned'] += $examQuestion->points;
                }

                if ($given !== null) {
                    QuestionAttempt::create([
                        'user_id' => $attempt->user_id,
                        'question_id' => $question->id,
                        'lesson_id' => null,
                        'context' => AttemptContext::Mock,
                        'is_correct' => $correct,
                        'given_answer' => ['given' => $given],
                        'xp_earned' => 0,
                    ]);
                }
            }

            foreach ($sections as &$section) {
                $section['accuracy'] = (int) round($section['correct'] / $section['total'] * 100);
            }
            unset($section);

            $attempt->update([
                'finished_at' => now(),
                'score' => $score,
                'section_scores' => $sections,
            ]);

            app(AchievementService::class)->evaluate($attempt->user);

            return [
                'score' => $score,
                'passed' => $score >= $attempt->mockExam->passing_score,
                'section_scores' => $sections,
            ];
        });
    }
}

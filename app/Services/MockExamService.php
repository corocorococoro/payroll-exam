<?php

namespace App\Services;

use App\Enums\AttemptContext;
use App\Models\MockExamAttempt;
use App\Models\Question;
use App\Models\QuestionAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MockExamService
{
    public function __construct(private readonly MockExamSnapshotService $snapshots) {}

    /**
     * @return array{score: int, passed: bool, section_scores: array<string, array{correct: int, total: int, earned: int, max: int, accuracy: int}>, unit_scores: array<string, array{name: string, correct: int, total: int, earned: int, max: int, accuracy: int}>, knowledge_score: int, calculation_score: int}
     */
    public function finish(MockExamAttempt $attempt): array
    {
        return DB::transaction(function () use ($attempt): array {
            $attempt = MockExamAttempt::query()
                ->lockForUpdate()
                ->findOrFail($attempt->id);
            $attempt->load('mockExam');

            if ($attempt->finished_at !== null) {
                return [
                    'score' => $attempt->score ?? 0,
                    'passed' => ($attempt->score ?? 0) >= $attempt->mockExam->passing_score,
                    'section_scores' => $attempt->section_scores ?? [],
                    'unit_scores' => $attempt->unit_scores ?? [],
                    'knowledge_score' => $attempt->knowledge_score ?? 0,
                    'calculation_score' => $attempt->calculation_score ?? 0,
                ];
            }

            $answers = $attempt->answers ?? [];
            $snapshot = $attempt->review_snapshot ?? $this->snapshots->build($attempt->mockExam);
            $gradedSnapshot = $this->snapshots->grade($snapshot, $answers);
            $questions = Question::query()
                ->whereKey(collect($gradedSnapshot)->pluck('question_id'))
                ->get()
                ->keyBy('id');
            $user = User::query()->findOrFail($attempt->user_id);
            $score = 0;
            /** @var array<string, array{correct: int, total: int, earned: int, max: int, accuracy: int}> $sections */
            $sections = [];
            /** @var array<string, array{name: string, correct: int, total: int, earned: int, max: int, accuracy: int}> $units */
            $units = [];
            $knowledgeScore = 0;
            $calculationScore = 0;

            foreach ($gradedSnapshot as $item) {
                $question = $questions->get((int) $item['question_id']);
                if (! $question instanceof Question) {
                    throw new \RuntimeException("Mock snapshot question {$item['question_id']} is missing.");
                }

                $given = $item['given_answer'];
                $correct = (bool) $item['correct'];
                $points = (int) $item['points'];
                $position = (int) $item['position'];

                if ($correct) {
                    $score += $points;
                    if ($position <= 35) {
                        $knowledgeScore += $points;
                    } else {
                        $calculationScore += $points;
                    }
                }

                $category = (string) $item['category'];
                $sections[$category] ??= ['correct' => 0, 'total' => 0, 'earned' => 0, 'max' => 0, 'accuracy' => 0];
                $sections[$category]['total']++;
                $sections[$category]['max'] += $points;

                if ($correct) {
                    $sections[$category]['correct']++;
                    $sections[$category]['earned'] += $points;
                }

                $unitSlug = (string) $item['unit_slug'];
                $units[$unitSlug] ??= [
                    'name' => (string) $item['unit_name'],
                    'correct' => 0,
                    'total' => 0,
                    'earned' => 0,
                    'max' => 0,
                    'accuracy' => 0,
                ];
                $units[$unitSlug]['total']++;
                $units[$unitSlug]['max'] += $points;

                if ($correct) {
                    $units[$unitSlug]['correct']++;
                    $units[$unitSlug]['earned'] += $points;
                }

                if (is_string($given)) {
                    QuestionAttempt::create([
                        'user_id' => $attempt->user_id,
                        'question_id' => $question->id,
                        'content_revision' => (int) $item['content_revision'],
                        'lesson_id' => null,
                        'context' => AttemptContext::Mock,
                        'is_correct' => $correct,
                        'given_answer' => ['given' => $given],
                        'xp_earned' => 0,
                    ]);

                }

                // 無回答も含め、模試で判明した弱点をそのまま復習キューへつなぐ。
                if ($question->content_revision === (int) $item['content_revision']) {
                    app(AnswerService::class)->updateLearningProgress(
                        $user,
                        $question,
                        $correct,
                        AttemptContext::Mock,
                        is_string($given),
                    );
                }
            }

            foreach ($sections as &$section) {
                $section['accuracy'] = (int) round($section['correct'] / $section['total'] * 100);
            }
            unset($section);

            foreach ($units as &$unit) {
                $unit['accuracy'] = (int) round($unit['earned'] / $unit['max'] * 100);
            }
            unset($unit);

            $attempt->update([
                'finished_at' => now(),
                'score' => $score,
                'section_scores' => $sections,
                'unit_scores' => $units,
                'knowledge_score' => $knowledgeScore,
                'calculation_score' => $calculationScore,
                'review_snapshot' => $gradedSnapshot,
            ]);

            app(AchievementService::class)->evaluate($attempt->user);

            return [
                'score' => $score,
                'passed' => $score >= $attempt->mockExam->passing_score,
                'section_scores' => $sections,
                'unit_scores' => $units,
                'knowledge_score' => $knowledgeScore,
                'calculation_score' => $calculationScore,
            ];
        });
    }
}

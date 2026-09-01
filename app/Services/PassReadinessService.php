<?php

namespace App\Services;

use App\Enums\AttemptContext;
use App\Models\MockExamAttempt;
use App\Models\User;
use Illuminate\Support\Collection;

class PassReadinessService
{
    /** @var list<string> */
    private const array REQUIRED_UNIT_SLUGS = ['shikyu', 'roudou', 'shaho', 'zei', 'keisan'];

    public const int REQUIRED_FRESH_MOCKS = 2;

    public const int MINIMUM_SCORE = 70;

    public const int MINIMUM_AVERAGE = 80;

    public const int MINIMUM_CALCULATION_SCORE = 18;

    public const int MINIMUM_UNIT_ACCURACY = 60;

    public function __construct(private readonly MockExamSnapshotService $snapshots) {}

    /**
     * 同じ模試の反復で見かけの判定を上げないよう、各模試の初回120分受験だけを使う。
     *
     * @return array{label: string, detail: string, qualifying_mock_count: int, mock_average: int|null, unit_accuracies: array<string, int>}
     */
    public function evaluate(User $user, int $coreSeen, int $coreTotal): array
    {
        /** @var Collection<int, MockExamAttempt> $firstAttempts */
        $firstAttempts = $user->mockExamAttempts()
            ->with('mockExam.examQuestions:id,mock_exam_id,question_id')
            ->whereNotNull('finished_at')
            ->orderBy('started_at')
            ->orderBy('id')
            ->get()
            ->groupBy('mock_exam_id')
            ->map(fn (Collection $attempts): MockExamAttempt => $attempts->first())
            ->values();

        /** @var Collection<int, MockExamAttempt> $freshAttempts */
        $freshAttempts = $firstAttempts
            ->filter(fn (MockExamAttempt $attempt): bool => $attempt->time_limit_minutes === $attempt->mockExam->time_limit_minutes)
            ->filter(fn (MockExamAttempt $attempt): bool => $this->hasCompleteDiagnostics($attempt)
                && $this->hasNoPriorPracticeExposure($user, $attempt))
            ->sortByDesc('finished_at')
            ->take(self::REQUIRED_FRESH_MOCKS)
            ->values();

        $count = $freshAttempts->count();
        $average = $count === 0 ? null : (int) round($freshAttempts->avg('score'));
        $unitTotals = collect(self::REQUIRED_UNIT_SLUGS)
            ->mapWithKeys(fn (string $slug): array => [$slug => ['earned' => 0, 'max' => 0]])
            ->all();

        foreach ($freshAttempts as $attempt) {
            foreach ($attempt->unit_scores ?? [] as $slug => $score) {
                $unitTotals[$slug] ??= ['earned' => 0, 'max' => 0];
                $unitTotals[$slug]['earned'] += (int) ($score['earned'] ?? 0);
                $unitTotals[$slug]['max'] += (int) ($score['max'] ?? 0);
            }
        }

        $unitAccuracies = collect($unitTotals)
            ->map(fn (array $score): int => $score['max'] === 0
                ? 0
                : (int) round($score['earned'] / $score['max'] * 100))
            ->all();

        if ($count < self::REQUIRED_FRESH_MOCKS) {
            if ($count === 1) {
                if (($freshAttempts->first()->score ?? 0) < self::MINIMUM_SCORE) {
                    return $this->result('弱点補強中', '初見模試70点との差を優先して補う', $count, $average, $unitAccuracies);
                }

                return $this->result('実力確認中', '初見120分模試をあと1回受験する', $count, $average, $unitAccuracies);
            }

            $coreCoverage = $coreTotal === 0 ? 0 : $coreSeen / $coreTotal;
            $label = $coreCoverage < 0.6 ? '基礎構築中' : '初見模試で確認';
            $detail = $coreCoverage < 0.6
                ? 'まず合格コアを広く一周する'
                : '初見120分模試を2回受験して実力を測る';

            return $this->result($label, $detail, $count, $average, $unitAccuracies);
        }

        if ($freshAttempts->contains(fn (MockExamAttempt $attempt): bool => ($attempt->score ?? 0) < self::MINIMUM_SCORE)) {
            return $this->result('弱点補強中', '初見模試は各70点以上を目指す', $count, $average, $unitAccuracies);
        }

        if (($average ?? 0) < self::MINIMUM_AVERAGE) {
            return $this->result('弱点補強中', '初見模試2回の平均80点以上を目指す', $count, $average, $unitAccuracies);
        }

        if ($freshAttempts->contains(fn (MockExamAttempt $attempt): bool => ($attempt->calculation_score ?? 0) < self::MINIMUM_CALCULATION_SCORE)) {
            return $this->result('弱点補強中', '各模試の計算問題で18点以上を目指す', $count, $average, $unitAccuracies);
        }

        $weakUnits = collect($unitAccuracies)
            ->filter(fn (int $accuracy): bool => $accuracy < self::MINIMUM_UNIT_ACCURACY)
            ->keys();
        if ($weakUnits->isNotEmpty()) {
            return $this->result('弱点補強中', '全単元で得点率60%以上を目指す', $count, $average, $unitAccuracies);
        }

        return $this->result(
            '合格圏',
            "初見模試2回平均{$average}点。弱点復習で再現性を保つ",
            $count,
            $average,
            $unitAccuracies,
        );
    }

    private function hasCompleteDiagnostics(MockExamAttempt $attempt): bool
    {
        if ($attempt->score === null || $attempt->knowledge_score === null || $attempt->calculation_score === null) {
            return false;
        }

        if ($attempt->score < 0 || $attempt->score > 100
            || $attempt->knowledge_score < 0 || $attempt->knowledge_score > 70
            || $attempt->calculation_score < 0 || $attempt->calculation_score > 30
            || $attempt->knowledge_score + $attempt->calculation_score !== $attempt->score) {
            return false;
        }

        $unitScores = $attempt->unit_scores;
        if (! is_array($unitScores)) {
            return false;
        }

        $actualUnitSlugs = array_keys($unitScores);
        sort($actualUnitSlugs);
        $requiredUnitSlugs = self::REQUIRED_UNIT_SLUGS;
        sort($requiredUnitSlugs);

        if ($actualUnitSlugs !== $requiredUnitSlugs) {
            return false;
        }

        $totalEarned = 0;
        $totalMax = 0;

        foreach (self::REQUIRED_UNIT_SLUGS as $slug) {
            $score = $unitScores[$slug] ?? null;
            $earned = is_array($score) ? ($score['earned'] ?? null) : null;
            $max = is_array($score) ? ($score['max'] ?? null) : null;

            if (! is_numeric($earned) || ! is_numeric($max) || (int) $max <= 0) {
                return false;
            }

            if ((int) $earned < 0 || (int) $earned > (int) $max) {
                return false;
            }

            $totalEarned += (int) $earned;
            $totalMax += (int) $max;
        }

        return $totalEarned === $attempt->score && $totalMax === 100;
    }

    private function hasNoPriorPracticeExposure(User $user, MockExamAttempt $attempt): bool
    {
        $snapshot = $attempt->review_snapshot ?? $this->snapshots->build($attempt->mockExam);
        $questionIds = collect($snapshot)->pluck('question_id');
        if ($questionIds->count() !== 40 || $questionIds->unique()->count() !== 40) {
            return false;
        }

        return ! $user->attempts()
            ->whereIn('question_id', $questionIds)
            ->where('context', '!=', AttemptContext::Mock->value)
            ->where('created_at', '<=', $attempt->started_at)
            ->exists();
    }

    /**
     * @param  array<string, int>  $unitAccuracies
     * @return array{label: string, detail: string, qualifying_mock_count: int, mock_average: int|null, unit_accuracies: array<string, int>}
     */
    private function result(string $label, string $detail, int $count, ?int $average, array $unitAccuracies): array
    {
        return [
            'label' => $label,
            'detail' => $detail,
            'qualifying_mock_count' => $count,
            'mock_average' => $average,
            'unit_accuracies' => $unitAccuracies,
        ];
    }
}

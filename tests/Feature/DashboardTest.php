<?php

namespace Tests\Feature;

use App\Enums\AttemptContext;
use App\Models\MockExam;
use App\Models\Question;
use App\Models\User;
use App\Services\MockExamSnapshotService;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_review_count_excludes_questions_that_are_not_publishable()
    {
        $this->seed(ContentSeeder::class);
        $user = User::factory()->create(['onboarded' => true])->refresh();
        $published = Question::where('source_id', 'q-0032')->firstOrFail();
        $expired = Question::where('source_id', 'q-0030')->firstOrFail();

        $user->reviewItems()->create(['question_id' => $published->id, 'box' => 1, 'due_date' => today(), 'lapses' => 1]);
        $user->reviewItems()->create(['question_id' => $expired->id, 'box' => 1, 'due_date' => today(), 'lapses' => 1]);
        $expired->update(['review_due_at' => now()->subMinute()]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('summary.review_due', 1));
    }

    public function test_due_review_is_prioritized_over_new_questions()
    {
        $this->seed(ContentSeeder::class);
        $user = User::factory()->create(['onboarded' => true])->refresh();
        $question = Question::where('source_id', 'q-0032')->firstOrFail();
        $user->reviewItems()->create([
            'question_id' => $question->id,
            'box' => 1,
            'due_date' => today(),
            'lapses' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.next_action_href', '/review')
                ->where('summary.next_action_label', '期限到来の復習1問をはじめる'),
            );
    }

    public function test_dashboard_starts_with_the_pass_core_instead_of_forcing_all_questions()
    {
        $this->seed(ContentSeeder::class);
        $user = User::factory()->create(['onboarded' => true])->refresh();
        $coreCount = Question::query()->published()->practiceBank()->where('study_tier', 'core')->count();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.core_question_count', $coreCount)
                ->where('summary.core_seen_questions', 0)
                ->where('summary.core_coverage_percent', 0)
                ->where('summary.daily_new_target', 10)
                ->where('summary.daily_new_label', '今日のコア')
                ->where('summary.readiness_label', '基礎構築中')
                ->where('summary.next_action_label', fn (string $label): bool => str_contains($label, '合格コア')),
            );
    }

    public function test_learning_recommendation_does_not_change_with_the_exam_date()
    {
        Carbon::setTestNow('2026-08-31');
        try {
            $this->seed(ContentSeeder::class);

            foreach (['2026-09-01', '2027-08-31'] as $examDate) {
                $user = User::factory()->create([
                    'onboarded' => true,
                    'exam_date' => $examDate,
                ])->refresh();

                $this->actingAs($user)
                    ->get(route('dashboard'))
                    ->assertOk()
                    ->assertInertia(fn ($page) => $page
                        ->where('summary.readiness_label', '基礎構築中')
                        ->where('summary.next_action_label', fn (string $label): bool => str_contains($label, '合格コア'))
                        ->missing('season'),
                    );
            }
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_one_high_mock_score_does_not_claim_pass_readiness()
    {
        $this->seed(ContentSeeder::class);
        $user = User::factory()->create(['onboarded' => true])->refresh();
        $exam = MockExam::query()->firstOrFail();
        $user->mockExamAttempts()->create([
            'mock_exam_id' => $exam->id,
            'time_limit_minutes' => 120,
            'started_at' => now()->subHours(2),
            'finished_at' => now(),
            'answers' => [],
            'score' => 70,
            'section_scores' => [],
            'unit_scores' => $this->unitScores(70),
            'knowledge_score' => 52,
            'calculation_score' => 18,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.core_seen_questions', 0)
                ->where('summary.latest_mock_score', 70)
                ->where('summary.readiness_label', '実力確認中')
                ->where('summary.qualifying_mock_count', 1),
            );
    }

    public function test_two_distinct_strong_first_time_mocks_reach_the_readiness_zone()
    {
        $this->seed(ContentSeeder::class);
        $user = User::factory()->create(['onboarded' => true])->refresh();
        $exams = MockExam::query()->orderBy('sort_order')->take(2)->get();

        foreach ($exams as $index => $exam) {
            $score = 80 + ($index * 2);
            $user->mockExamAttempts()->create([
                'mock_exam_id' => $exam->id,
                'time_limit_minutes' => $exam->time_limit_minutes,
                'started_at' => now()->subHours(5 - $index),
                'finished_at' => now()->subHours(3 - $index),
                'answers' => [],
                'score' => $score,
                'section_scores' => [],
                'unit_scores' => $this->unitScores($score),
                'knowledge_score' => 62 + ($index * 2),
                'calculation_score' => 18,
            ]);
        }

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.readiness_label', '合格圏')
                ->where('summary.qualifying_mock_count', 2)
                ->where('summary.mock_average', 81),
            );
    }

    public function test_retaking_one_mock_does_not_count_as_multiple_fresh_mocks()
    {
        $this->seed(ContentSeeder::class);
        $user = User::factory()->create(['onboarded' => true])->refresh();
        $exam = MockExam::query()->firstOrFail();

        foreach ([80, 100] as $index => $score) {
            $user->mockExamAttempts()->create([
                'mock_exam_id' => $exam->id,
                'time_limit_minutes' => $exam->time_limit_minutes,
                'started_at' => now()->subHours(5 - $index),
                'finished_at' => now()->subHours(3 - $index),
                'answers' => [],
                'score' => $score,
                'section_scores' => [],
                'unit_scores' => $this->unitScores($score),
                'knowledge_score' => $score - 18,
                'calculation_score' => 18,
            ]);
        }

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.readiness_label', '実力確認中')
                ->where('summary.qualifying_mock_count', 1)
                ->where('summary.mock_average', 80),
            );
    }

    public function test_missing_unit_diagnostics_never_reach_the_readiness_zone()
    {
        $this->seed(ContentSeeder::class);
        $user = User::factory()->create(['onboarded' => true])->refresh();
        $exams = MockExam::query()->orderBy('sort_order')->take(2)->get();

        foreach ($exams as $index => $exam) {
            $unitScores = $this->unitScores(90);
            if ($index === 0) {
                unset($unitScores['zei']);
            }

            $user->mockExamAttempts()->create([
                'mock_exam_id' => $exam->id,
                'time_limit_minutes' => $exam->time_limit_minutes,
                'started_at' => now()->subHours(5 - $index),
                'finished_at' => now()->subHours(3 - $index),
                'answers' => [],
                'score' => 90,
                'section_scores' => [],
                'unit_scores' => $unitScores,
                'knowledge_score' => 66,
                'calculation_score' => 24,
            ]);
        }

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.readiness_label', '実力確認中')
                ->where('summary.qualifying_mock_count', 1)
                ->where('summary.mock_average', 90),
            );
    }

    public function test_compressed_mocks_never_reach_the_readiness_zone()
    {
        $this->seed(ContentSeeder::class);
        $user = User::factory()->create(['onboarded' => true])->refresh();
        $exams = MockExam::query()->orderBy('sort_order')->take(2)->get();

        foreach ($exams as $index => $exam) {
            $user->mockExamAttempts()->create([
                'mock_exam_id' => $exam->id,
                'time_limit_minutes' => 90,
                'started_at' => now()->subHours(5 - $index),
                'finished_at' => now()->subHours(3 - $index),
                'answers' => [],
                'score' => 100,
                'section_scores' => [],
                'unit_scores' => $this->unitScores(100),
                'knowledge_score' => 70,
                'calculation_score' => 30,
            ]);
        }

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.readiness_label', '基礎構築中')
                ->where('summary.qualifying_mock_count', 0)
                ->where('summary.mock_average', null),
            );
    }

    public function test_standard_retake_after_a_compressed_first_attempt_is_not_fresh()
    {
        $this->seed(ContentSeeder::class);
        $user = User::factory()->create(['onboarded' => true])->refresh();
        $exams = MockExam::query()->orderBy('sort_order')->take(2)->get();

        foreach ([90, 120] as $index => $minutes) {
            $user->mockExamAttempts()->create([
                'mock_exam_id' => $exams->first()->id,
                'time_limit_minutes' => $minutes,
                'started_at' => now()->subHours(8 - $index),
                'finished_at' => now()->subHours(7 - $index),
                'answers' => [],
                'score' => 100,
                'section_scores' => [],
                'unit_scores' => $this->unitScores(100),
                'knowledge_score' => 70,
                'calculation_score' => 30,
            ]);
        }

        $user->mockExamAttempts()->create([
            'mock_exam_id' => $exams->last()->id,
            'time_limit_minutes' => 120,
            'started_at' => now()->subHours(4),
            'finished_at' => now()->subHours(2),
            'answers' => [],
            'score' => 100,
            'section_scores' => [],
            'unit_scores' => $this->unitScores(100),
            'knowledge_score' => 70,
            'calculation_score' => 30,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.readiness_label', '実力確認中')
                ->where('summary.qualifying_mock_count', 1),
            );
    }

    public function test_prior_practice_exposure_disqualifies_that_mock_from_fresh_readiness()
    {
        $this->seed(ContentSeeder::class);
        $user = User::factory()->create(['onboarded' => true])->refresh();
        $exams = MockExam::query()->orderBy('sort_order')->take(2)->get();
        $exposedQuestion = $exams->first()->examQuestions()->firstOrFail()->question;
        $exposure = $user->attempts()->create([
            'question_id' => $exposedQuestion->id,
            'lesson_id' => $exposedQuestion->lesson_id,
            'context' => AttemptContext::Lesson,
            'is_correct' => true,
            'given_answer' => ['given' => $exposedQuestion->answer['choice']],
            'xp_earned' => 0,
        ]);
        DB::table('question_attempts')->where('id', $exposure->id)->update([
            'created_at' => now()->subHours(10),
            'updated_at' => now()->subHours(10),
        ]);

        foreach ($exams as $index => $exam) {
            $user->mockExamAttempts()->create([
                'mock_exam_id' => $exam->id,
                'time_limit_minutes' => 120,
                'started_at' => now()->subHours(5 - $index),
                'finished_at' => now()->subHours(3 - $index),
                'answers' => [],
                'score' => 100,
                'section_scores' => [],
                'unit_scores' => $this->unitScores(100),
                'knowledge_score' => 70,
                'calculation_score' => 30,
            ]);
        }

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.readiness_label', '実力確認中')
                ->where('summary.qualifying_mock_count', 1),
            );
    }

    public function test_inconsistent_diagnostic_totals_never_reach_the_readiness_zone()
    {
        $this->seed(ContentSeeder::class);
        $user = User::factory()->create(['onboarded' => true])->refresh();
        $exams = MockExam::query()->orderBy('sort_order')->take(2)->get();

        foreach ($exams as $index => $exam) {
            $user->mockExamAttempts()->create([
                'mock_exam_id' => $exam->id,
                'time_limit_minutes' => $exam->time_limit_minutes,
                'started_at' => now()->subHours(5 - $index),
                'finished_at' => now()->subHours(3 - $index),
                'answers' => [],
                'score' => 90,
                'section_scores' => [],
                'unit_scores' => $this->unitScores(80),
                'knowledge_score' => 66,
                'calculation_score' => 24,
            ]);
        }

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.readiness_label', '基礎構築中')
                ->where('summary.qualifying_mock_count', 0),
            );
    }

    public function test_duplicate_questions_in_a_saved_snapshot_never_reach_the_readiness_zone()
    {
        $this->seed(ContentSeeder::class);
        $user = User::factory()->create(['onboarded' => true])->refresh();
        $exams = MockExam::query()->orderBy('sort_order')->take(2)->get();

        foreach ($exams as $index => $exam) {
            $snapshot = app(MockExamSnapshotService::class)->build($exam);
            if ($index === 0) {
                $snapshot = array_fill(0, 40, $snapshot[0]);
            }

            $user->mockExamAttempts()->create([
                'mock_exam_id' => $exam->id,
                'time_limit_minutes' => $exam->time_limit_minutes,
                'started_at' => now()->subHours(5 - $index),
                'finished_at' => now()->subHours(3 - $index),
                'answers' => [],
                'score' => 90,
                'section_scores' => [],
                'unit_scores' => $this->unitScores(90),
                'knowledge_score' => 66,
                'calculation_score' => 24,
                'review_snapshot' => $snapshot,
            ]);
        }

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.readiness_label', '実力確認中')
                ->where('summary.qualifying_mock_count', 1),
            );
    }

    public function test_low_calculation_score_blocks_the_readiness_zone()
    {
        $this->seed(ContentSeeder::class);
        $user = User::factory()->create(['onboarded' => true])->refresh();
        $exams = MockExam::query()->orderBy('sort_order')->take(2)->get();

        foreach ($exams as $index => $exam) {
            $user->mockExamAttempts()->create([
                'mock_exam_id' => $exam->id,
                'time_limit_minutes' => $exam->time_limit_minutes,
                'started_at' => now()->subHours(5 - $index),
                'finished_at' => now()->subHours(3 - $index),
                'answers' => [],
                'score' => 85,
                'section_scores' => [],
                'unit_scores' => $this->unitScores(85),
                'knowledge_score' => 68,
                'calculation_score' => 17,
            ]);
        }

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.readiness_label', '弱点補強中')
                ->where('summary.qualifying_mock_count', 2),
            );
    }

    public function test_low_unit_score_blocks_the_readiness_zone()
    {
        $this->seed(ContentSeeder::class);
        $user = User::factory()->create(['onboarded' => true])->refresh();
        $exams = MockExam::query()->orderBy('sort_order')->take(2)->get();

        foreach ($exams as $index => $exam) {
            $unitScores = $this->unitScores(80);
            $unitScores['zei']['earned'] = 10;
            $unitScores['zei']['accuracy'] = 50;
            $unitScores['shikyu']['earned'] = 20;
            $unitScores['shikyu']['accuracy'] = 100;
            $unitScores['roudou']['earned'] = 18;
            $unitScores['roudou']['accuracy'] = 90;

            $user->mockExamAttempts()->create([
                'mock_exam_id' => $exam->id,
                'time_limit_minutes' => $exam->time_limit_minutes,
                'started_at' => now()->subHours(5 - $index),
                'finished_at' => now()->subHours(3 - $index),
                'answers' => [],
                'score' => 80,
                'section_scores' => [],
                'unit_scores' => $unitScores,
                'knowledge_score' => 62,
                'calculation_score' => 18,
            ]);
        }

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.readiness_label', '弱点補強中')
                ->where('summary.readiness_detail', '全単元で得点率60%以上を目指す'),
            );
    }

    public function test_a_strong_retake_cannot_replace_a_weak_first_standard_attempt()
    {
        $this->seed(ContentSeeder::class);
        $user = User::factory()->create(['onboarded' => true])->refresh();
        $exams = MockExam::query()->orderBy('sort_order')->take(2)->get();

        foreach ([60, 100] as $index => $score) {
            $user->mockExamAttempts()->create([
                'mock_exam_id' => $exams->first()->id,
                'time_limit_minutes' => $exams->first()->time_limit_minutes,
                'started_at' => now()->subHours(8 - $index),
                'finished_at' => now()->subHours(7 - $index),
                'answers' => [],
                'score' => $score,
                'section_scores' => [],
                'unit_scores' => $this->unitScores($score),
                'knowledge_score' => $score === 60 ? 42 : 70,
                'calculation_score' => $score === 60 ? 18 : 30,
            ]);
        }

        $user->mockExamAttempts()->create([
            'mock_exam_id' => $exams->last()->id,
            'time_limit_minutes' => $exams->last()->time_limit_minutes,
            'started_at' => now()->subHours(4),
            'finished_at' => now()->subHours(2),
            'answers' => [],
            'score' => 100,
            'section_scores' => [],
            'unit_scores' => $this->unitScores(100),
            'knowledge_score' => 70,
            'calculation_score' => 30,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.readiness_label', '弱点補強中')
                ->where('summary.qualifying_mock_count', 2)
                ->where('summary.mock_average', 80),
            );
    }

    public function test_below_pass_mock_prioritizes_weakness_even_before_core_coverage()
    {
        $this->seed(ContentSeeder::class);
        $user = User::factory()->create(['onboarded' => true])->refresh();
        $exam = MockExam::query()->firstOrFail();
        $user->mockExamAttempts()->create([
            'mock_exam_id' => $exam->id,
            'time_limit_minutes' => 120,
            'started_at' => now()->subHours(2),
            'finished_at' => now(),
            'answers' => [],
            'score' => 69,
            'section_scores' => [],
            'unit_scores' => $this->unitScores(69),
            'knowledge_score' => 51,
            'calculation_score' => 18,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.latest_mock_score', 69)
                ->where('summary.readiness_label', '弱点補強中'),
            );
    }

    public function test_reinforcement_questions_do_not_inflate_the_daily_core_target()
    {
        $this->seed(ContentSeeder::class);
        $user = User::factory()->create(['onboarded' => true])->refresh();
        $core = Question::query()->published()->practiceBank()->where('study_tier', 'core')->firstOrFail();
        $reinforcement = Question::query()->published()->practiceBank()->where('study_tier', 'reinforcement')->firstOrFail();

        foreach ([$core, $reinforcement] as $question) {
            $user->questionProgresses()->create([
                'question_id' => $question->id,
                'state' => 'review',
                'box' => 2,
                'due_at' => now()->addDays(3),
                'content_revision_seen' => $question->content_revision,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
            ]);
        }

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.new_completed_today', 1)
                ->where('summary.daily_new_label', '今日のコア'),
            );
    }

    public function test_health_endpoint_checks_the_database()
    {
        $this->get('/up')->assertOk();
    }

    /** @return array<string, array{name: string, correct: int, total: int, earned: int, max: int, accuracy: int}> */
    private function unitScores(int $score): array
    {
        $base = intdiv($score, 5);
        $remainder = $score % 5;

        return collect(['shikyu', 'roudou', 'shaho', 'zei', 'keisan'])
            ->mapWithKeys(function (string $slug, int $index) use ($base, $remainder): array {
                $earned = $base + ($index < $remainder ? 1 : 0);

                return [$slug => [
                    'name' => $slug,
                    'correct' => $earned,
                    'total' => 20,
                    'earned' => $earned,
                    'max' => 20,
                    'accuracy' => (int) round($earned / 20 * 100),
                ]];
            })
            ->all();
    }
}

<?php

namespace Tests\Feature;

use App\Models\MockExam;
use App\Models\Question;
use App\Models\User;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
        $coreCount = Question::query()->published()->where('study_tier', 'core')->count();

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

    public function test_mock_score_is_the_source_of_truth_for_pass_readiness()
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
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.core_seen_questions', 0)
                ->where('summary.latest_mock_score', 70)
                ->where('summary.readiness_label', '合格ライン到達'),
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
        $core = Question::query()->published()->where('study_tier', 'core')->firstOrFail();
        $reinforcement = Question::query()->published()->where('study_tier', 'reinforcement')->firstOrFail();

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
}

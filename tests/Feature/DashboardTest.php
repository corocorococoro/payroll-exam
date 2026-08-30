<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\User;
use Database\Seeders\ContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $published = Question::where('source_id', 'r2-q01')->firstOrFail();
        $expired = Question::where('source_id', 'r2-q02')->firstOrFail();

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
        $question = Question::where('source_id', 'r2-q01')->firstOrFail();
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

    public function test_health_endpoint_checks_the_database()
    {
        $this->get('/up')->assertOk();
    }
}

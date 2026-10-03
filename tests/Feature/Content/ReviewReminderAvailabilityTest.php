<?php

use App\Models\LearningModule;
use App\Models\Lesson;
use App\Models\MockExam;
use App\Models\Question;
use App\Models\User;
use App\Services\QuestionReviewLedger;
use Carbon\CarbonImmutable;

test('実台帳の再確認日を過ぎても全単元・旧リンク・復習・模試を開始し解答できる', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-03 09:00:00', 'Asia/Tokyo'));
    // Use the real, unchanged ledger, not the application's synthetic test binding.
    $ledger = new QuestionReviewLedger;
    app()->instance(QuestionReviewLedger::class, $ledger);
    expect($ledger->errors())->toBe([])
        ->and($ledger->reminders())->toHaveCount(567);
    $this->artisan('content:review-audit')->assertSuccessful();
    $this->artisan('content:sync')->assertSuccessful();
    $this->artisan('content:audit --strict')->assertSuccessful();
    expect(Question::published()->count())->toBe(567)
        ->and(Question::practiceBank()->count())->toBe(447);

    $user = User::factory()->create(['onboarded' => true])->refresh();
    $user->statOrCreate()->update(['total_xp' => 1820]);
    $question = Question::where('source_id', 'q-0032')->firstOrFail();
    $review = $user->reviewItems()->create(['question_id' => $question->id, 'box' => 2, 'due_date' => today(), 'lapses' => 1]);
    $ids = Question::pluck('id', 'source_id')->all();
    $this->artisan('content:sync --force')->assertSuccessful();
    expect(Question::pluck('id', 'source_id')->all())->toBe($ids)
        ->and($user->statOrCreate()->refresh()->total_xp)->toBe(1820)
        ->and($review->refresh()->box)->toBe(2)
        ->and($question->refresh()->review_due_at->toDateString())->toBe('2026-10-01');

    $this->actingAs($user)->get('/learn')->assertOk()->assertInertia(fn ($page) => $page
        ->has('curriculum.modules', 57)->where('curriculum.unavailable_count', 0)
        ->where('curriculum.next.href', '/study/payslip'));
    foreach (Lesson::all() as $lesson) {
        $this->get("/lessons/{$lesson->id}")->assertOk();
    }
    foreach (LearningModule::where('is_active', true)->get() as $module) {
        $this->get('/study/'.$module->slug)->assertOk();
    }

    $module = LearningModule::where('slug', 'payslip')->firstOrFail();
    $run = session("study_runs.{$module->id}");
    foreach ($run['question_ids'] as $id) {
        $question = Question::findOrFail($id);
        $this->postJson('/answers', [
            'question_id' => $id, 'context' => 'lesson', 'study_run_id' => $run['id'],
            'learning_module_id' => $module->id, 'answer' => correctChoice($question),
        ])->assertOk()->assertJsonPath('correct', true);
    }
    $this->postJson('/study/payslip/complete', ['study_run_id' => $run['id']])->assertOk();
    $attemptCount = $user->attempts()->count();
    $this->artisan('content:sync --force')->assertSuccessful();
    expect($user->attempts()->count())->toBe($attemptCount)->toBeGreaterThan(0);
    $this->get('/review')->assertOk()->assertInertia(fn ($page) => $page->has('questions', 1));
    $this->get('/mock-exams')->assertOk();
    foreach (MockExam::where('is_published', true)->get() as $exam) {
        $this->post("/mock-exams/{$exam->id}/attempts", ['mode' => 'standard'])->assertRedirect();
        $attempt = $user->mockExamAttempts()->where('mock_exam_id', $exam->id)->firstOrFail();
        $this->get("/mock-attempts/{$attempt->id}")->assertOk();
    }
});

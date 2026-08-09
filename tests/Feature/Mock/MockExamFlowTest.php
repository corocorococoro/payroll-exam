<?php

use App\Models\MockExam;
use App\Models\MockExamAttempt;
use App\Models\User;
use Database\Seeders\ContentSeeder;
use Database\Seeders\GamificationSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed([ContentSeeder::class, GamificationSeeder::class]);
    $this->user = User::factory()->create(['onboarded' => true])->refresh();
    $this->exam = MockExam::where('slug', 'mogi-1')->firstOrFail();
});

test('模試を開始して正解を漏らさず途中保存できる', function () {
    actingAs($this->user)->post("/mock-exams/{$this->exam->id}/attempts", [
        'mode' => 'standard',
    ])->assertRedirect();

    $attempt = MockExamAttempt::where('user_id', $this->user->id)->firstOrFail();
    expect($attempt->time_limit_minutes)->toBe(120);

    $response = actingAs($this->user)->get("/mock-attempts/{$attempt->id}");
    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('mock/Player')
        ->has('questions', 40)
        ->missing('questions.0.answer')
        ->missing('questions.0.explanation'),
    );
    expect($response->getContent())->not->toContain('correct_answer');

    $questionId = (string) $this->exam->examQuestions()->value('question_id');
    actingAs($this->user)->patchJson("/mock-attempts/{$attempt->id}", [
        'answers' => [$questionId => 'D', '999999' => 'A'],
    ])->assertOk()->assertJson(['saved' => true]);

    expect($attempt->refresh()->answers)->toBe([$questionId => 'D']);
});

test('40問をサーバー採点し100点と分野別診断を返す', function () {
    $attempt = $this->user->mockExamAttempts()->create([
        'mock_exam_id' => $this->exam->id,
        'time_limit_minutes' => 120,
        'started_at' => now(),
        'answers' => correctAnswers($this->exam),
    ]);

    actingAs($this->user)->post("/mock-attempts/{$attempt->id}/finish")->assertRedirect();

    $attempt->refresh();
    expect($attempt->score)->toBe(100)
        ->and($attempt->finished_at)->not->toBeNull()
        ->and(array_sum(array_column($attempt->section_scores, 'max')))->toBe(100);

    actingAs($this->user)->get("/mock-attempts/{$attempt->id}/result")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('mock/Result')
            ->where('result.score', 100)
            ->where('result.passed', true)
            ->has('review', 40),
        );
});

test('模試終了の再送でも解答履歴を二重作成しない', function () {
    $attempt = $this->user->mockExamAttempts()->create([
        'mock_exam_id' => $this->exam->id,
        'time_limit_minutes' => 120,
        'started_at' => now(),
        'answers' => correctAnswers($this->exam),
    ]);

    actingAs($this->user)->post("/mock-attempts/{$attempt->id}/finish")->assertRedirect();
    actingAs($this->user)->post("/mock-attempts/{$attempt->id}/finish")->assertRedirect();

    expect($this->user->attempts()->where('context', 'mock')->count())->toBe(40);
});

test('採点後の遅延した途中保存を拒否して採点時の解答を保持する', function () {
    $answers = correctAnswers($this->exam);
    $attempt = $this->user->mockExamAttempts()->create([
        'mock_exam_id' => $this->exam->id,
        'time_limit_minutes' => 120,
        'started_at' => now(),
        'answers' => $answers,
    ]);

    actingAs($this->user)->post("/mock-attempts/{$attempt->id}/finish")->assertRedirect();
    actingAs($this->user)->patchJson("/mock-attempts/{$attempt->id}", [
        'answers' => [],
    ])->assertStatus(422);

    expect($attempt->refresh()->answers)->toBe($answers)
        ->and($attempt->score)->toBe(100);
});

test('知識35問正解で70点となり合格判定される', function () {
    $answers = collect(correctAnswers($this->exam))->take(35)->all();
    $attempt = $this->user->mockExamAttempts()->create([
        'mock_exam_id' => $this->exam->id,
        'time_limit_minutes' => 90,
        'started_at' => now(),
        'answers' => $answers,
    ]);

    actingAs($this->user)->post("/mock-attempts/{$attempt->id}/finish")->assertRedirect();
    expect($attempt->refresh()->score)->toBe(70);
});

test('他ユーザーの模試にはアクセスできない', function () {
    $attempt = $this->user->mockExamAttempts()->create([
        'mock_exam_id' => $this->exam->id,
        'time_limit_minutes' => 120,
        'started_at' => now(),
        'answers' => [],
    ]);

    $other = User::factory()->create();
    actingAs($other)->get("/mock-attempts/{$attempt->id}")->assertForbidden();
});

test('期限後の途中保存を拒否し自動採点する', function () {
    $attempt = $this->user->mockExamAttempts()->create([
        'mock_exam_id' => $this->exam->id,
        'time_limit_minutes' => 120,
        'started_at' => now()->subMinutes(121),
        'answers' => [],
    ]);

    $questionId = (string) $this->exam->examQuestions()->value('question_id');
    actingAs($this->user)->patchJson("/mock-attempts/{$attempt->id}", [
        'answers' => [$questionId => 'D'],
    ])->assertStatus(422);

    expect($attempt->refresh()->finished_at)->not->toBeNull()
        ->and($attempt->answers)->toBe([]);
});

function correctAnswers(MockExam $exam): array
{
    return $exam->examQuestions()->with('question')->get()->mapWithKeys(function ($item): array {
        $answer = $item->question->answer;

        return [(string) $item->question_id => (string) ($answer['choice'] ?? $answer['value'])];
    })->all();
}

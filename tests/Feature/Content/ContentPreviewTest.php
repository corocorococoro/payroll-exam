<?php

use App\Models\Lesson;
use App\Models\MockExam;
use App\Models\Question;
use App\Models\User;
use Database\Seeders\ContentSeeder;
use Illuminate\Support\Facades\File;

test('例題・模試資料・採点後の選択肢を画面に渡せる', function () {
    $this->seed(ContentSeeder::class);
    $user = User::factory()->create(['onboarded' => true])->refresh();
    $lesson = Lesson::where('slug', 'kyuyo-keisan')->firstOrFail();
    $lessonResponse = $this->actingAs($user)->get("/lessons/{$lesson->id}")->assertOk();
    $lessonResponse->assertInertia(fn ($page) => $page->where('lesson.study_guide.worked_example', fn ($text) => is_string($text) && $text !== ''));

    $exam = MockExam::where('slug', 'mogi-1')->firstOrFail();
    $this->post("/mock-exams/{$exam->id}/attempts", ['mode' => 'standard'])->assertRedirect();
    $attempt = $user->mockExamAttempts()->firstOrFail();
    $player = $this->get("/mock-attempts/{$attempt->id}")->assertOk();
    $this->post("/mock-attempts/{$attempt->id}/finish")->assertRedirect();
    $result = $this->get("/mock-attempts/{$attempt->id}/result")->assertOk();
    $result->assertInertia(fn ($page) => $page->has('review.0.choices', 4)->has('reference_sheets'));

    // Opt-in static visual QA snapshots of synthetic users; no production data.
    if (getenv('EXPORT_CONTENT_PREVIEW') === '1') {
        $payrollLesson = Lesson::where('slug', 'warimashi')->firstOrFail();
        $payrollResponse = $this->get("/lessons/{$payrollLesson->id}")->assertOk();
        $flowLesson = Lesson::where('slug', 'payroll-flow')->firstOrFail();
        $flowResponse = $this->get("/lessons/{$flowLesson->id}")->assertOk();
        $taxLesson = Lesson::where('slug', 'gensen')->firstOrFail();
        $taxResponse = $this->get("/lessons/{$taxLesson->id}")->assertOk();
        $socialLesson = Lesson::where('slug', 'hyojun-hoshu')->firstOrFail();
        $socialResponse = $this->get("/lessons/{$socialLesson->id}")->assertOk();
        $directory = storage_path('framework/testing/content-qa');
        File::ensureDirectoryExists($directory);
        foreach (['lesson' => $lessonResponse, 'payroll-lesson' => $payrollResponse, 'flow-lesson' => $flowResponse, 'tax-lesson' => $taxResponse, 'social-lesson' => $socialResponse, 'player' => $player, 'result' => $result] as $name => $response) {
            File::put("{$directory}/{$name}.html", str_replace(config('app.url'), '', $response->getContent()));
        }
    }
});

test('年休の比例付与表を通常問題と受験時の資料から参照できる', function () {
    $this->seed(ContentSeeder::class);
    $user = User::factory()->create(['onboarded' => true])->refresh();
    $question = Question::where('source_id', 'q-0091')->firstOrFail();
    $response = $this->actingAs($user)->withSession(lessonRun($question))
        ->get("/lessons/{$question->lesson_id}")->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('questions', 1)
        ->where('questions.0.reference_sheet_slugs', ['nenkyu-fuyo'])
        ->where('reference_sheets.0.slug', 'nenkyu-fuyo')
        ->where('reference_sheets.0.content.rows.1.1', '7日')
        ->where('reference_sheets.0.content.rows.2.1', '5日')
        ->where('reference_sheets.0.content.rows.0.7', '20日')
        ->missing('questions.0.answer'));

    $exam = MockExam::where('slug', 'mogi-2')->firstOrFail();
    $this->post("/mock-exams/{$exam->id}/attempts", ['mode' => 'standard'])->assertRedirect();
    $attempt = $user->mockExamAttempts()->firstOrFail();
    $id = Question::where('source_id', 'q-0086')->value('id');
    $item = collect($attempt->review_snapshot)->firstWhere('question_id', $id);
    expect($item['reference_sheets'][0]['slug'])->toBe('nenkyu-fuyo');

    if (getenv('EXPORT_CONTENT_PREVIEW') === '1') {
        $directory = storage_path('framework/testing/content-qa');
        File::ensureDirectoryExists($directory);
        File::put("{$directory}/annual-leave.html", str_replace(config('app.url'), '', $response->getContent()));
    }
});

test('賞与表の隣接区分と健康厚年の上下限を解答画面から参照できる', function () {
    $this->seed(ContentSeeder::class);
    $user = User::factory()->create(['onboarded' => true])->refresh();
    $responses = [];
    foreach (['bonus-table' => 'q-0828', 'insurance-grades' => 'q-0831', 'integrated-net' => 'q-0830'] as $name => $id) {
        $question = Question::where('source_id', $id)->firstOrFail();
        $response = $this->actingAs($user)->withSession(lessonRun($question))
            ->get("/lessons/{$question->lesson_id}")->assertOk();
        $response->assertInertia(fn ($page) => $page->missing('questions.0.answer'));
        $responses[$name] = $response;
    }

    // Fixed cells independently transcribed from the published 2026 originals:
    // NTA pages 15–16 and Kyokai Kenpo Tokyo grade table (not generated formulas).
    $responses['bonus-table']->assertInertia(function ($page) {
        $page->where('reference_sheets', function ($sheets) {
            $table = collect($sheets)->firstWhere('slug', 'shoyo-santei-ritsu')['content'];
            $original = json_decode(File::get(base_path('tests/Fixtures/bonus-rate-table-2026.json')), true, flags: JSON_THROW_ON_ERROR);
            // Compare every published band, including those not used by a current question.
            expect($table['rows'])->toBe($original['rows']);
            expect($table['note'])->toContain('単位は千円');
            expect($table['rows'])->toHaveCount(21);
            expect($table['rows'][0][2])->toBe('107未満');
            expect($table['rows'][1][0])->toBe('2.042');
            expect($table['rows'][1][2])->toBe('107以上 250未満');
            expect($table['rows'][2][2])->toBe('250以上 289未満');
            expect($table['rows'][20][8])->toBe('3,717以上');
            expect($table['example_rows'][0]['rows'])->toBe([
                ['224未満', '10.210'], ['224以上 295未満', '20.420'],
                ['295以上 527未満', '30.630'], ['527以上 1,118未満', '38.798'],
                ['1,118以上', '45.945'],
            ]);

            return true;
        });
    });
    $responses['insurance-grades']->assertInertia(function ($page) {
        $page->where('reference_sheets.0.content.example_rows.0.rows', function ($rows) {
            $original = json_decode(File::get(base_path('tests/Fixtures/insurance-grades-2026.json')), true, flags: JSON_THROW_ON_ERROR);
            expect(collect($rows)->all())->toBe($original['rows']);
            expect($rows)->toHaveCount(50);
            expect($rows[0])->toBe(['1', '1', '63,000未満', '58,000', '88,000']);
            expect($rows[16])->toBe(['17', '14', '195,000以上 210,000未満', '200,000', '200,000']);
            expect($rows[34])->toBe(['35', '32', '635,000以上 665,000未満', '650,000', '650,000']);
            expect($rows[49])->toBe(['50', '32', '1,355,000以上', '1,390,000', '650,000']);

            return true;
        });
    });
    $this->assertDatabaseMissing('questions', ['source_id' => 'q-0395']);
    $exam = MockExam::where('slug', 'mogi-3')->firstOrFail();
    $replacementId = Question::where('source_id', 'q-0837')->value('id');
    expect($exam->examQuestions()->where('question_id', $replacementId)->firstOrFail()->points)->toBe(2);
    expect($exam->examQuestions()->count())->toBe(40);

    if (getenv('EXPORT_CONTENT_PREVIEW') === '1') {
        $directory = storage_path('framework/testing/content-qa');
        File::ensureDirectoryExists($directory);
        foreach ($responses as $name => $response) {
            File::put("{$directory}/{$name}.html", str_replace(config('app.url'), '', $response->getContent()));
        }
    }
});

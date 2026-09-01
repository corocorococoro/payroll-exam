<?php

use App\Enums\AttemptContext;
use App\Models\MockExamAttempt;
use App\Models\QuestionAttempt;
use App\Services\MockExamSnapshotService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mock_exam_attempts', function (Blueprint $table) {
            $table->jsonb('review_snapshot')->nullable()->after('calculation_score');
        });

        MockExamAttempt::query()
            ->whereNull('review_snapshot')
            ->with('mockExam')
            ->eachById(function (MockExamAttempt $attempt): void {
                $snapshots = app(MockExamSnapshotService::class);
                $answers = $attempt->answers ?? [];

                if ($attempt->finished_at === null) {
                    $snapshot = $snapshots->build($attempt->mockExam);
                    DB::table('mock_exam_attempts')->where('id', $attempt->id)->update([
                        'review_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    ]);

                    return;
                }

                $recordedAttempts = QuestionAttempt::query()
                    ->where('user_id', $attempt->user_id)
                    ->where('context', AttemptContext::Mock->value)
                    ->whereBetween('created_at', [
                        $attempt->finished_at->copy()->subSeconds(5),
                        $attempt->finished_at->copy()->addSeconds(5),
                    ])
                    ->orderBy('id')
                    ->get()
                    ->unique('question_id')
                    ->values();
                /** @var list<int> $recordedQuestionIds */
                $recordedQuestionIds = $recordedAttempts
                    ->pluck('question_id')
                    ->map(fn ($id): int => (int) $id)
                    ->values()
                    ->all();
                $snapshot = $recordedAttempts->count() === 40
                    ? $snapshots->buildFromQuestionIds($recordedQuestionIds)
                    : $snapshots->build($attempt->mockExam);
                $snapshot = $snapshots->grade($snapshot, $answers);
                $recordedAttempts = $recordedAttempts->keyBy('question_id');

                $snapshot = collect($snapshot)->map(function (array $item) use ($recordedAttempts): array {
                    $recorded = $recordedAttempts->get((int) $item['question_id']);
                    if ($recorded === null) {
                        return $item;
                    }

                    $item['content_revision'] = $recorded->content_revision;
                    $item['correct'] = $recorded->is_correct;
                    if ($recorded->is_correct && is_string($item['given_answer'])) {
                        $item['correct_answer'] = $item['given_answer'];
                    }

                    return $item;
                })->values()->all();

                DB::table('mock_exam_attempts')->where('id', $attempt->id)->update([
                    'review_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('mock_exam_attempts', function (Blueprint $table) {
            $table->dropColumn('review_snapshot');
        });
    }
};

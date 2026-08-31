<?php

namespace App\Http\Controllers;

use App\Enums\AttemptContext;
use App\Models\Lesson;
use App\Models\Question;
use App\Services\AnswerService;
use App\Services\LessonRunService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AnswerController extends Controller
{
    /**
     * 解答を判定して結果・解説・XPを返す。判定は必ずサーバー側。
     */
    public function store(
        Request $request,
        AnswerService $answerService,
        LessonRunService $runs,
    ): JsonResponse {
        $validated = $request->validate([
            'question_id' => ['required', 'integer', 'exists:questions,id'],
            'answer' => ['required', 'string', 'max:100'],
            'context' => ['required', Rule::in([AttemptContext::Lesson->value, AttemptContext::Review->value])],
            'lesson_id' => ['nullable', 'integer', 'exists:lessons,id'],
        ]);

        $question = Question::query()->published()->findOrFail((int) $validated['question_id']);
        $context = AttemptContext::from($validated['context']);
        $lessonId = isset($validated['lesson_id']) ? (int) $validated['lesson_id'] : null;
        $runStartedAt = null;

        if ($context === AttemptContext::Lesson) {
            abort_if($lessonId === null, 422, 'レッスンIDが必要です。');
            $lesson = Lesson::findOrFail($lessonId);
            $run = $runs->current($request, $lesson);

            abort_if(
                $question->lesson_id !== $lesson->id
                || $run === null
                || ! in_array($question->id, $run['question_ids'], true),
                422,
                'この問題は現在のレッスンには含まれていません。',
            );

            $runStartedAt = CarbonImmutable::parse($run['started_at']);

            $alreadyAnswered = $request->user()->attempts()
                ->where('question_id', $question->id)
                ->where('lesson_id', $lesson->id)
                ->where('context', AttemptContext::Lesson)
                ->where('created_at', '>=', $runStartedAt)
                ->exists();
            abort_if($alreadyAnswered, 422, 'この問題にはすでに解答済みです。');
        } else {
            abort_if($lessonId !== null, 422, '復習ではレッスンIDを指定できません。');
            $isDue = $request->user()->reviewItems()
                ->where('question_id', $question->id)
                ->whereDate('due_date', '<=', today())
                ->exists();
            abort_unless($isDue, 422, 'この問題は現在の復習対象ではありません。');
        }

        $result = $answerService->answer(
            $request->user(),
            $question,
            $validated['answer'],
            $context,
            $lessonId,
            $runStartedAt,
        );

        return response()->json($result);
    }
}

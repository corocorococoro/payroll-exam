<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LearningController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Learning', [
            'learning' => [
                'daily_goal' => $request->user()->daily_goal,
                'reminder_enabled' => $request->user()->reminder_enabled,
                'reminder_time' => $request->user()->reminder_time !== null
                    ? substr((string) $request->user()->reminder_time, 0, 5)
                    : '20:00',
                'exam_date' => $request->user()->exam_date?->toDateString() ?? '2026-11-22',
                'sound_enabled' => $request->user()->sound_enabled,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'daily_goal' => ['required', 'integer', Rule::in([10, 20, 30, 50])],
            'reminder_enabled' => ['required', 'boolean'],
            'reminder_time' => ['nullable', 'required_if:reminder_enabled,true', 'date_format:H:i'],
            'exam_date' => ['required', 'date', 'after_or_equal:today'],
            'sound_enabled' => ['required', 'boolean'],
        ]);

        $request->user()->update([
            ...$validated,
            'reminder_time' => $validated['reminder_enabled'] ? $validated['reminder_time'] : null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => '学習設定を保存しました。']);

        return to_route('learning.edit');
    }
}

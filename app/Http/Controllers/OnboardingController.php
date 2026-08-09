<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OnboardingController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'daily_goal' => ['required', 'integer', Rule::in([10, 20, 30, 50])],
            'reminder_enabled' => ['required', 'boolean'],
            'reminder_time' => ['nullable', 'required_if:reminder_enabled,true', 'date_format:H:i'],
            'exam_date' => ['required', 'date', 'after_or_equal:today'],
        ]);

        $request->user()->update([
            ...$validated,
            'reminder_time' => $validated['reminder_enabled'] ? $validated['reminder_time'] : null,
            'onboarded' => true,
        ]);

        return to_route('dashboard');
    }
}

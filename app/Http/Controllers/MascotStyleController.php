<?php

namespace App\Http\Controllers;

use App\Services\XpLevelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MascotStyleController extends Controller
{
    public function update(Request $request, XpLevelService $levels): JsonResponse
    {
        $validated = $request->validate([
            'style' => ['required', 'string', 'max:64'],
        ]);
        $user = $request->user();
        $style = $validated['style'];

        if (! $levels->canEquip($user, $style)) {
            throw ValidationException::withMessages([
                'style' => 'この衣装はまだ解放されていません。',
            ]);
        }

        $user->statOrCreate()->update(['mascot_style' => $style]);

        return response()->json([
            'xp_progress' => $levels->progress($user),
            'styles' => $levels->styles($user),
        ]);
    }
}

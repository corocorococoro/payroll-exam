<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * ローカル開発専用: Google OAuth の設定なしでワンクリックログインする。
 * ルート登録は local 環境のみ（routes/web.php 参照）。
 */
class DevLoginController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        abort_unless(app()->environment(['local', 'testing']), 404);

        $user = User::firstOrCreate(
            ['email' => 'dev@example.com'],
            [
                'name' => '開発ユーザー',
                'email_verified_at' => now(),
            ],
        );

        Auth::login($user, remember: true);

        return redirect()->intended(route('dashboard'));
    }
}

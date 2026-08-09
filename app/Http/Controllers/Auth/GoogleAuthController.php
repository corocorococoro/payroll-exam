<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;

class GoogleAuthController extends Controller
{
    public function redirect(): SymfonyRedirect
    {
        $this->ensureConfigured();

        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        $this->ensureConfigured();

        $googleUser = Socialite::driver('google')->user();
        $email = $googleUser->getEmail();
        $raw = method_exists($googleUser, 'getRaw') ? $googleUser->getRaw() : [];
        $verified = filter_var(
            $raw['email_verified'] ?? $raw['verified_email'] ?? false,
            FILTER_VALIDATE_BOOL,
        );

        abort_unless(filled($email) && $verified, 403, '検証済みのGoogleメールアドレスが必要です。');
        $email = Str::lower($email);

        $user = User::where('google_id', $googleUser->getId())->first();

        if ($user === null) {
            // 既存のメールアドレスがあれば Google アカウントを紐付ける
            $user = User::where('email', $email)->first();

            if ($user !== null) {
                $user->forceFill([
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                ])->save();
            } else {
                $user = User::forceCreate([
                    'name' => $googleUser->getName() ?? $googleUser->getNickname() ?? 'ユーザー',
                    'email' => $email,
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                    'email_verified_at' => now(),
                ]);
            }
        }

        Auth::login($user, remember: true);

        return redirect()->intended(route('dashboard'));
    }

    private function ensureConfigured(): void
    {
        abort_unless(
            filled(config('services.google.client_id')) && filled(config('services.google.client_secret')),
            404,
        );
    }
}

<?php

use App\Models\User;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

use function Pest\Laravel\assertAuthenticated;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\get;

beforeEach(function () {
    config([
        'services.google.client_id' => 'test-client-id',
        'services.google.client_secret' => 'test-client-secret',
        'services.google.redirect' => 'http://localhost:8000/auth/google/callback',
    ]);
});

function fakeGoogleUser(string $id = 'google-123', string $email = 'taro@example.com', string $name = '検定 太郎'): SocialiteUser
{
    $user = Mockery::mock(SocialiteUser::class);
    $user->allows([
        'getId' => $id,
        'getEmail' => $email,
        'getName' => $name,
        'getNickname' => null,
        'getAvatar' => 'https://example.com/avatar.png',
    ]);

    return $user;
}

test('google redirect はGoogleの認可画面へ飛ばす', function () {
    get('/auth/google/redirect')
        ->assertRedirect()
        ->assertRedirectContains('accounts.google.com');
});

test('未設定環境ではGoogleログインを公開しない', function () {
    config(['services.google.client_id' => null, 'services.google.client_secret' => null]);

    get('/auth/google/redirect')->assertNotFound();
});

test('google callback で新規ユーザーが作成されログインする', function () {
    Socialite::shouldReceive('driver->user')->andReturn(fakeGoogleUser());

    get('/auth/google/callback')->assertRedirect(route('dashboard'));

    assertAuthenticated();

    $user = User::where('email', 'taro@example.com')->firstOrFail();
    expect($user->google_id)->toBe('google-123')
        ->and($user->name)->toBe('検定 太郎')
        ->and($user->email_verified_at)->not->toBeNull();
});

test('google callback で既存メールのユーザーに紐付けされる', function () {
    $existing = User::factory()->create(['email' => 'taro@example.com']);

    Socialite::shouldReceive('driver->user')->andReturn(fakeGoogleUser());

    get('/auth/google/callback')->assertRedirect(route('dashboard'));

    assertAuthenticatedAs($existing);
    expect($existing->refresh()->google_id)->toBe('google-123');
    expect(User::count())->toBe(1);
});

test('dev-login はテスト/ローカル環境でワンクリックログインできる', function () {
    get('/auth/dev-login')->assertRedirect(route('dashboard'));

    assertAuthenticated();
    expect(User::where('email', 'dev@example.com')->exists())->toBeTrue();
});

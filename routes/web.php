<?php

use App\Http\Controllers\Admin\QuestionImportController;
use App\Http\Controllers\AnswerController;
use App\Http\Controllers\Auth\DevLoginController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeagueController;
use App\Http\Controllers\LearnController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\MascotStyleController;
use App\Http\Controllers\MockExamAttemptController;
use App\Http\Controllers\MockExamController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect'])
        ->middleware('throttle:10,1')
        ->name('google.redirect');
    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])
        ->middleware('throttle:10,1')
        ->name('google.callback');

    if (app()->environment(['local', 'testing'])) {
        Route::get('auth/dev-login', DevLoginController::class)->name('dev.login');
    }
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::post('onboarding', [OnboardingController::class, 'update'])->name('onboarding.update');

    Route::get('learn', [LearnController::class, 'index'])->name('learn');
    Route::get('review', ReviewController::class)->name('review');
    Route::get('league', LeagueController::class)->name('league');
    Route::patch('rewards/mascot-style', [MascotStyleController::class, 'update'])->name('rewards.mascot-style.update');
    Route::get('mock-exams', [MockExamController::class, 'index'])->name('mock-exams.index');
    Route::post('mock-exams/{mockExam}/attempts', [MockExamAttemptController::class, 'store'])->middleware('throttle:10,1')->name('mock-attempts.store');
    Route::get('mock-attempts/{attempt}', [MockExamAttemptController::class, 'show'])->name('mock-attempts.show');
    Route::patch('mock-attempts/{attempt}', [MockExamAttemptController::class, 'update'])->middleware('throttle:120,1')->name('mock-attempts.update');
    Route::post('mock-attempts/{attempt}/finish', [MockExamAttemptController::class, 'finish'])->middleware('throttle:10,1')->name('mock-attempts.finish');
    Route::get('mock-attempts/{attempt}/result', [MockExamAttemptController::class, 'result'])->name('mock-attempts.result');
    Route::get('lessons/{lesson}', [LessonController::class, 'show'])->name('lessons.show');
    Route::post('lessons/{lesson}/complete', [LessonController::class, 'complete'])->middleware('throttle:20,1')->name('lessons.complete');
    Route::post('answers', [AnswerController::class, 'store'])->middleware('throttle:60,1')->name('answers.store');
});

require __DIR__.'/settings.php';

Route::post('admin/question-import', QuestionImportController::class)
    ->middleware(['auth', 'throttle:5,1'])
    ->name('admin.questions.import');

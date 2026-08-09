<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'google_id', 'avatar'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'reminder_enabled' => 'boolean',
            'sound_enabled' => 'boolean',
            'onboarded' => 'boolean',
            'exam_date' => 'date',
        ];
    }

    /** @return HasOne<UserStat, $this> */
    public function stat(): HasOne
    {
        return $this->hasOne(UserStat::class);
    }

    /** ユーザー統計を必ず取得する（無ければ作成） */
    public function statOrCreate(): UserStat
    {
        return $this->stat ?? $this->stat()->create();
    }

    /** @return HasMany<QuestionAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(QuestionAttempt::class);
    }

    /** @return HasMany<LessonProgress, $this> */
    public function lessonProgresses(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    /** @return HasMany<DailyActivity, $this> */
    public function dailyActivities(): HasMany
    {
        return $this->hasMany(DailyActivity::class);
    }

    /** @return HasMany<ReviewItem, $this> */
    public function reviewItems(): HasMany
    {
        return $this->hasMany(ReviewItem::class);
    }

    /** @return HasMany<DailyQuest, $this> */
    public function dailyQuests(): HasMany
    {
        return $this->hasMany(DailyQuest::class);
    }

    /** @return BelongsToMany<Badge, $this> */
    public function badges(): BelongsToMany
    {
        return $this->belongsToMany(Badge::class, 'user_badges')->withPivot('awarded_at');
    }
}

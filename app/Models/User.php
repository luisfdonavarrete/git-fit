<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

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
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * The answers the coach calculates targets from; null until the user sets the coach up.
     *
     * @return HasOne<CoachProfile, $this>
     */
    public function coachProfile(): HasOne
    {
        return $this->hasOne(CoachProfile::class);
    }

    /**
     * @return HasMany<DailyCheckIn, $this>
     */
    public function dailyCheckIns(): HasMany
    {
        return $this->hasMany(DailyCheckIn::class);
    }

    /**
     * The user's daily targets over time, newest first.
     *
     * @return HasMany<NutritionGoal, $this>
     */
    public function nutritionGoals(): HasMany
    {
        return $this->hasMany(NutritionGoal::class)->latest('effective_from');
    }

    /**
     * @return HasMany<MealEntry, $this>
     */
    public function mealEntries(): HasMany
    {
        return $this->hasMany(MealEntry::class);
    }

    /**
     * The goal in effect on the given day: the latest one starting on or before it.
     */
    public function goalOn(CarbonInterface $date): ?NutritionGoal
    {
        return $this->nutritionGoals()->whereDate('effective_from', '<=', $date->toDateString())->first();
    }

    /**
     * What the user ate on the given day versus the goal in effect, keyed by macro.
     *
     * @return array{date: string, totals: array<string, int|float>, targets: array<string, int|float|null>}
     */
    public function nutritionSummaryFor(CarbonInterface $date): array
    {
        $sums = $this->mealEntries()
            ->eaten()
            ->onDate($date)
            ->toBase()
            ->selectRaw(implode(', ', array_map(fn (string $macro): string => "sum({$macro}) as {$macro}", MealEntry::MACROS)))
            ->first();

        $goal = $this->goalOn($date);

        $totals = [];
        $targets = [];

        foreach (MealEntry::MACROS as $macro) {
            $isInteger = in_array($macro, MealEntry::INTEGER_MACROS, true);
            $sum = $sums->{$macro} ?? 0;
            $target = $goal?->getAttribute($macro);

            $totals[$macro] = $isInteger ? (int) $sum : round((float) $sum, 1);
            $targets[$macro] = $target === null ? null : ($isInteger ? (int) $target : (float) $target);
        }

        return [
            'date' => $date->toDateString(),
            'totals' => $totals,
            'targets' => $targets,
        ];
    }

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
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}

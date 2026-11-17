<?php

namespace App\Http\Controllers;

use App\Actions\Coach\BuildCoachWeeks;
use App\Actions\Coach\CalculateCoachTargets;
use App\Actions\Coach\PresentCoachTargets;
use App\Actions\Coach\SyncCoachGoal;
use App\Enums\MainGoal;
use App\Enums\Sex;
use App\Enums\TrainingExperience;
use App\Enums\WeightUnit;
use App\Http\Requests\CoachProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CoachProfileController extends Controller
{
    /**
     * Show the coach setup: the questions, and the targets they currently produce.
     */
    public function edit(Request $request, BuildCoachWeeks $buildCoachWeeks, CalculateCoachTargets $calculateCoachTargets, PresentCoachTargets $presentCoachTargets): Response
    {
        $today = $request->user()->localToday();
        $profile = $request->user()->coachProfile;
        $targets = null;

        if ($profile !== null) {
            $asOf = $today->max($profile->started_on);
            $targets = $presentCoachTargets->handle(
                $calculateCoachTargets->handle($profile, $buildCoachWeeks->handle($profile, $asOf), $asOf),
                $profile->weight_unit,
            );
        }

        return Inertia::render('coach/edit', [
            'profile' => $profile === null ? null : [
                'weight_unit' => $profile->weight_unit->value,
                'sex' => $profile->sex->value,
                'training_experience' => $profile->training_experience->value,
                'main_goal' => $profile->main_goal->value,
                'started_on' => $profile->started_on->toDateString(),
                'starting_weight' => round($profile->weight_unit->fromKg((float) $profile->starting_weight_kg), 1),
                'body_fat_pct' => (float) $profile->body_fat_pct,
                'weekly_change_override' => $profile->weekly_change_override_kg === null
                    ? null
                    : round($profile->weight_unit->fromKg((float) $profile->weekly_change_override_kg), 2),
            ],
            'targets' => $targets,
            'today' => $today->toDateString(),
            'options' => [
                'weightUnits' => WeightUnit::options(),
                'sexes' => Sex::options(),
                'trainingExperiences' => TrainingExperience::options(),
                'mainGoals' => MainGoal::options(),
            ],
        ]);
    }

    /**
     * Save the answers and recalculate this week's goal.
     */
    public function update(CoachProfileRequest $request, SyncCoachGoal $syncCoachGoal): RedirectResponse
    {
        $user = $request->user();
        $isFirstSetup = $user->coachProfile === null;

        $user->coachProfile()->updateOrCreate([], $request->profileAttributes());
        $syncCoachGoal->handle($user->refresh());

        Inertia::flash('toast', ['type' => 'success', 'message' => $isFirstSetup
            ? __('Your coach is set up. Here are this week\'s targets.')
            : __('Coach settings saved. This week\'s targets were updated.')]);

        return to_route('coach.edit');
    }
}

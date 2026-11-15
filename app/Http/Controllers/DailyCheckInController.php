<?php

namespace App\Http\Controllers;

use App\Actions\Coach\SyncCoachGoal;
use App\Http\Requests\DailyCheckInRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Date;

class DailyCheckInController extends Controller
{
    /**
     * Record a day's weigh-in and/or typed calorie total; clearing both removes the check-in.
     */
    public function update(DailyCheckInRequest $request, string $date, SyncCoachGoal $syncCoachGoal): RedirectResponse
    {
        $user = $request->user();
        $day = Date::createFromFormat('!Y-m-d', $date);

        abort_if($day === null || $day->toDateString() !== $date, 404);

        $checkIn = $user->dailyCheckIns()->whereDate('checked_on', $date)->firstOrNew(['checked_on' => $day]);
        $checkIn->fill($request->checkInAttributes());

        if ($checkIn->weight_kg === null && $checkIn->calories_override === null) {
            if ($checkIn->exists) {
                $checkIn->delete();
            }
        } else {
            $checkIn->save();
        }

        $syncCoachGoal->handle($user);

        return back();
    }
}

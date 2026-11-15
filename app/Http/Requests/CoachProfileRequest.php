<?php

namespace App\Http\Requests;

use App\Enums\MainGoal;
use App\Enums\Sex;
use App\Enums\TrainingExperience;
use App\Enums\WeightUnit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CoachProfileRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request. Weights are in the submitted unit.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isKg = $this->input('weight_unit') === WeightUnit::Kg->value;

        return [
            'weight_unit' => ['required', Rule::enum(WeightUnit::class)],
            'sex' => ['required', Rule::enum(Sex::class)],
            'training_experience' => ['required', Rule::enum(TrainingExperience::class)],
            'main_goal' => ['required', Rule::enum(MainGoal::class)],
            'started_on' => ['required', 'date_format:Y-m-d'],
            'starting_weight' => ['required', 'numeric', $isKg ? 'between:30,300' : 'between:66,660', 'decimal:0,2'],
            'body_fat_pct' => ['required', 'numeric', 'between:3,60', 'decimal:0,1'],
            'weekly_change_override' => ['nullable', 'numeric', $isKg ? 'between:-1.5,1.5' : 'between:-3,3', 'decimal:0,2'],
        ];
    }

    /**
     * Get custom attribute names.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'started_on' => 'start date',
            'starting_weight' => 'current weight',
            'body_fat_pct' => 'body fat',
            'weekly_change_override' => 'weekly change',
        ];
    }

    /**
     * The validated answers ready for the coach profile, with weights converted to kg.
     *
     * @return array<string, mixed>
     */
    public function profileAttributes(): array
    {
        $unit = $this->enum('weight_unit', WeightUnit::class) ?? WeightUnit::Lb;
        $override = $this->validated('weekly_change_override');

        return [
            'weight_unit' => $unit,
            'sex' => $this->validated('sex'),
            'training_experience' => $this->validated('training_experience'),
            'main_goal' => $this->validated('main_goal'),
            'started_on' => $this->validated('started_on'),
            'starting_weight_kg' => round($unit->toKg($this->float('starting_weight')), 2),
            'body_fat_pct' => $this->float('body_fat_pct'),
            'weekly_change_override_kg' => $override === null ? null : round($unit->toKg((float) $override), 3),
        ];
    }
}

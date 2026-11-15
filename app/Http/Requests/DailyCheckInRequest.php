<?php

namespace App\Http\Requests;

use App\Enums\WeightUnit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DailyCheckInRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request. Only the submitted fields change;
     * an empty value clears that field.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isKg = $this->weightUnit() === WeightUnit::Kg;

        return [
            'weight' => ['sometimes', 'nullable', 'numeric', $isKg ? 'between:30,300' : 'between:66,660', 'decimal:0,2'],
            'calories' => ['sometimes', 'nullable', 'integer', 'between:0,15000'],
        ];
    }

    /**
     * The unit the user weighs in with: their coach setting, pounds otherwise.
     */
    public function weightUnit(): WeightUnit
    {
        return $this->user()?->coachProfile->weight_unit ?? WeightUnit::Lb;
    }

    /**
     * The submitted fields mapped to check-in columns, weight converted to kg.
     *
     * @return array{weight_kg?: float|null, calories_override?: int|null}
     */
    public function checkInAttributes(): array
    {
        $attributes = [];

        if ($this->has('weight')) {
            $attributes['weight_kg'] = $this->filled('weight') ? round($this->weightUnit()->toKg($this->float('weight')), 2) : null;
        }

        if ($this->has('calories')) {
            $attributes['calories_override'] = $this->filled('calories') ? $this->integer('calories') : null;
        }

        return $attributes;
    }
}

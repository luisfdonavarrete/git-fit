<?php

namespace App\Http\Requests;

use App\Enums\MealType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMealEntryRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'recipe_version_id' => ['required', 'integer', Rule::exists('recipe_versions', 'id')],
            'meal_type' => ['required', Rule::enum(MealType::class)->only(MealType::diarySections())],
            'servings' => ['required', 'numeric', 'min:0.25', 'max:20', 'decimal:0,2'],
            'eaten_on' => ['required', 'date_format:Y-m-d'],
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
            'recipe_version_id' => 'recipe',
            'meal_type' => 'meal',
        ];
    }
}

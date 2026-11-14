<?php

namespace App\Http\Requests;

use App\Enums\MealType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMealEntryRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'meal_type' => ['sometimes', Rule::enum(MealType::class)->only(MealType::diarySections())],
            'servings' => ['required', 'numeric', 'min:0.25', 'max:20', 'decimal:0,2'],
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
            'meal_type' => 'meal',
        ];
    }
}

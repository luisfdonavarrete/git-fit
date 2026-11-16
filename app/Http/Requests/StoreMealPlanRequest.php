<?php

namespace App\Http\Requests;

use App\Enums\MealPlannerType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMealPlanRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $available = array_values(array_filter(MealPlannerType::cases(), fn (MealPlannerType $type): bool => $type->isAvailable()));

        return [
            'planner' => ['required', Rule::enum(MealPlannerType::class)->only($available)],
        ];
    }

    /**
     * The planner strategy the user chose.
     */
    public function plannerType(): MealPlannerType
    {
        return $this->enum('planner', MealPlannerType::class) ?? MealPlannerType::Search;
    }
}

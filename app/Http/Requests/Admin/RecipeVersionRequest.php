<?php

namespace App\Http\Requests\Admin;

use App\Enums\CalorieRange;
use App\Models\Recipe;
use App\Models\RecipeVersion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecipeVersionRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $recipe = $this->route('recipe');
        $version = $this->route('version');

        return [
            'calorie_range' => [
                'required',
                Rule::enum(CalorieRange::class),
                Rule::unique('recipe_versions', 'calorie_range')
                    ->where('recipe_id', $recipe instanceof Recipe ? $recipe->id : null)
                    ->ignore($version instanceof RecipeVersion ? $version->id : null),
            ],
            'calories' => ['required', 'integer', 'min:0', 'max:5000'],
            'prep_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'protein_g' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'carbs_g' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'fat_g' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'fiber_g' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'sugar_g' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'sodium_mg' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'notes' => ['nullable', 'string', 'max:5000'],

            'ingredients' => ['present', 'array', 'max:100'],
            'ingredients.*.name' => ['required', 'string', 'max:255'],
            'ingredients.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'ingredients.*.unit' => ['nullable', 'string', 'max:50'],
            'ingredients.*.note' => ['nullable', 'string', 'max:255'],

            'steps' => ['present', 'array', 'max:100'],
            'steps.*.instruction' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * Get custom attribute names for nested fields.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'ingredients.*.name' => 'ingredient name',
            'ingredients.*.quantity' => 'quantity',
            'steps.*.instruction' => 'step',
        ];
    }

    /**
     * Get custom error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'calorie_range.unique' => 'This recipe already has a version for that calorie range.',
        ];
    }
}

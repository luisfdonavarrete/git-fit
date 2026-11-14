<?php

namespace App\Http\Requests\Admin;

use App\Enums\MealType;
use App\Models\Recipe;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RecipeRequest extends FormRequest
{
    /**
     * Derive the slug from the name when none is given.
     */
    protected function prepareForValidation(): void
    {
        $slug = $this->input('slug');

        if (! is_string($slug) || trim($slug) === '') {
            $slug = is_string($this->input('name')) ? $this->input('name') : '';
        }

        $this->merge(['slug' => Str::slug($slug)]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $recipe = $this->route('recipe');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('recipes', 'slug')->ignore($recipe instanceof Recipe ? $recipe->id : null),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'image_path' => ['nullable', 'url', 'max:2048'],
            'meal_types' => ['nullable', 'array'],
            'meal_types.*' => ['distinct', Rule::enum(MealType::class)],
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
            'meal_types.*' => 'meal type',
        ];
    }

    /**
     * The selected meal types.
     *
     * @return list<MealType>
     */
    public function mealTypes(): array
    {
        return array_values($this->enums('meal_types', MealType::class));
    }
}

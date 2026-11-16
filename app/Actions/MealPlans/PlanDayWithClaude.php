<?php

namespace App\Actions\MealPlans;

use App\Enums\MealType;

/**
 * Experimental: ask Claude to choose the day's recipes from the same candidates the search
 * planner uses. GenerateMealPlan still validates every pick, so a made-up id never gets saved.
 */
class PlanDayWithClaude implements MealPlanner
{
    private const string SYSTEM_PROMPT = <<<'PROMPT'
        You plan one day of meals for a nutrition-tracking app.
        For each slot, pick exactly one recipe version from the candidates listed for that slot's meal, and use each recipe at most once across the day.
        The planned meals' total calories must fall inside calorie_window; within it, get as close to target_calories as you can and reach target_protein_g when possible.
        Prefer a varied, realistic day: a filling breakfast, balanced lunch and dinner, and lighter snacks.
        Reply with JSON only: one pick per slot, in slot order, plus a one-sentence note on the plan.
        PROMPT;

    public function __construct(private ClaudeMealPlanClient $client) {}

    public function plan(MealPlanRequest $request): array
    {
        $reply = json_decode($this->client->complete(self::SYSTEM_PROMPT, $this->prompt($request), $this->schema()), true);
        $picks = is_array($reply) && is_array($reply['picks'] ?? null) ? $reply['picks'] : null;

        if ($picks === null) {
            throw new MealPlanFailed(__('Claude returned a plan that couldn\'t be read. Try again.'));
        }

        $versionIds = [];

        foreach (array_keys($request->slots) as $index) {
            $pick = collect($picks)->first(fn (mixed $pick): bool => is_array($pick) && ($pick['slot'] ?? null) === $index);

            if (! is_array($pick) || ! is_int($pick['recipe_version_id'] ?? null)) {
                throw new MealPlanFailed(__('Claude skipped a meal. Try again.'));
            }

            $versionIds[] = $pick['recipe_version_id'];
        }

        return $versionIds;
    }

    private function prompt(MealPlanRequest $request): string
    {
        [$minCalories, $maxCalories] = $request->calorieWindow;

        return (string) json_encode([
            'target_calories' => $request->targetCalories,
            'calorie_window' => ['min' => $minCalories, 'max' => $maxCalories],
            'target_protein_g' => $request->targetProtein,
            'slots' => array_map(
                fn (int $index, MealType $slot): array => ['slot' => $index, 'meal' => $slot->value],
                array_keys($request->slots),
                $request->slots,
            ),
            'candidates' => array_map(fn (array $recipes): array => array_map(fn (array $recipe): array => [
                'recipe' => $recipe['name'],
                'versions' => array_map(fn (array $version): array => [
                    'recipe_version_id' => $version['id'],
                    'calories' => $version['calories'],
                    'protein_g' => $version['protein_g'],
                ], $recipe['versions']),
            ], $recipes), $request->candidates),
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'picks' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'slot' => ['type' => 'integer'],
                            'recipe_version_id' => ['type' => 'integer'],
                        ],
                        'required' => ['slot', 'recipe_version_id'],
                        'additionalProperties' => false,
                    ],
                ],
                'note' => ['type' => 'string'],
            ],
            'required' => ['picks', 'note'],
            'additionalProperties' => false,
        ];
    }
}

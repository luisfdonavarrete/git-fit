<?php

use App\Models\NutritionGoal;
use App\Models\User;

it('starts a new goal today and keeps earlier goals for past days', function () {
    $this->travelTo('2026-11-14 09:00');
    $user = User::factory()->create();
    NutritionGoal::factory()->for($user)->create(['effective_from' => '2026-11-01', 'calories' => 2000]);

    $this->actingAs($user)->post(route('nutrition-goal.store'), ['calories' => 2200, 'protein_g' => 160])->assertRedirect();

    expect($user->goalOn(today())?->calories)->toBe(2200)
        ->and($user->goalOn(today()->subDay())?->calories)->toBe(2000);
});

it('updates today\'s goal instead of adding a second one', function () {
    $this->travelTo('2026-11-14 09:00');
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('nutrition-goal.store'), ['calories' => 2200, 'protein_g' => 160]);
    $this->actingAs($user)->post(route('nutrition-goal.store'), ['calories' => 1900, 'protein_g' => 140]);

    expect($user->nutritionGoals()->sole()->calories)->toBe(1900);
});

it('rejects unrealistic goals', function (array $input, string $field) {
    $response = $this->actingAs(User::factory()->create())
        ->post(route('nutrition-goal.store'), [...['calories' => 2000, 'protein_g' => 150], ...$input]);

    $response->assertSessionHasErrors($field);
})->with([
    'too few calories' => [['calories' => 500], 'calories'],
    'too much protein' => [['protein_g' => 600], 'protein_g'],
    'missing protein' => [['protein_g' => ''], 'protein_g'],
]);

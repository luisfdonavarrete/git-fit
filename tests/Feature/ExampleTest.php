<?php

use App\Models\User;

test('guests are sent from the home page to the login page', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect(route('login'));
});

test('signed-in users are sent from the home page to the dashboard', function () {
    $response = $this->actingAs(User::factory()->create())->get(route('home'));

    $response->assertRedirect(route('dashboard'));
});

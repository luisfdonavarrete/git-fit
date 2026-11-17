<?php

use App\Models\User;

it('remembers the browser timezone on the signed-in user', function () {
    $user = User::factory()->create(['timezone' => null]);

    $this->actingAs($user)
        ->withUnencryptedCookie('timezone', 'America/New_York')
        ->get(route('dashboard'))
        ->assertOk();

    expect($user->refresh()->timezone)->toBe('America/New_York');
});

it('ignores a timezone that does not exist', function () {
    $user = User::factory()->create(['timezone' => 'America/New_York']);

    $this->actingAs($user)
        ->withUnencryptedCookie('timezone', 'Mars/Base')
        ->get(route('dashboard'))
        ->assertOk();

    expect($user->refresh()->timezone)->toBe('America/New_York');
});

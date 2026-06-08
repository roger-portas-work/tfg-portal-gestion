<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('cliente users can visit the dashboard', function () {
    $user = clienteUser();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('gestor users are redirected from the cliente dashboard to admin', function () {
    $user = User::factory()->create([
        'role' => User::ROLE_GESTOR,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertRedirect('/admin');
});

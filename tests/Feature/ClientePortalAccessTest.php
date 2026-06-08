<?php

use App\Models\User;

test('gestor users are redirected away from cliente portal settings', function () {
    $user = User::factory()->create([
        'role' => User::ROLE_GESTOR,
    ]);

    $this->actingAs($user);

    $response = $this->get(route('profile.edit'));
    $response->assertRedirect('/admin');
});

test('cliente users can access cliente portal settings', function () {
    $this->actingAs(clienteUser());

    $response = $this->get(route('profile.edit'));
    $response->assertOk();
});

<?php

use App\Actions\Fortify\CreateNewUser;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

test('public registration is disabled', function () {
    expect(config('fortify.features'))
        ->not->toContain(Features::registration());

    expect(Route::has('register'))->toBeFalse();
    expect(Route::has('register.store'))->toBeFalse();
});

test('fortify user creation assigns the cliente role', function () {
    $user = app(CreateNewUser::class)->create([
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    expect($user)
        ->role->toBe(User::ROLE_CLIENTE);
});

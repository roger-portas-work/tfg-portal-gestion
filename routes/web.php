<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::livewire('drones', 'pages::drones.index')->name('drones.index');
    Route::livewire('operadora', 'pages::operadora.index')->name('operadora.index');
});

require __DIR__.'/settings.php';

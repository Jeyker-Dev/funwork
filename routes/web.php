<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {;
    return redirect(route('login'));
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::view('projects', 'app.projects')->name('projects');
});

require __DIR__.'/settings.php';

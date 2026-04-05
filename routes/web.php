<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {;
    return redirect(route('login'));
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::view('projects', 'app.projects.index')->name('projects');
    Route::view('projects/{project}', 'app.projects.show')->name('projects.show');
    Route::view('projects/{project}/inbox', 'app.projects.inbox')->name('project.inbox');
    Route::view('projects/{project}/tasks', 'app.projects.tasks')->name('project.tasks');
});

require __DIR__.'/settings.php';

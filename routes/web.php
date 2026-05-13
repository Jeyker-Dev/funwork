<?php

use Illuminate\Support\Facades\Route;

Route::get('/', static function () {
    if (Auth::check()) {
        return redirect(route('projects'));
    }

    return redirect(route('login'));
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('/projects', 'pages::projects.index')->name('projects');
    Route::view('projects/{project}', 'app.projects.show')->name('projects.show');
    Route::view('projects/{project}/inbox', 'app.projects.inbox')->name('project.inbox');
    Route::view('projects/{project}/tasks', 'app.projects.tasks.index')->name('project.tasks');
    Route::view('projects/{project}/tasks/{task}', 'app.projects.tasks.show')->name('show.task');
    Route::livewire('/projects/{project}/notebooks', 'pages::projects.notebooks.index')->name('project.notebooks');
});

require __DIR__.'/settings.php';

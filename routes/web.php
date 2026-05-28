<?php

use Illuminate\Support\Facades\Route;
use App\Models\Project;
use App\Models\Task;

Route::get('/', static function () {
    if (Auth::check()) {
        return redirect(route('projects'));
    }

    return redirect(route('login'));
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('/projects', 'pages::projects.index')->name('projects');

    Route::get('projects/{project}', static function (Project $project) {
        return view('app.projects.show', compact('project'));
    })->name('projects.show');

    Route::get('projects/{project}/inbox', static function (Project $project) {
        return view('app.projects.inbox', compact('project'));
    })->name('project.inbox');

    Route::get('projects/{project}/tasks', static function (Project $project) {
        return view('app.projects.tasks.index', compact('project'));
    })->name('project.tasks');

    Route::get('projects/{project}/tasks/{task}', static function (Project $project, Task $task) {
        return view('app.projects.tasks.show', compact('project', 'task'));
    })->name('show.task');

    Route::livewire('/projects/{project}/notebooks', 'pages::projects.notebooks.index')->name('project.notebooks');
});

require __DIR__.'/settings.php';

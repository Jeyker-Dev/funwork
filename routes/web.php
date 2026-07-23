<?php

use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Facades\Route;

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
    Route::livewire('/projects/{project}/files', 'pages::projects.files.index')->name('project.files');
    Route::get('/projects/{project}/files/{projectFile}/preview', \App\Http\Controllers\ProjectFilePreviewController::class)->name('project.files.preview');
    Route::livewire('/projects/{project}/notebooks/{notebook}/pages/{page}/edit', 'pages::projects.notebooks.pages.editor')->name('project.notebooks.pages.edit');
});

require __DIR__.'/settings.php';

<?php

use App\Http\Controllers\ProjectFilePreviewController;
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

    Route::livewire('projects/{project}', 'pages::projects.show')
        ->can('view', 'project')
        ->name('projects.show');

    Route::livewire('/projects/{project}/inbox', 'pages::projects.inbox')
        ->can('view', 'project')
        ->name('project.inbox');

    Route::get('projects/{project}/tasks', static function (Project $project) {
        return view('app.projects.tasks.index', compact('project'));
    })->can('view', 'project')->name('project.tasks');

    Route::get('projects/{project}/tasks/{task}', static function (Project $project, Task $task) {
        return view('app.projects.tasks.show', compact('project', 'task'));
    })->can('view', 'project')->can('view', 'task')->name('show.task');

    Route::livewire('/projects/{project}/notebooks', 'pages::projects.notebooks.index')
        ->can('view', 'project')
        ->name('project.notebooks');
    Route::livewire('/projects/{project}/files', 'pages::projects.files.index')
        ->can('view', 'project')
        ->name('project.files');
    Route::get('/projects/{project}/files/{projectFile}/preview', ProjectFilePreviewController::class)
        ->can('view', 'project')
        ->can('view', 'projectFile')
        ->name('project.files.preview');
    Route::livewire('/projects/{project}/notebooks/{notebook}/pages/{page}/edit', 'pages::projects.notebooks.pages.editor')
        ->can('view', 'project')
        ->can('view', 'notebook')
        ->can('update', 'page')
        ->name('project.notebooks.pages.edit');
});

require __DIR__.'/settings.php';

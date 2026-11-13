<?php

use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\NotebookExportController;
use App\Http\Controllers\ProjectFilePreviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', static function () {
    if (Auth::check()) {
        return redirect(route('projects'));
    }

    return redirect(route('login'));
});

// Public: Google's OAuth consent screen links here.
Route::view('/privacy', 'privacy')->name('privacy');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('/projects', 'pages::projects.index')->name('projects');

    Route::livewire('projects/{project}', 'pages::projects.show')
        ->can('view', 'project')
        ->name('projects.show');

    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('google.callback');

    Route::get('/projects/{project}/notebooks/{notebook}/export', NotebookExportController::class)
        ->scopeBindings()
        ->can('view', 'project')
        ->can('view', 'notebook')
        ->name('project.notebooks.export');

    Route::livewire('/projects/{project}/inbox', 'pages::projects.inbox')
        ->can('view', 'project')
        ->name('project.inbox');

    Route::livewire('/projects/{project}/tasks', 'pages::projects.tasks.index')
        ->name('project.tasks')
        ->can('view', 'project');

    Route::livewire('/projects/{project}/tasks/{task}', 'pages::projects.tasks.show')
        ->name('show.task')
        ->scopeBindings()
        ->can('view', 'project')
        ->can('view', 'task');

    Route::livewire('/projects/{project}/notebooks', 'pages::projects.notebooks.index')
        ->can('view', 'project')
        ->name('project.notebooks');
    Route::livewire('/projects/{project}/decisions', 'pages::projects.decisions.index')
        ->can('view', 'project')
        ->name('project.decisions');
    Route::livewire('/projects/{project}/decisions/{decision}', 'pages::projects.decisions.show')
        ->scopeBindings()
        ->can('view', 'project')
        ->can('view', 'decision')
        ->name('project.decisions.show');
    Route::livewire('/projects/{project}/files', 'pages::projects.files.index')
        ->can('view', 'project')
        ->name('project.files');
    Route::get('/projects/{project}/files/{projectFile}/preview', ProjectFilePreviewController::class)
        ->can('view', 'project')
        ->can('view', 'projectFile')
        ->name('project.files.preview');
    Route::livewire('/projects/{project}/notebooks/{notebook}/pages/{page}/edit', 'pages::projects.notebooks.pages.editor')
        ->scopeBindings()
        ->can('view', 'project')
        ->can('view', 'notebook')
        ->can('update', 'page')
        ->name('project.notebooks.pages.edit');
});

require __DIR__.'/settings.php';

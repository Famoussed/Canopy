<?php

use Illuminate\Support\Facades\Route;

// ─── Guest Routes ───

Route::middleware('guest')->group(function () {
    Route::livewire('/login', 'auth.login')->name('login');
    Route::livewire('/register', 'auth.register')->name('register');
});

// ─── Authenticated Routes ───

Route::middleware('auth')->group(function () {
    Route::redirect('/', '/dashboard');

    Route::livewire('/dashboard', 'dashboard')->name('dashboard');

    // Project creation
    Route::livewire('/projects/create', 'projects.create-project')->name('projects.create');

    // Project-scoped routes
    Route::prefix('/projects/{project:slug}')->group(function () {
        Route::livewire('/', 'projects.project-dashboard')->name('projects.show');
        Route::livewire('/backlog', 'scrum.backlog')->name('projects.backlog');
        Route::livewire('/board', 'scrum.kanban-board')->name('projects.board');
        Route::livewire('/sprints', 'scrum.sprint-list')->name('projects.sprints');
        Route::livewire('/epics', 'scrum.epic-list')->name('projects.epics');
        Route::livewire('/stories/{story}', 'scrum.story-detail')->name('projects.stories.show');
        Route::livewire('/issues', 'issues.issue-list')->name('projects.issues');
        Route::livewire('/analytics', 'analytics.analytics-dashboard')->name('projects.analytics');
        Route::livewire('/settings', 'projects.project-settings')->name('projects.settings');
    });
});

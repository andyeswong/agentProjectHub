<?php

use App\Http\Controllers\Web\AgentWebController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\PilotSessionController;
use App\Http\Controllers\Web\ProjectWebController;
use App\Http\Controllers\Web\TaskWebController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Root redirect
Route::get('/', fn() => redirect()->route(
    session('pilot_session_token') ? 'dashboard' : 'login'
));

// Guest routes
Route::middleware('pilot.guest')->group(function () {
    Route::get('/login', fn() => Inertia::render('Auth/Login'))
        ->name('login');
    Route::post('/login', [PilotSessionController::class, 'store'])
        ->name('login.store');
    Route::get('/register', fn() => Inertia::render('Auth/Register'))
        ->name('register');
});

// Logout
Route::post('/logout', [PilotSessionController::class, 'destroy'])
    ->name('logout');

// Protected routes
Route::middleware('pilot.auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/projects', [ProjectWebController::class, 'index'])->name('projects.index');
    Route::get('/projects/{id}', [ProjectWebController::class, 'show'])->name('projects.show');
    Route::get('/tasks/{id}', [TaskWebController::class, 'show'])->name('tasks.show');
    Route::get('/agents', [AgentWebController::class, 'index'])->name('agents.index');
});

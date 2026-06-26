<?php

use App\Http\Controllers\Web\AgentWebController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\MemoryWebController;
use App\Http\Controllers\Web\PilotSessionController;
use App\Http\Controllers\Web\ProjectWebController;
use App\Http\Controllers\Web\PublicDashboardController;
use App\Http\Controllers\Web\TaskWebController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Public boards — disabled to prevent cross-workspace data leakage
// Re-enable by uncommenting and removing the redirect below
Route::get('/board',        fn() => redirect()->route('login'))->name('public.boards');
Route::get('/board/{slug}', fn() => redirect()->route('login'))->name('public.board');

// Root redirect
Route::get('/', fn() => redirect()->route(
    session('pilot_session_token') ? 'dashboard' : 'login'
));

// Guest routes
Route::middleware('pilot.guest')->group(function () {
    Route::get('/login',  [PilotSessionController::class, 'show'])->name('login');
    Route::post('/login', [PilotSessionController::class, 'store'])->name('login.store');
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
    Route::post('/projects', [ProjectWebController::class, 'store'])->name('projects.store');
    Route::get('/projects/{id}', [ProjectWebController::class, 'show'])->name('projects.show');
    Route::patch('/projects/{id}', [ProjectWebController::class, 'update'])->name('projects.update');
    Route::post('/projects/{id}/tasks', [ProjectWebController::class, 'createTask'])->name('projects.tasks.create');
    Route::get('/tasks/{id}', [TaskWebController::class, 'show'])->name('tasks.show');
    Route::patch('/tasks/{id}', [TaskWebController::class, 'update'])->name('tasks.update');
    Route::post('/tasks/{id}/comments', [TaskWebController::class, 'comment'])->name('tasks.comment');
    Route::get('/agents', [AgentWebController::class, 'index'])->name('agents.index');
    Route::patch('/agents/{id}', [AgentWebController::class, 'update'])->name('agents.update');
    Route::post('/agents/{id}/revoke', [AgentWebController::class, 'revoke'])->name('agents.revoke');
    Route::post('/agents/{id}/restore', [AgentWebController::class, 'restore'])->name('agents.restore');
    Route::get('/memory', [MemoryWebController::class, 'index'])->name('memory.index');
    Route::get('/memory/{id}/reveal', [MemoryWebController::class, 'reveal'])->name('memory.reveal');
    Route::get('/memory/{id}', [MemoryWebController::class, 'show'])->name('memory.show');
    Route::post('/memory/{id}/integrate', [MemoryWebController::class, 'integrate'])->name('memory.integrate');
});

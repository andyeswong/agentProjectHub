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
    Route::get('/projects/{id}', [ProjectWebController::class, 'show'])->name('projects.show');
    Route::get('/tasks/{id}', [TaskWebController::class, 'show'])->name('tasks.show');
    Route::get('/agents', [AgentWebController::class, 'index'])->name('agents.index');
    Route::get('/memory', [MemoryWebController::class, 'index'])->name('memory.index');
    Route::get('/memory/{id}/reveal', [MemoryWebController::class, 'reveal'])->name('memory.reveal');
});

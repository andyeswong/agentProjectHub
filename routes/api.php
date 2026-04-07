<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CommentController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\MemoryController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\SchemaController;
use App\Http\Controllers\Api\V1\TaskController;
use Illuminate\Support\Facades\Route;

// Public — no auth required
Route::get('/v1/health', HealthController::class);
Route::get('/v1/schema', SchemaController::class);

// Auth — public endpoints
Route::prefix('v1/auth')->group(function () {
    Route::post('/register',     [AuthController::class, 'register']);
    Route::post('/token',        [AuthController::class, 'token']);
    Route::post('/pilot-login',  [AuthController::class, 'pilotLogin']);
});

// Auth — protected endpoints
Route::prefix('v1/auth')->middleware('api.auth')->group(function () {
    Route::get('/me',            [AuthController::class, 'me']);
    Route::post('/pilot-token',  [AuthController::class, 'pilotToken']);
});

// Protected API
Route::middleware('api.auth')->prefix('v1')->group(function () {
    // Rate limit: 120 req/min per API key (resolved from rate_limit field)
    Route::middleware('throttle:api')->group(function () {

        // Organizations & Workspaces
        Route::get('/organizations',                                  [OrganizationController::class, 'index']);
        Route::get('/organizations/{slug}/workspaces',                [OrganizationController::class, 'workspaces']);
        Route::post('/organizations/{slug}/workspaces',               [OrganizationController::class, 'createWorkspace']);

        // Projects
        Route::get('/projects',                                       [ProjectController::class, 'index']);
        Route::post('/projects',                                      [ProjectController::class, 'store']);
        Route::get('/projects/{id}',                                  [ProjectController::class, 'show']);
        Route::patch('/projects/{id}',                                [ProjectController::class, 'update']);

        // Tasks
        Route::get('/projects/{id}/tasks',                            [TaskController::class, 'index']);
        Route::post('/projects/{id}/tasks',                           [TaskController::class, 'store']);
        Route::post('/projects/{id}/tasks/batch',                     [TaskController::class, 'batch']);
        Route::get('/tasks/{id}',                                     [TaskController::class, 'show']);
        Route::patch('/tasks/{id}',                                   [TaskController::class, 'update']);
        Route::post('/tasks/{id}/archive',                            [TaskController::class, 'archive']);
        Route::post('/tasks/{id}/unarchive',                          [TaskController::class, 'unarchive']);

        // Comments
        Route::post('/tasks/{id}/comments',                           [CommentController::class, 'store']);

        // Events
        Route::get('/events',                                         [EventController::class, 'index']);

        // Shared Agent Memory (vector search via mxbai-embed-large @ Ollama)
        Route::get('/memory',                                         [MemoryController::class, 'index']);
        Route::post('/memory',                                        [MemoryController::class, 'store']);
        Route::post('/memory/search',                                 [MemoryController::class, 'search']);
        Route::put('/memory/key/{key}',                               [MemoryController::class, 'upsertByKey']);
        Route::get('/memory/{id}',                                    [MemoryController::class, 'show']);
        Route::put('/memory/{id}',                                    [MemoryController::class, 'update']);
        Route::delete('/memory/{id}',                                 [MemoryController::class, 'destroy']);
    });
});

<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\MaterialController;
use App\Http\Controllers\Api\AgentController;

use App\Http\Controllers\Api\AuthController;

// Public Routes
Route::post('/login', [AuthController::class, 'login']);

// Protected Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Task Routes
    Route::apiResource('tasks', TaskController::class);

    // Schedule Routes
    Route::apiResource('schedules', ScheduleController::class);

    // Material Routes
    Route::post('/materials/upload', [MaterialController::class, 'upload']);
    Route::apiResource('materials', MaterialController::class);

    // Chatbot Route
    Route::post('/chat', [AgentController::class, 'chat']);
});

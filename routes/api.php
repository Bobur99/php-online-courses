<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CategoryController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AuthController::class, 'register'])->name('register');
Route::post('/auth/login', [AuthController::class, 'login'])->name('login');
Route::post('/auth/logout', [AuthController::class, 'logout'])->name('logout')
    ->middleware('auth:sanctum');
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/profile', [AuthController::class, 'profile'])
        ->name('profile');

    Route::patch('/profile', [AuthController::class, 'updateProfile'])
        ->name('updateProfile');

    Route::patch('/profile/password', [AuthController::class, 'changePassword'])
        ->name('changePassword');
});
Route::middleware('auth:sanctum')
    ->prefix('admin')
    ->group(function (): void {
        Route::get('/users', [UserController::class, 'index'])
            ->name('admin.users.index');

        Route::patch('/users/{user}/role', [UserController::class, 'updateRole'])
            ->name('admin.users.role');

        Route::patch('/users/{user}/block', [UserController::class, 'block'])
            ->name('admin.users.block');
    });

Route::get('/categories', [CategoryController::class, 'index']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::patch('/categories/{category}', [CategoryController::class, 'update']);
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);
});

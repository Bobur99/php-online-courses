<?php

use App\Http\Controllers\Auth\AuthController;
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
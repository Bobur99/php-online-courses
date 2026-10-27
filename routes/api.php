<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\LessonProgressController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\PurchaseController;
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
        Route::get('/courses', [CourseController::class, 'adminIndex'])
            ->name('admin.courses.index');

        Route::get('/users', [UserController::class, 'index'])
            ->name('admin.users.index');

        Route::patch('/users/{user}/role', [UserController::class, 'updateRole'])
            ->name('admin.users.role');

        Route::patch('/users/{user}/block', [UserController::class, 'block'])
            ->name('admin.users.block');
    });

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/teacher/courses', [CourseController::class, 'teacherIndex'])
        ->name('teacher.courses.index');

    Route::get('/student/courses', [CourseController::class, 'studentIndex'])
        ->name('student.courses.index');
});

Route::get('/categories', [CategoryController::class, 'index']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::patch('/categories/{category}', [CategoryController::class, 'update']);
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);
});

Route::get('/courses', [CourseController::class, 'index']);
Route::get('/courses/{course}', [CourseController::class, 'show']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/courses', [CourseController::class, 'store']);
    Route::patch('/courses/{course}', [CourseController::class, 'update']);
    Route::delete('/courses/{course}', [CourseController::class, 'destroy']);
    Route::post('/courses/{course}/publish', [CourseController::class, 'publish']);
    Route::post('/courses/{course}/unpublish', [CourseController::class, 'unpublish']);
});

Route::get('/courses/{course}/lessons', [LessonController::class, 'index']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/courses/{course}/lessons', [LessonController::class, 'store']);
    Route::patch('/lessons/{lesson}', [LessonController::class, 'update']);
    Route::delete('/lessons/{lesson}', [LessonController::class, 'destroy']);
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post(
        '/courses/{course}/purchase',
        [PurchaseController::class, 'store']
    );
});
Route::post(
    '/webhooks/payment',
    [PaymentWebhookController::class, 'handle']
);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post(
        '/lessons/{lesson}/complete',
        [LessonProgressController::class, 'complete']
    );
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get(
        '/certificates',
        [CertificateController::class, 'index']
    );

    Route::get(
        '/certificates/{certificate}/download',
        [CertificateController::class, 'download']
    );
});

<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SubmissionController;
use Illuminate\Support\Facades\Route;

// Public Article and Taxonomy endpoints (No auth required)
Route::get('/articles', [ArticleController::class, 'index']);
Route::get('/articles/{slug}', [ArticleController::class, 'show']);
Route::get('/categories', [ArticleController::class, 'categories']);
Route::get('/tags', [ArticleController::class, 'tags']);

// Public Auth routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,60');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
    Route::get('/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])->middleware('signed')->name('verification.verify');
    Route::middleware('firestore.auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/email/verification-notification', [AuthController::class, 'sendVerification'])->middleware('throttle:6,1');
        Route::patch('/profile', [AuthController::class, 'updateProfile']);
    });
});

// Protected Editorial and User routes
Route::middleware('firestore.auth')->group(function () {
    Route::get('/submissions', [SubmissionController::class, 'index']);
    Route::post('/submissions', [SubmissionController::class, 'store'])->middleware('role:CONTRIBUTOR,EDITOR,ADMIN');
    Route::get('/submissions/{submission}', [SubmissionController::class, 'show']);
    Route::match(['put', 'patch'], '/submissions/{submission}', [SubmissionController::class, 'update']);
    Route::delete('/submissions/{submission}', [SubmissionController::class, 'destroy']);
    Route::post('/submissions/{submission}/review', [SubmissionController::class, 'review'])->middleware('role:EDITOR,ADMIN');

    Route::get('/notifications', [AdminController::class, 'notifications']);
    Route::patch('/notifications', [AdminController::class, 'readNotifications']);
    Route::get('/users/profile', [AuthController::class, 'me']);
    Route::patch('/users/profile', [AuthController::class, 'updateProfile']);

    Route::middleware('role:EDITOR,ADMIN')->prefix('admin')->group(function () {
        Route::get('/stats', [AdminController::class, 'stats']);
        Route::get('/newsletter', [AdminController::class, 'newsletterExport']);
    });
    Route::middleware('role:ADMIN')->prefix('admin')->group(function () {
        Route::get('/users', [AdminController::class, 'users']);
        Route::patch('/users/{user}', [AdminController::class, 'updateUser']);
    });
});

Route::post('/newsletter', [AdminController::class, 'subscribe'])->middleware('throttle:5,1');
Route::get('/health', fn () => response()->json(['status' => 'ok', 'cms' => 'internal', 'wp_optional' => true]));

<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// Authentication routes (login, logout, password reset) are registered by Fortify.

Route::middleware('auth')->group(function () {
    Route::get('/', HomeController::class)->name('home');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');

    Route::prefix('admin')->name('admin.')->middleware('can:access-admin')->group(function () {
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
        Route::resource('categories', CategoryController::class)->except(['show']);
    });
});

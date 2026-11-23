<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// Authentication routes (login, logout, password reset) are registered by Fortify.

Route::middleware('auth')->group(function () {
    Route::redirect('/', '/profile')->name('home');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
});

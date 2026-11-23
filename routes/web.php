<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TicketAssignmentController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketStatusController;
use App\Http\Controllers\TicketTriageController;
use Illuminate\Support\Facades\Route;

// Authentication routes (login, logout, password reset) are registered by Fortify.

Route::middleware('auth')->group(function () {
    Route::get('/', HomeController::class)->name('home');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');

    Route::resource('tickets', TicketController::class)->only(['index', 'create', 'store', 'show']);
    Route::patch('/tickets/{ticket}/status', [TicketStatusController::class, 'update'])->name('tickets.status.update');
    Route::patch('/tickets/{ticket}/triage', [TicketTriageController::class, 'update'])->name('tickets.triage.update');
    Route::patch('/tickets/{ticket}/assign', [TicketAssignmentController::class, 'update'])->name('tickets.assign.update');

    // The technician queue is a Livewire component embedded in a plain Blade view.
    Route::view('/queue', 'queue.index')->middleware('can:view-queue')->name('queue.index');

    Route::prefix('admin')->name('admin.')->middleware('can:access-admin')->group(function () {
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
        Route::resource('categories', CategoryController::class)->except(['show']);
    });
});

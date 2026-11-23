<?php

use App\Http\Controllers\Admin\AiLogController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\KbArticleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AiSuggestionController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\KbController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TicketAssignmentController;
use App\Http\Controllers\TicketAttachmentController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketRatingController;
use App\Http\Controllers\TicketStatusController;
use App\Http\Controllers\TicketTriageController;
use Illuminate\Support\Facades\Route;

// Authentication routes (login, logout, password reset) are registered by Fortify.

Route::middleware('auth')->group(function () {
    Route::get('/', HomeController::class)->name('home');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');

    Route::resource('tickets', TicketController::class)->only(['index', 'create', 'store', 'show'])
        ->middlewareFor('store', 'throttle:ticket-creation');
    Route::patch('/tickets/{ticket}/status', [TicketStatusController::class, 'update'])->name('tickets.status.update');
    Route::patch('/tickets/{ticket}/triage', [TicketTriageController::class, 'update'])->name('tickets.triage.update');
    Route::patch('/tickets/{ticket}/assign', [TicketAssignmentController::class, 'update'])->name('tickets.assign.update');
    Route::post('/tickets/{ticket}/attachments', [TicketAttachmentController::class, 'store'])->name('tickets.attachments.store');
    // scopeBindings: the attachment must belong to the ticket of the address, or the route is a 404.
    Route::get('/tickets/{ticket}/attachments/{attachment}', [TicketAttachmentController::class, 'show'])
        ->scopeBindings()
        ->name('tickets.attachments.show');
    Route::post('/tickets/{ticket}/rating', [TicketRatingController::class, 'store'])->name('tickets.rating.store');
    Route::post('/tickets/{ticket}/suggestion/accept', [AiSuggestionController::class, 'accept'])->name('tickets.suggestion.accept');
    Route::post('/tickets/{ticket}/suggestion/reject', [AiSuggestionController::class, 'reject'])->name('tickets.suggestion.reject');

    // The technician queue is a Livewire component embedded in a plain Blade view.
    Route::view('/queue', 'queue.index')->middleware('can:view-queue')->name('queue.index');

    // The knowledge base, read-only. Employees see the help center only (see KbController).
    Route::get('/kb', [KbController::class, 'index'])->name('kb.index');
    Route::get('/kb/{kbArticle}', [KbController::class, 'show'])->name('kb.show');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::get('/notifications/{notification}', [NotificationController::class, 'show'])->name('notifications.show');

    Route::prefix('admin')->name('admin.')->middleware('can:access-admin')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/ai-logs', [AiLogController::class, 'index'])->name('ai-logs.index');
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
        Route::resource('categories', CategoryController::class)->except(['show']);
        Route::resource('kb-articles', KbArticleController::class)->except(['show']);
    });
});

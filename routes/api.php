<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\TicketController;
use App\Http\Controllers\Api\V1\TicketMessageController;
use App\Http\Controllers\Api\V1\TicketStatusController;
use Illuminate\Support\Facades\Route;

/*
 * REST API of the mobile application for technicians. Documented in docs/API.md.
 * Every route is prefixed with /api (see bootstrap/app.php) and versioned with /v1.
 */

Route::prefix('v1')->name('api.v1.')->group(function () {
    // Five attempts per minute and per IP address.
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login');

    // A Sanctum token, held by an active technician.
    Route::middleware(['auth:sanctum', 'can:use-mobile-api'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/me', [AuthController::class, 'me'])->name('me');

        Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
        Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
        Route::patch('/tickets/{ticket}/status', [TicketStatusController::class, 'update'])->name('tickets.status.update');
        Route::post('/tickets/{ticket}/messages', [TicketMessageController::class, 'store'])->name('tickets.messages.store');
    });
});

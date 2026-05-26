<?php

use App\Http\Controllers\BillController;
use App\Http\Controllers\DemoController;
use App\Http\Controllers\FiuuPaymentController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\ManualPaymentController;
use App\Http\Controllers\OrganizerAuthController;
use App\Http\Controllers\ParticipantPaymentController;
use App\Http\Controllers\PaymentLogController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\SplitController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/', LandingController::class)->name('home');
Route::get('/demo', DemoController::class)->name('demo');

// Participant payment (public, token-based)
Route::get('/pay/{participantPayment}', [ParticipantPaymentController::class, 'show'])->name('pay.show');
Route::post('/pay/{participantPayment}', [ParticipantPaymentController::class, 'pay'])->name('pay.process');

// Fiuu callbacks (public, webhook)
Route::post('/fiuu/callback', [FiuuPaymentController::class, 'callback'])->name('fiuu.callback');
Route::get('/fiuu/return', [FiuuPaymentController::class, 'return'])->name('fiuu.return');

// Organizer authentication routes
Route::get('/organizer/login', [OrganizerAuthController::class, 'showLoginForm'])->name('organizer.login');
Route::post('/organizer/login', [OrganizerAuthController::class, 'login'])->name('organizer.login.post');
Route::post('/organizer/logout', [OrganizerAuthController::class, 'logout'])->name('organizer.logout');

// Organizer routes (require authentication)
Route::middleware(['auth:organizer'])->group(function () {
    // Bill CRUD
    Route::get('/bills/create', [BillController::class, 'create'])->name('bills.create');
    Route::post('/bills', [BillController::class, 'store'])->name('bills.store');
    Route::get('/bills/{bill}', [BillController::class, 'show'])->name('bills.show');
    Route::post('/bills/{bill}/publish', [BillController::class, 'publish'])->name('bills.publish');
    Route::get('/bills/{bill}/edit', [BillController::class, 'edit'])->name('bills.edit');
    Route::put('/bills/{bill}', [BillController::class, 'update'])->name('bills.update');
    Route::delete('/bills/{bill}', [BillController::class, 'destroy'])->name('bills.destroy');

    // Receipt
    Route::post('/bills/{bill}/receipt', [ReceiptController::class, 'store'])->name('receipt.store');
    Route::get('/bills/{bill}/receipt/items', [ReceiptController::class, 'items'])->name('receipt.items');

    // Splits
    Route::get('/bills/{bill}/splits', [SplitController::class, 'index'])->name('splits.index');
    Route::post('/bills/{bill}/splits', [SplitController::class, 'store'])->name('splits.store');
    Route::put('/bills/{bill}/splits/{split}', [SplitController::class, 'update'])->name('splits.update');

    // Manual payment confirmation
    Route::post('/manual-payment/confirm', [ManualPaymentController::class, 'store'])->name('manual-payment.confirm');

    // Payment logs
    Route::get('/payment-logs', [PaymentLogController::class, 'index'])->name('payment-logs.index');
});

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
Route::post('/organizer/login', [OrganizerAuthController::class, 'login'])->name('organizer.login.store');
Route::post('/organizer/logout', [OrganizerAuthController::class, 'logout'])->name('organizer.logout');

// Organizer routes (authenticated via session)
Route::middleware(['organizer'])->group(function () {
    Route::get('/bills/create', [BillController::class, 'create'])->name('bills.create');
    Route::post('/bills', [BillController::class, 'store'])->name('bills.store');
    Route::get('/bills/{bill}', [BillController::class, 'show'])->name('bills.show');
    Route::get('/bills/{bill}/receipt', [ReceiptController::class, 'show'])->name('bills.receipt');
    Route::post('/bills/{bill}/receipt', [ReceiptController::class, 'store'])->name('bills.receipt.store');
    Route::post('/bills/{bill}/receipt/parse', [ReceiptController::class, 'parse'])->name('receipts.parse');
    Route::get('/bills/{bill}/receipt/image', [ReceiptController::class, 'image'])->name('receipts.image');
    Route::get('/bills/{bill}/splits', [SplitController::class, 'show'])->name('bills.splits');
    Route::post('/bills/{bill}/splits', [SplitController::class, 'store'])->name('bills.splits.store');
    Route::post('/bills/{bill}/participants/{participant}/manual-payment', [ManualPaymentController::class, 'store'])->name('bills.manual-payment.store');
    Route::get('/bills/{bill}/payment-logs', [PaymentLogController::class, 'index'])->name('bills.payment-logs');
});

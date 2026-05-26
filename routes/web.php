<?php

use App\Http\Controllers\BillController;
use App\Http\Controllers\DemoController;
use App\Http\Controllers\FiuuPaymentController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\ManualPaymentController;
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

// Organizer routes (require authentication)
Route::middleware(['auth:organizer'])->group(function () {
    // Bill CRUD
    Route::get('/bills/create', [BillController::class, 'create'])->name('bills.create');
    Route::post('/bills', [BillController::class, 'store'])->name('bills.store');
    Route::get('/bills/{bill}', [BillController::class, 'show'])->name('bills.show');
    Route::post('/bills/{bill}/publish', [BillController::class, 'publish'])->name('bills.publish');
    Route::get('/bills/{bill}/summary', [BillController::class, 'summary'])->name('bills.summary');

    // Receipt management
    Route::get('/bills/{bill}/receipt', [ReceiptController::class, 'show'])->name('bills.receipt.show');
    Route::post('/bills/{bill}/receipt/upload', [ReceiptController::class, 'upload'])->name('bills.receipt.upload');
    Route::post('/bills/{bill}/receipt/parse', [ReceiptController::class, 'parse'])->name('bills.receipt.parse');
    Route::post('/bills/{bill}/receipt/items', [ReceiptController::class, 'saveItems'])->name('bills.receipt.items');

    // Split management
    Route::get('/bills/{bill}/splits', [SplitController::class, 'edit'])->name('bills.splits.edit');
    Route::post('/bills/{bill}/splits', [SplitController::class, 'update'])->name('bills.splits.update');

    // Manual payment confirmation
    Route::post('/manual-payment/confirm/{bill}/{participant}', [ManualPaymentController::class, 'store'])->name('manual-payment.confirm');

    // Payment logs
    Route::get('/payment-logs/{bill}', [PaymentLogController::class, 'index'])->name('payment-logs.index');
});
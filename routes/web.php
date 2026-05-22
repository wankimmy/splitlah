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

Route::get('/', LandingController::class)->name('home');
Route::get('/demo', [DemoController::class, 'show'])->name('demo');

Route::get('/bills/create', [BillController::class, 'create'])->name('bills.create');
Route::post('/bills', [BillController::class, 'store'])->name('bills.store');
Route::get('/bills/{bill}', [BillController::class, 'show'])->name('bills.show');
Route::post('/bills/{bill}/publish', [BillController::class, 'publish'])->name('bills.publish');
Route::get('/bills/{bill}/summary', [BillController::class, 'summary'])->name('bills.summary');

Route::get('/bills/{bill}/receipt', [ReceiptController::class, 'show'])->name('bills.receipt.show');
Route::post('/bills/{bill}/receipt/upload', [ReceiptController::class, 'upload'])->name('bills.receipt.upload');
Route::post('/bills/{bill}/receipt/parse', [ReceiptController::class, 'parse'])->name('bills.receipt.parse');
Route::post('/bills/{bill}/receipt/items', [ReceiptController::class, 'saveItems'])->name('bills.receipt.items');

Route::get('/bills/{bill}/split', [SplitController::class, 'edit'])->name('bills.split.edit');
Route::post('/bills/{bill}/split', [SplitController::class, 'update'])->name('bills.split.update');

Route::get('/bills/{bill}/payments', [PaymentLogController::class, 'index'])->name('bills.payments.index');

Route::get('/pay/{token}', [ParticipantPaymentController::class, 'show'])->name('participants.pay');

Route::post('/participants/{participant:token}/manual-paid', [ManualPaymentController::class, 'store'])->name('participants.manual-paid');

Route::post('/payments/fiuu/create/{participant:token}', [FiuuPaymentController::class, 'create'])->name('payments.fiuu.create');
Route::match(['GET', 'POST'], '/payments/fiuu/return', [FiuuPaymentController::class, 'return'])->name('payments.fiuu.return');
Route::post('/payments/fiuu/notify', [FiuuPaymentController::class, 'notify'])->name('payments.fiuu.notify');
Route::match(['GET', 'POST'], '/payments/fiuu/cancel', [FiuuPaymentController::class, 'cancel'])->name('payments.fiuu.cancel');

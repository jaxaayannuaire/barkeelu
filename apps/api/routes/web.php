<?php

use App\Http\Controllers\Web\CampaignShowController;
use App\Http\Controllers\Web\DonationFlowController;
use App\Http\Controllers\Web\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class);
Route::get('/collectes/{slug}', CampaignShowController::class)->name('campaigns.show');
Route::get('/collectes/{slug}/don', [DonationFlowController::class, 'amount'])->name('donations.amount');
Route::post('/collectes/{slug}/don', [DonationFlowController::class, 'storeAmount'])->middleware('throttle:auth-token');
Route::get('/collectes/{slug}/don/coordonnees/{checkout}', [DonationFlowController::class, 'details'])->name('donations.details');
Route::post('/collectes/{slug}/don/coordonnees/{checkout}', [DonationFlowController::class, 'storeDetails'])->middleware('throttle:auth-token');
Route::get('/collectes/{slug}/don/paiement/{checkout}', [DonationFlowController::class, 'checkout'])->name('donations.checkout');
Route::post('/collectes/{slug}/don/paiement/{checkout}/confirmer', [DonationFlowController::class, 'confirm'])->name('donations.confirm')->middleware('throttle:auth-token');
Route::post('/collectes/{slug}/don/paiement/{checkout}/payer', [DonationFlowController::class, 'payCheckout'])->name('donations.pay')->middleware('throttle:auth-token');
Route::get('/collectes/{slug}/don/paiement/{checkout}/attente', [DonationFlowController::class, 'waitingCheckout'])->name('donations.waiting.checkout');
Route::get('/collectes/{slug}/don/paiement/{checkout}/statut', [DonationFlowController::class, 'statusCheckout'])->name('donations.status.checkout');
Route::post('/collectes/{slug}/don/paiement/{checkout}/reessayer', [DonationFlowController::class, 'retryCheckout'])->name('donations.retry.checkout')->middleware('throttle:auth-token');
Route::post('/collectes/{slug}/don/paiement/{checkout}/verifier', [DonationFlowController::class, 'resolveCheckoutUnknown'])->name('donations.verify.checkout')->middleware('throttle:auth-token');
Route::get('/collectes/{slug}/don/paiement/{checkout}/merci', [DonationFlowController::class, 'thanksCheckout'])->name('donations.thanks.checkout');
Route::get('/collectes/{slug}/don/paiement/{checkout}/echec', [DonationFlowController::class, 'failedCheckout'])->name('donations.failed.checkout');
Route::post('/collectes/{slug}/don/paiement', [DonationFlowController::class, 'pay'])->middleware('throttle:auth-token');
Route::get('/dons/{donation}/paiement/{payment}/attente', [DonationFlowController::class, 'waiting'])->name('donations.waiting');
Route::get('/dons/{donation}/paiement/{payment}/statut', [DonationFlowController::class, 'status'])->name('donations.status');
Route::get('/dons/{donation}/merci', [DonationFlowController::class, 'thanks'])->name('donations.thanks');
Route::get('/dons/{donation}/paiement/{payment}/echec', [DonationFlowController::class, 'failed'])->name('donations.failed');
Route::post('/dons/{donation}/paiement/{payment}/reessayer', [DonationFlowController::class, 'retry'])->name('donations.retry')->middleware('throttle:auth-token');

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
Route::post('/collectes/{slug}/don/paiement', [DonationFlowController::class, 'pay'])->middleware('throttle:auth-token');
Route::get('/dons/{donation}/paiement/{payment}/attente', [DonationFlowController::class, 'waiting'])->name('donations.waiting');
Route::get('/dons/{donation}/paiement/{payment}/statut', [DonationFlowController::class, 'status'])->name('donations.status');
Route::get('/dons/{donation}/merci', [DonationFlowController::class, 'thanks'])->name('donations.thanks');
Route::get('/dons/{donation}/paiement/{payment}/echec', [DonationFlowController::class, 'failed'])->name('donations.failed');
Route::post('/dons/{donation}/paiement/{payment}/reessayer', [DonationFlowController::class, 'retry'])->name('donations.retry')->middleware('throttle:auth-token');

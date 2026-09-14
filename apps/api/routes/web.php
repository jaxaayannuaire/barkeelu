<?php

use App\Http\Controllers\Web\CampaignShowController;
use App\Http\Controllers\Web\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class);
Route::get('/collectes/{slug}', CampaignShowController::class)->name('campaigns.show');

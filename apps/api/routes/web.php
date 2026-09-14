<?php

use App\Support\HomeDemoData;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('pages.home', ['campaigns' => HomeDemoData::campaigns()]);
});

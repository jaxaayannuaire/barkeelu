<?php

use App\Models\Campaign;
use App\Services\Checkout\CheckoutSessionService;
use Illuminate\Contracts\Console\Kernel;

putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';

require dirname(__DIR__, 3).'/vendor/autoload.php';

$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$campaign = Campaign::query()->findOrFail((int) $argv[1]);
$session = $app->make(CheckoutSessionService::class)->create($campaign, null, [
    'currency' => 'XOF',
    'nominal_amount' => 1000,
    'idempotency_key' => 'checkout-concurrent',
]);

echo $session->id;

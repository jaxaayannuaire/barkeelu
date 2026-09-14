<?php

use App\Models\Payment;
use App\Services\Payments\PaymentService;
use Illuminate\Contracts\Console\Kernel;

putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';

require dirname(__DIR__, 3).'/vendor/autoload.php';

$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$payment = Payment::query()->findOrFail((int) $argv[1]);
$event = json_decode(base64_decode($argv[2], true), true, 512, JSON_THROW_ON_ERROR);
$result = $app->make(PaymentService::class)->applyProviderState($payment, $event);

echo $result->id;

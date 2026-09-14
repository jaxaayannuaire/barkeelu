<?php

use App\Models\Payment;
use App\Models\User;
use App\Services\Refunds\RefundService;
use Illuminate\Contracts\Console\Kernel;

putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';
require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
try {
    $r = $app->make(RefundService::class)->request(Payment::findOrFail((int) $argv[1]), User::findOrFail((int) $argv[2]), ['amount' => 7000, 'currency' => 'XOF', 'idempotency_key' => $argv[3]]);
    echo 'OK:'.$r->id;
} catch (Throwable $e) {
    fwrite(STDERR, get_class($e).':'.$e->getMessage());
    exit(10);
}

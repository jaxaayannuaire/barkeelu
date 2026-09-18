<?php

use App\Models\CheckoutSession;
use App\Services\Checkout\CheckoutConfirmationService;
use Illuminate\Contracts\Console\Kernel;

putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';

require dirname(__DIR__, 3).'/vendor/autoload.php';

$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$session = CheckoutSession::query()->findOrFail((int) $argv[1]);
try {
    $confirmed = $app->make(CheckoutConfirmationService::class)->confirm($session, [
        'idempotency_key' => $argv[2] ?? 'confirm-concurrent',
    ]);

    echo $confirmed->donation_id;
    exit(0);
} catch (DomainException $exception) {
    fwrite(STDERR, $exception->getMessage());
    exit(42);
}

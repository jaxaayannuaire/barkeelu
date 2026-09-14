<?php

use App\Models\Campaign;
use App\Models\ProviderAccount;
use App\Models\User;
use App\Services\Payouts\PayoutService;
use Illuminate\Contracts\Console\Kernel;

putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';

require dirname(__DIR__, 3).'/vendor/autoload.php';

$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    $service = $app->make(PayoutService::class);
    $payout = $service->request(
        Campaign::findOrFail((int) $argv[1]),
        ProviderAccount::findOrFail((int) $argv[2]),
        User::findOrFail((int) $argv[3]),
        [
            'amount' => (int) $argv[5],
            'currency' => 'XOF',
            'idempotency_key' => $argv[4],
            'destination_snapshot' => ['account' => 'masked-concurrent'],
        ],
    );
    $service->approve($payout, User::findOrFail((int) $argv[6]));
    echo 'OK:'.$payout->id;
} catch (Throwable $exception) {
    fwrite(STDERR, get_class($exception).':'.$exception->getMessage());
    exit(10);
}

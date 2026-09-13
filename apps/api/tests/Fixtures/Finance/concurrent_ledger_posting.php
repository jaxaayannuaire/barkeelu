<?php

use App\Services\Finance\LedgerPostingService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';

require dirname(__DIR__, 3).'/vendor/autoload.php';

$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$transaction = $app->make(LedgerPostingService::class)->post(
    $argv[1],
    'XOF',
    json_decode(base64_decode($argv[2], true), true, 512, JSON_THROW_ON_ERROR),
    'Concurrent posting',
);

echo json_encode([
    'id' => $transaction->id,
    'database' => DB::scalar('select current_database()'),
], JSON_THROW_ON_ERROR);

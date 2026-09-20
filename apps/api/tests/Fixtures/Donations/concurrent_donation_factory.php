<?php

use App\Data\Donations\ConfirmedDonationData;
use App\Services\Donations\DonationFactory;
use Illuminate\Contracts\Console\Kernel;

putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';

require dirname(__DIR__, 3).'/vendor/autoload.php';

$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    $app->make(DonationFactory::class)->create(new ConfirmedDonationData(
        campaignId: (int) $argv[1],
        donorUserId: null,
        donorSnapshot: ['name' => 'Awa', 'email' => 'awa@example.test', 'is_anonymous' => false],
        nominalAmount: 10_000,
        platformFeeAmount: 400,
        payoutProvisionAmount: 100,
        totalPayableAmount: 10_500,
        currency: 'XOF',
        idempotencyKey: $argv[2],
        contentHash: $argv[3],
        createdByUserId: null,
        sourceContext: 'checkout_confirmation',
    ));
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage());
    exit(42);
}

<?php

use App\Models\CheckoutSession;
use App\Models\ProviderAccount;
use App\Services\Checkout\CheckoutPaymentService;
use App\Services\Payments\ProviderGatewayResolver;
use Illuminate\Contracts\Console\Kernel;
use Mockery;
use Tests\Fixtures\Checkout\CountingProviderGateway;

putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';

require dirname(__DIR__, 3).'/vendor/autoload.php';

$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$gateway = new CountingProviderGateway;
$resolver = Mockery::mock(ProviderGatewayResolver::class);
$resolver->shouldReceive('for')->andReturn($gateway);
$app->instance(ProviderGatewayResolver::class, $resolver);

$session = CheckoutSession::query()->findOrFail((int) $argv[1]);
$account = ProviderAccount::query()->findOrFail((int) $argv[2]);
$result = $app->make(CheckoutPaymentService::class)->initiate($session, $account, [
    'idempotency_key' => 'payment-concurrent',
    'payer_mobile' => '+221770000000',
    'success_url' => 'https://barkeelu.test/success',
    'error_url' => 'https://barkeelu.test/error',
]);

echo $result->payment->id;

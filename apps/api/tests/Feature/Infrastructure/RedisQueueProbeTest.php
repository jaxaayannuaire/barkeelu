<?php

namespace Tests\Feature\Infrastructure;

use App\Jobs\Infrastructure\RedisQueueProbe;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class RedisQueueProbeTest extends TestCase
{
    public function test_a_diagnostic_job_is_processed_by_a_redis_one_shot_worker(): void
    {
        $this->assertTrue(
            extension_loaded('redis'),
            'L’extension PhpRedis est requise pour ce test d’intégration.',
        );

        config([
            'database.redis.client' => 'phpredis',
            'database.redis.default.host' => '127.0.0.1',
            'database.redis.default.port' => 6379,
            'database.redis.default.password' => null,
            'queue.default' => 'redis',
            'queue.connections.redis.connection' => 'default',
            'queue.connections.redis.queue' => 'diagnostic',
        ]);

        $resultKey = 'barkeelu:diagnostic:queue-probe:'.bin2hex(random_bytes(16));
        $connection = Redis::connection('default');

        try {
            RedisQueueProbe::dispatch($resultKey)
                ->onConnection('redis')
                ->onQueue('diagnostic');

            $exitCode = Artisan::call('queue:work', [
                'connection' => 'redis',
                '--queue' => 'diagnostic',
                '--once' => true,
                '--no-interaction' => true,
            ]);

            $this->assertSame(0, $exitCode, Artisan::output());
            $this->assertSame('processed', $connection->get($resultKey));
            $this->assertSame(0, $connection->llen('queues:diagnostic'));
        } finally {
            $connection->del($resultKey);
        }
    }
}

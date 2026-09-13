<?php

namespace Tests\Feature\Infrastructure;

use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class RedisConnectionTest extends TestCase
{
    public function test_real_redis_connection_can_set_get_and_delete_a_dedicated_key(): void
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
        ]);

        $key = 'barkeelu:diagnostic:redis-connection:'.bin2hex(random_bytes(16));
        $connection = Redis::connection('default');

        try {
            $this->assertTrue($connection->ping());
            $this->assertTrue($connection->set($key, 'redis-real-pass'));
            $this->assertSame('redis-real-pass', $connection->get($key));
        } finally {
            $connection->del($key);
        }

        $this->assertSame(0, $connection->exists($key));
    }
}

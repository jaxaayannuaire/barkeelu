<?php

namespace Tests\Feature\Infrastructure;

use App\Events\Infrastructure\InfrastructureBroadcastProbe;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Support\Facades\Queue;
use Laravel\Reverb\ReverbServiceProvider;
use Tests\TestCase;

class ReverbBroadcastingTest extends TestCase
{
    public function test_reverb_configuration_and_diagnostic_event_are_coherent(): void
    {
        $this->assertTrue(class_exists(ReverbServiceProvider::class));
        $this->assertArrayHasKey('reverb', config('broadcasting.connections'));
        $this->assertSame('reverb', config('broadcasting.connections.reverb.driver'));

        config(['broadcasting.default' => 'reverb']);
        Queue::fake();

        $probe = new InfrastructureBroadcastProbe;

        $this->assertInstanceOf(ShouldBroadcast::class, $probe);
        $this->assertSame('infrastructure.diagnostic', $probe->broadcastOn()[0]->name);
        $this->assertSame('diagnostic', $probe->broadcastQueue());

        event($probe);

        Queue::assertPushed(BroadcastEvent::class);
    }
}

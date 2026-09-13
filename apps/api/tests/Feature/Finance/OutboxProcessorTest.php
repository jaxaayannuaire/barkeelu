<?php

namespace Tests\Feature\Finance;

use App\Models\OutboxEvent;
use App\Services\Finance\OutboxProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OutboxProcessorTest extends TestCase
{
    use RefreshDatabase;

    public function test_processor_marks_event_published_after_success(): void
    {
        $event = $this->event();
        $this->assertTrue(app(OutboxProcessor::class)->processOne(static fn (): null => null));
        $event->refresh();
        $this->assertNotNull($event->published_at);
        $this->assertSame(1, $event->attempts);
    }

    public function test_processor_keeps_failed_event_retryable_and_sanitizes_error(): void
    {
        $event = $this->event();
        app(OutboxProcessor::class)->processOne(static function (): void {
            throw new \RuntimeException('secret');
        });
        $event->refresh();
        $this->assertNull($event->published_at);
        $this->assertSame(1, $event->attempts);
        $this->assertSame('Publication outbox échouée.', $event->last_error);
    }

    public function test_processor_ignores_already_published_event(): void
    {
        $event = $this->event();
        $event->update(['published_at' => now()]);
        $this->assertFalse(app(OutboxProcessor::class)->processOne(static fn (): null => null));
        $this->assertSame(0, $event->refresh()->attempts);
    }

    private function event(): OutboxEvent
    {
        return OutboxEvent::query()->create(['public_id' => (string) Str::uuid(), 'event_type' => 'ledger.transaction.posted', 'aggregate_type' => 'ledger_transaction', 'aggregate_reference' => 'test', 'dedupe_key' => Str::uuid(), 'payload' => [], 'occurred_at' => now(), 'available_at' => now()]);
    }
}

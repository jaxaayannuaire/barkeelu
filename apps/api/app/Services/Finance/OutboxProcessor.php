<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;

class OutboxProcessor
{
    public function processOne(callable $dispatch): bool
    {
        return DB::transaction(function () use ($dispatch): bool {
            $event = DB::table('outbox_events')
                ->whereNull('published_at')
                ->where('available_at', '<=', now())
                ->orderBy('id')
                ->lock('FOR UPDATE SKIP LOCKED')
                ->first();

            if ($event === null) {
                return false;
            }

            try {
                $dispatch(json_decode($event->payload, true, 512, JSON_THROW_ON_ERROR));

                DB::table('outbox_events')->where('id', $event->id)->update([
                    'published_at' => now(),
                    'attempts' => $event->attempts + 1,
                    'updated_at' => now(),
                ]);
            } catch (\Throwable) {
                DB::table('outbox_events')->where('id', $event->id)->update([
                    'attempts' => $event->attempts + 1,
                    'last_error' => 'Publication outbox échouée.',
                    'updated_at' => now(),
                ]);
            }

            return true;
        });
    }
}

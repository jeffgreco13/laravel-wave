<?php

namespace Jeffgreco13\Wave\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Jeffgreco13\Wave\WaveService;

class SyncBusinessesFromWave implements ShouldQueue
{
    use Queueable;

    public function handle(WaveService $wave): void
    {
        $model = config('wave.models.business');

        $seenWaveIds = [];

        $businesses = $wave->getAllBusinesses();
        foreach ($businesses as $business) {
            $model::syncFromWave($business->toArray());
            $seenWaveIds[] = $business['id'];
        }

        if (! empty($seenWaveIds)) {
            $model::whereNotIn('wave_id', $seenWaveIds)->delete();
        }
    }
}

<?php

namespace Jeffgreco13\Wave\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Jeffgreco13\Wave\WaveService;

class SyncVendorsFromWave implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected ?string $businessId = null,
    ) {}

    public function handle(WaveService $wave): void
    {
        $model = config('wave.models.vendor');

        if ($this->businessId) {
            $wave->setBusinessId($this->businessId);
        }

        $seenWaveIds = [];

        $vendors = $wave->getAllVendors();
        foreach ($vendors as $vendor) {
            $model::syncFromWave($vendor->toArray());
            $seenWaveIds[] = $vendor['id'];
        }

        if (! empty($seenWaveIds)) {
            $model::whereNotIn('wave_id', $seenWaveIds)->delete();
        }
    }
}

<?php

namespace Jeffgreco13\Wave\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Jeffgreco13\Wave\WaveService;

class SyncSalesTaxesFromWave implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected ?string $businessId = null,
    ) {}

    public function handle(WaveService $wave): void
    {
        $model = config('wave.models.sales_tax');

        if ($this->businessId) {
            $wave->setBusinessId($this->businessId);
        }

        $seenWaveIds = [];

        $taxes = $wave->getAllTaxes();
        foreach ($taxes as $tax) {
            $model::syncFromWave($tax->toArray());
            $seenWaveIds[] = $tax['id'];
        }

        if (! empty($seenWaveIds)) {
            $model::whereNotIn('wave_id', $seenWaveIds)->delete();
        }
    }
}

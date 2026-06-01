<?php

namespace Jeffgreco13\Wave\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Jeffgreco13\Wave\WaveService;

class SyncProductsFromWave implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected ?string $businessId = null,
    ) {}

    public function handle(WaveService $wave): void
    {
        $model = config('wave.models.product');

        if ($this->businessId) {
            $wave->setBusinessId($this->businessId);
        }

        $seenWaveIds = [];

        $products = $wave->getAllProducts();
        foreach ($products as $product) {
            $model::syncFromWave($product->toArray());
            $seenWaveIds[] = $product['id'];
        }

        if (! empty($seenWaveIds)) {
            $model::whereNotIn('wave_id', $seenWaveIds)->delete();
        }
    }
}

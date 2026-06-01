<?php

namespace Jeffgreco13\Wave\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Jeffgreco13\Wave\WaveService;

class CreateWaveCustomer implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected array $input,
        protected ?string $businessId = null,
    ) {}

    public function handle(WaveService $wave): void
    {
        if ($this->businessId) {
            $wave->setBusinessId($this->businessId);
        }

        $node = $wave->createCustomer($this->input);

        if ($node['id'] ?? null) {
            $model = config('wave.models.customer');
            $model::syncFromWave($node->toArray());
        }
    }
}

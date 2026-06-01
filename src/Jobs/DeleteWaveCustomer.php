<?php

namespace Jeffgreco13\Wave\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Jeffgreco13\Wave\WaveService;

class DeleteWaveCustomer implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $waveCustomerId,
        protected ?string $businessId = null,
    ) {}

    public function handle(WaveService $wave): void
    {
        if ($this->businessId) {
            $wave->setBusinessId($this->businessId);
        }

        $deleted = $wave->deleteCustomer($this->waveCustomerId);

        if ($deleted) {
            $model = config('wave.models.customer');
            $model::where('wave_id', $this->waveCustomerId)->delete();
        }
    }
}

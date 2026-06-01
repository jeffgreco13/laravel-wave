<?php

namespace Jeffgreco13\Wave\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Jeffgreco13\Wave\WaveService;

class SyncAccountsFromWave implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected ?string $businessId = null,
    ) {}

    public function handle(WaveService $wave): void
    {
        $model = config('wave.models.account');

        if ($this->businessId) {
            $wave->setBusinessId($this->businessId);
        }

        $seenWaveIds = [];

        $accounts = $wave->getAllAccounts();
        foreach ($accounts as $account) {
            $model::syncFromWave($account->toArray());
            $seenWaveIds[] = $account['id'];
        }

        if (! empty($seenWaveIds)) {
            $model::whereNotIn('wave_id', $seenWaveIds)->delete();
        }
    }
}

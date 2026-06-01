<?php

namespace Jeffgreco13\Wave\Jobs;

use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Jeffgreco13\Wave\WaveService;

class SyncCustomersFromWave implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected ?Carbon $since = null,
        protected ?string $businessId = null,
    ) {}

    public function handle(WaveService $wave): void
    {
        $isFullSync = is_null($this->since);
        $model = config('wave.models.customer');

        if ($this->businessId) {
            $wave->setBusinessId($this->businessId);
        }

        $variables = [
            'page'     => 1,
            'pageSize' => config('wave.sync.chunk_size', 150),
        ];

        if ($this->since) {
            $variables['modifiedAtAfter'] = $this->since->toIso8601String();
        }

        $seenWaveIds = $isFullSync ? [] : null;

        do {
            $customers = $wave->getCustomers($variables);
            foreach ($customers as $customer) {
                $model::syncFromWave($customer->toArray());
                if ($isFullSync) {
                    $seenWaveIds[] = $customer['id'];
                }
            }
            $variables['page']++;
        } while ($wave->hasNextPage());

        // Delete orphaned local records only on full sync
        if ($isFullSync && ! empty($seenWaveIds)) {
            $model::whereNotIn('wave_id', $seenWaveIds)->delete();
        }
    }
}

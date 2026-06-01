<?php

namespace Jeffgreco13\Wave\Jobs;

use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Jeffgreco13\Wave\WaveService;

class SyncInvoicesFromWave implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected ?Carbon $since = null,
        protected ?string $businessId = null,
    ) {}

    public function handle(WaveService $wave): void
    {
        $isFullSync = is_null($this->since);
        $model = config('wave.models.invoice');

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
            $invoices = $wave->getInvoices($variables);
            foreach ($invoices as $invoice) {
                $model::syncFromWave($invoice->toArray());
                if ($isFullSync) {
                    $seenWaveIds[] = $invoice['id'];
                }
            }
            $variables['page']++;
        } while ($wave->hasNextPage());

        if ($isFullSync && ! empty($seenWaveIds)) {
            $model::whereNotIn('wave_id', $seenWaveIds)->delete();
        }
    }
}

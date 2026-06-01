<?php

namespace Jeffgreco13\Wave\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Jeffgreco13\Wave\Jobs\SyncCustomersFromWave;

class SyncWaveCustomers extends Command
{
    protected $signature = 'wave:sync-customers
                            {--since= : Only sync records modified after this date (e.g. "2024-01-01"). Use "last" to auto-detect from local records.}
                            {--business-id= : Wave business ID (overrides config).}
                            {--sync : Run synchronously instead of dispatching to the queue.}
                            {--fresh : Truncate the local wave_customers table before syncing (full sync only).}';

    protected $description = 'Sync customers from Wave to the local database.';

    public function handle(): int
    {
        $since = $this->parseSince();
        $businessId = $this->option('business-id');

        if ($this->option('fresh') && is_null($since)) {
            $model = config('wave.models.customer');
            $model::truncate();
            $this->info('wave_customers table truncated.');
        }

        $job = new SyncCustomersFromWave($since, $businessId ?: null);

        if ($this->option('sync')) {
            $this->info('Syncing customers from Wave...');
            app()->call([$job, 'handle']);
            $this->info('Done.');
        } else {
            dispatch($job);
            $this->info('SyncCustomersFromWave job dispatched to the queue.');
        }

        return self::SUCCESS;
    }

    private function parseSince(): ?Carbon
    {
        $value = $this->option('since');

        if (is_null($value)) {
            return null;
        }

        if ($value === 'last') {
            $model = config('wave.models.customer');
            $latest = $model::whereNotNull('wave_modified_at')->max('wave_modified_at');

            return $latest ? Carbon::parse($latest) : null;
        }

        return Carbon::parse($value);
    }
}

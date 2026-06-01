<?php

namespace Jeffgreco13\Wave\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Jeffgreco13\Wave\Jobs\SyncAccountsFromWave;
use Jeffgreco13\Wave\Jobs\SyncBusinessesFromWave;
use Jeffgreco13\Wave\Jobs\SyncCustomersFromWave;
use Jeffgreco13\Wave\Jobs\SyncInvoicesFromWave;
use Jeffgreco13\Wave\Jobs\SyncProductsFromWave;
use Jeffgreco13\Wave\Jobs\SyncSalesTaxesFromWave;
use Jeffgreco13\Wave\Jobs\SyncVendorsFromWave;

class SyncWaveAll extends Command
{
    protected $signature = 'wave:sync-all
                            {--since= : Only sync records modified after this date (where supported). Use "last" per entity.}
                            {--business-id= : Wave business ID (overrides config).}
                            {--sync : Run synchronously instead of dispatching to the queue.}';

    protected $description = 'Sync all Wave entities (customers, invoices, products, taxes, vendors, businesses, accounts) to the local database.';

    public function handle(): int
    {
        $businessId = $this->option('business-id') ?: null;
        $sinceRaw = $this->option('since');
        $since = $sinceRaw && $sinceRaw !== 'last' ? Carbon::parse($sinceRaw) : null;
        $sync = $this->option('sync');

        $jobs = [
            new SyncBusinessesFromWave,
            new SyncCustomersFromWave($since, $businessId),
            new SyncInvoicesFromWave($since, $businessId),
            new SyncProductsFromWave($businessId),
            new SyncSalesTaxesFromWave($businessId),
            new SyncVendorsFromWave($businessId),
            new SyncAccountsFromWave($businessId),
        ];

        foreach ($jobs as $job) {
            if ($sync) {
                $this->info('Running '.class_basename($job).'...');
                app()->call([$job, 'handle']);
            } else {
                dispatch($job);
            }
        }

        $this->info($sync ? 'All Wave entities synced.' : 'All sync jobs dispatched to the queue.');

        return self::SUCCESS;
    }
}

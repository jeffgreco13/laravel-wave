<?php

namespace Jeffgreco13\Wave\Commands;

use Illuminate\Console\Command;
use Jeffgreco13\Wave\WaveService;

class PullWaveCurrencies extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wave:pull-currencies';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command will fetch the available currencies from Wave and save them in a json file in this storage path.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $wave = new WaveService();

        if ($currencies = $wave->getAllCurrencies()){
            file_put_contents(storage_path('wave_currencies.json'),json_encode($currencies));
            $this->info('Currencies saved in storage path!');
        } else {
            $this->error('No currencies or empty array returned. Query failed.');
        }

    }
}

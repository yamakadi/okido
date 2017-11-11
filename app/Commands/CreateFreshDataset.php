<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class CreateFreshDataset extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dataset:new';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates the dataset folder from scratch and follows all the necessary steps to create a complete dataset.';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(): void
    {
        $this->warn('Creating fresh datasets. This might delete or overwrite existing datasets.');

        $continue = $this->confirm('Would you like yo continue?');

        if($continue) {
            $this->call('images:metadata');
            $this->call('images:download');
            $this->call('images:normalize');
            $this->call('dataset:prepare');
        } else {
            $this->info('Cancelled.');
        }
    }
}

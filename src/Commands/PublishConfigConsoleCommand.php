<?php

namespace Kolirt\MasterModel\Commands;

use Illuminate\Console\Command;

class PublishConfigConsoleCommand extends Command
{

    protected $signature = 'master-model:publish-config';

    protected $description = 'Publish the config file';

    public function handle(): void
    {
        $this->call('vendor:publish', [
            '--provider' => 'Kolirt\\MasterModel\\ServiceProvider',
            '--tag' => 'config'
        ]);
    }

}

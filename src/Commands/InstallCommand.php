<?php

namespace Kolirt\MasterModel\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{

    protected $signature = 'master-model:install';

    protected $description = 'Install master model package';

    public function handle(): void
    {
        $this->call(PublishConfigConsoleCommand::class);
    }

}

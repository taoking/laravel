<?php

namespace App\Console\Commands;

use App\Services\EsService;
use Illuminate\Console\Command;

class EsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'es:init_org';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(EsService $service)
    {
        echo '11331';
        $service->initOrg();
    }
}

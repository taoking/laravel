<?php

namespace App\Console\Commands;

use App\Models\MacroData;
use App\Models\MacroDataNew;
use Illuminate\Console\Command;

class DorisCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'doris:init';

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
    public function handle()
    {

        $id = 188886;
        while (true){
            $data =  MacroData::where('rid', '>', $id)->orderBy('rid')->limit(2000)->get();
            if (empty($data)){
                echo '执行完成';
                break;
            }
            $data = $data->toArray();
            MacroDataNew::insert($data);

            $id  = end($data)['rid'];
            echo '--'.$id.'-\n-';
        }
        return 0;
    }
}

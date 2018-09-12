<?php

namespace App\Console\Commands;

use App\Models\Log\AdminLogs;
use App\Models\Log\CurlLogs;
use App\Models\Log\ErrorLogs;
use App\Models\Log\H5Logs;
use App\Models\Log\Logs;
use Illuminate\Console\Command;
use Illuminate\Foundation\Bus\DispatchesJobs;

class ClearLogTemp extends Command
{
    use DispatchesJobs;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clear:temp:log';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'clear temp log 7 days ago';

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
     * @return mixed
     */
    public function handle()
    {
        AdminLogs::where('operate_time','<',strtotime('-3 days'))->delete();
        CurlLogs::where('time','<',strtotime('-3 days'))->delete();
        ErrorLogs::where('time','<',strtotime('-3 days'))->delete();
        H5Logs::where('time','<',strtotime('-3 days'))->delete();
        Logs::where('time','<',strtotime('-3 days'))->delete();
    }
}

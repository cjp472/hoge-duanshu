<?php

namespace App\Console\Commands;

use App\Jobs\CronData;
use Illuminate\Console\Command;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Support\Facades\Cache;

class CronStatistics extends Command
{
    use DispatchesJobs;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cron:statistics';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'change shop version when expired';

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
        //检测今日是否跑过脚本(注意:设置的是跑脚本的日期,不是前一天日期)
        $flag = date('Ymd');
        if(Cache::has('cron'.$flag)){
            return;
        }
        //设置脚本运行时间缓存标识
        Cache::forever('cron'.$flag,true);

        $beginYesterday=mktime(0,0,0,date('m'),date('d')-1,date('Y'));
        $endYesterday=mktime(0,0,0,date('m'),date('d'),date('Y'))-1;
        $this->dispatch(new CronData([
            'beginYesterday' => $beginYesterday,
            'endYesterday' => $endYesterday,
        ]));
    }
}

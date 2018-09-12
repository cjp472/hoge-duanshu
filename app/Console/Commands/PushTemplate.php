<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ShopContentRemind;
use App\Events\PushTemplateEvent;
use Illuminate\Foundation\Bus\DispatchesJobs;

class PushTemplate extends Command
{
    use DispatchesJobs;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'push:template';

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
//        $obj = ShopContentRemind::select('shop_content_remind_record.shop_id','live.start_time','shop_content_remind_record.id','shop_content_remind_record.content_id','shop_content_remind_record.openid','shop_content_remind_record.scene','shop_content_remind_record.content_type','shop_content_remind_record.source')
//            ->leftJoin('live','shop_content_remind_record.content_id','=','live.content_id')
//            ->where(['push_status'=>1,'content_type'=>'live','source'=>'wechat'])
//            ->get();
//        $time = time();
        $obj = ShopContentRemind::where(['push_status'=>1,'content_type'=>'live','source'=>'wechat'])->get();
        if(!$obj->isEmpty()){
            foreach($obj as $value){
                $live_start = $value->live?$value->live->start_time:0;
                if($live_start && (strtotime('+5 minute',strtotime(date('Y-m-d H:i',time()))) == strtotime(date('Y-m-d H:i',$live_start)))){
                    $value->start_time = $live_start;
                    event(new PushTemplateEvent($value));
                }
            }
        }
    }
}

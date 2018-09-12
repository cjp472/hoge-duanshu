<?php

namespace App\Console\Commands;

use App\Events\ClearMaterial;
use App\Events\NoticeEvent;
use App\Events\PintuanRefundsEvent;
use App\Events\SystemEvent;
use App\Models\FightGroup;
use App\Models\FightGroupMember;
use App\Models\ShopClose;
use App\Models\ShopDisable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class PinTuanRefund extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pintuan:refund {--fight_group_ids=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '拼团手动处理退款';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $fight_group_ids_params = $this->option('fight_group_ids');
        if ($fight_group_ids_params) {
            $fight_group_ids = explode(',', $fight_group_ids_params);
            foreach ($fight_group_ids as $fight_group_id){
                $fight_group = FightGroup::where(['id'=>$fight_group_id, 'status'=>'failed'])->first();
                $where = ['fight_group_id'=>$fight_group_id];
                if(!$fight_group){
                    $where['join_success'] = 0;
                }
                $fight_group_member = FightGroupMember::where($where)->get(['order_no','fight_group_id','redundancy_member']);
                if($fight_group_member->isNotEmpty()){
                    foreach ($fight_group_member as $item) {

                        $member = $item->redundancy_member ? json_decode($item->redundancy_member,1): [];
                        $item->member_uid = $member['uid'];
                        //订单存在且订单是未确认时才进行退款操作
                        if($item->order && $item->order->pay_status == -6) {
                            event(new NoticeEvent(
                                0,
                                '您参与的拼团超过有效期拼团失败，系统会自动将所支付的款项退回，具体到账时间以各银行为准。',
                                $item->order->shop_id,
                                $member['uid'],
                                $member['nick_name'],
                                ['title' => '点击查看', 'content_id' => $item->order->content_id, 'type' => $item->order->content_type, 'fight_group_id' => $fight_group_id, 'fight_group_activity_id' => FightGroup::where(['id' => $fight_group_id])->value('fight_group_activity_id') ?: '', 'out_link' => ''],
                                '拼团失败'));

                            $order_source = $item->order ? $item->order->source : 'h5';
                            if ($order_source == 'applet') {
                                $this->appletRefunds($item->order);
                            } else {
                                $param = [
                                    'buyer_id' => $item->member_uid,
                                    'order_no' => $item->order_no,  //字段待确认
                                    'order_item' => '',
                                    'quantity' => 1,
                                    'refund_type' => 'money',
                                    'refund_reason' => '短书拼团失败退款',
                                    'auto_refund' => true
                                ];
                                event(new PintuanRefundsEvent($param));
                            }
                        }
                    }
                }
            }
        }
    }

}

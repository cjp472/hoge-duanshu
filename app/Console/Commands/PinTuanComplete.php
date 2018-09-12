<?php

namespace App\Console\Commands;

use App\Events\ClearMaterial;
use App\Events\CurlLogsEvent;
use App\Events\ErrorHandle;
use App\Events\NoticeEvent;
use App\Events\PintuanPaymentEvent;
use App\Events\PintuanRefundsEvent;
use App\Events\SystemEvent;
use App\Models\FightGroup;
use App\Models\FightGroupMember;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ShopClose;
use App\Models\ShopDisable;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class PinTuanComplete extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pintuan:complete {--fight_group_ids=}';

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
//        $fight_group_ids_params = $this->option('fight_group_ids');
//        if ($fight_group_ids_params) {
//            $fight_group_ids = explode(',', $fight_group_ids_params);
//            foreach ($fight_group_ids as $fight_group_id) {
//                $order_center_no = FightGroupMember::where(['fight_group_id' => $fight_group_id, 'is_creator' => 1])->value('order_no');
//                $order = Order::where('center_order_no', $order_center_no)->first();
//                $order && event(new PintuanPaymentEvent($fight_group_id, $order));
//            }
//        }

//        $fight_group_id = 'c366aebe04434d7fa395117391b4c765';
//        $shop_id = 'bed7j49g7211908312';
//        $member_order = FightGroupMember::where(['fight_group_id'=>$fight_group_id,'is_del'=>0,'join_success'=>1])
//            ->leftJoin('order','order.order_id','=','fightgroupmember.order_no')
//            ->select(['order.shop_id','order.user_id','order.center_order_no','order.nickname',
//                'order.content_id','order.content_type', 'order.avatar','order.price','order.order_id',
//                'order.pay_time','order.source', 'fightgroupmember.fight_group_id'])
//            ->get();
//        if($member_order->pluck('center_order_no')->isNotEmpty()){
//            foreach ($member_order->pluck('center_order_no') as $order_no){
//                $appId = config('define.order_center.app_id');
//                $appSecret = config('define.order_center.app_secret');
//                $timesTamp = time();
//                $param = [
//                    'extra_data'    => [
//                        'fight_group_id' => $fight_group_id
//                    ]
//                ];
//                $client = hg_verify_signature($param,$timesTamp,$appId,$appSecret, $shop_id);
//                try{
//                    $url = str_replace('{order_no}', $order_no, config('define.order_center.api.m_order_confirm'));
//                    $res = $client->request('PUT',$url);
//                    $return = $res->getBody()->getContents();
//                    event(new CurlLogsEvent($return,$client,$url));
//                }catch (Exception $exception){
//                    event(new ErrorHandle($exception,'order_center'));
//                }
//            }
//
//        }

//        $param = [
//            'order_no'  => '76U96iQBoVqbhqDLDktmaRNLJgkrY4',
//            'status'    => 'success',
//            'pay_channel'   => 'weapp',
//            'receipt_amount'    => 39900
//        ];
//        $shop_id = 'bed7j49g7211908312';
//
//        $appId = config('define.order_center.app_id');
//        $appSecret = config('define.order_center.app_secret');
//        $timesTamp = time();
//        $client = hg_verify_signature($param,$timesTamp,$appId,$appSecret,$shop_id);
//        $url = str_replace('{order_no}', $param['order_no'], config('define.order_center.api.order_undeliver'));
//        try {
//            $response = $client->request('PUT', $url, ['query' => $param]);
//        }catch (Exception $exception){
//            event(new ErrorHandle($exception,'order_center'));
//            return false;
//        }
//        event(new CurlLogsEvent($response,$client,$url));

//        $shop_id = 'bed7j49g7211908312';
//        $user_id = '154fd2a050a796a661772597e1607b47';
//        $content_id = 'l348jl2m8627';
//        $content_type = 'course';
//        $nickname = 'Q宝Ddad';
//        $content_title = '德扑核心理论·听相声学德扑';
//        $avatar = 'http://thirdwx.qlogo.cn/mmopen/vi_32/Ucflic3nnR3kGnb571HGDR4HWakdYV9p2EHttP4Deo8Nx0TxG2YdzXegDejWANGuQsu5icRU1m9EfbXWephP6rXw/132';
//        $content_indexpic = 'https://duanshu-1253562005.picsh.myqcloud.com/2018/07/20/20/bed7j49g7211908312/common/content/1532089342736_952233.jpg';
//        $order_id = '1808131449173388897784';
//        $payment[] = [
//            'user_id' => $user_id,
//            'nickname' => $nickname,
//            'avatar' => $avatar,
//            'payment_type' => 1,
//            'content_id' => $content_id,
//            'content_type' => $content_type,
//            'content_title' => $content_title,
//            'content_indexpic' => $content_indexpic,
//            'order_id' => $order_id,
//            'order_time' => '1534146898',
//            'price' => 399.00,
//            'shop_id' => $shop_id,
//        ];
//        Payment::insert($payment);
//        Cache::forever('payment:' . $shop_id . ':' . $user_id . ':' . $content_id . ':' . $content_type, $order_id);
//        echo Cache::get('payment:'.$shop_id.':'.$user_id.':'.$content_id.':'.$content_type);
    }

}

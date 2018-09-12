<?php

namespace App\Console\Commands;

use App\Events\SendMessageEvent;
use App\Events\SystemEvent;
use App\Models\Manage\VersionExpire;
use App\Models\Shop;
use App\Models\ShopClose;
use App\Models\SystemNotice;
use App\Models\UserShop;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use App\Models\Protocol;
use App\Models\ShopProtocol;

class ChangeVersion extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'version:expire';

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
        $expire = VersionExpire::where('expire','<',time())
            ->where('version','advanced')
            ->where('is_expire',0)
            ->get();

        if( $expire){
            foreach ($expire as $item){
                $unexpire = VersionExpire::where('hashid',$item->hashid)
                    ->where('version','advanced')
                    ->where('start','<',time())
                    ->where('expire','>',time())
                    ->select('id')
                    ->first();
                if(!$unexpire){//如果没有其他未到期记录,店铺到期
                    $sc = new ShopClose();
                    $sc->shop_id = $item->hashid;
                    $sc->method = 'expire';
                    $sc->reason = 'advanced_expire';
                    $sc->event_time = time();
                    $sc->process_time = strtotime('+7 days');
                    $sc->save();
                    $time = strtotime('+7 days');
                    $y = date('Y', $time);
                    $m = date('m', $time);
                    $d = date('d', $time);
                    SystemNotice::sendShopSystemNotice($item->hashid, 'notice.title.advanced_expire',
                        'notice.content.advanced_expire', ['year' => $y, 'month' => $m, 'day' => $d]);

                    $expire_shop_id[] = $item->hashid;
                }
                $ids[] = $item->id;
            }
            if(isset($expire_shop_id)){
                Shop::whereIn('hashid',$expire_shop_id)
                    ->where('verify_status','success')
                    ->update(['verify_status'=>'invalid','verify_expire'=>time()]);
                $this->setProtocolStatus($expire_shop_id);
            }
            if(isset($ids)){
                VersionExpire::whereIn('id',$ids)->update(['is_expire'=>1]);
            }
        }

        $shops = Shop::where(['version'=>'basic'])->get();
        $this->processSendNotice($shops); //处理消息发送
    }

    private function processSendNotice($shops)
    {
        foreach ($shops as $shop) {
            if ($shop->verify_expire != 0) {
                if (strtotime(date('YmdHi', $shop->verify_expire)) == strtotime('+30day', strtotime(date('YmdHi', time())))) {
                    $content = trans('notice.content.verify.soon');
                    $content = str_replace('{verify_expire}', date('Y年m月d日', $shop->verify_expire), $content);
                    event(new SystemEvent($shop->hashid, '认证即将到期', $content, 0, -1, '系统管理员'));
                }
                if (strtotime(date('YmdHi', $shop->verify_expire)) == strtotime('+7day', strtotime(date('YmdHi', time())))) {
                    $content = trans('notice.content.verify.soon');
                    $content = str_replace('{verify_expire}', date('Y年m月d日', $shop->verify_expire), $content);
                    event(new SystemEvent($shop->hashid, '认证即将到期', $content, 0, -1, '系统管理员', 1));
                    $user_shop = UserShop::where(['shop_id' => $shop->hashid, 'admin' => 1])->first();
                    $mobile = $user_shop->user ? $user_shop->user->mobile : '';
                    $year = date('Y', $shop->verify_expire);
                    $month = date('m', $shop->verify_expire);
                    $day = date('d', $shop->verify_expire);
                    $mobile && event(new SendMessageEvent($mobile, 'duanshu-cert-overdue', ['year' => $year, 'month' => $month, 'day' => $day]));
                }
                if (strtotime(date('YmdHi', $shop->verify_expire)) == strtotime(date('YmdHi', time()))) {
                    $content = trans('notice.content.verify.expire');
                    $content = str_replace('{protect_date}', date('Y年m月d日', strtotime('+7day', $shop->verify_expire)), $content);
                    event(new SystemEvent($shop->hashid, '认证已到期', $content, 0, -1, '系统管理员', 1));
                    Shop::where('hashid',$shop->hashid)->where('verify_status','success')->update(['verify_status'=>'invalid']);
                    $sc = new ShopClose();
                    $sc->shop_id = $shop->hashid;
                    $sc->method = 'expire';
                    $sc->reason = 'basic_expire';
                    $sc->event_time = time();
                    $sc->process_time = strtotime('+7 days');
                    $sc->save();
                }
            }
            VersionExpire::where('hashid',$shop->hashid)->where('is_expire',0)->where('expire','<',time())->update(['is_expire'=>1]);
        }
    }

    /**
     * 版本过期设置协议无效状态
    */
    public function setProtocolStatus($shop)
    {
        $id = Protocol::where('type','order')->value('id');
        if($id){
            $info = ShopProtocol::where('p_id',$id)->where('status','!=',3)->whereIn('shop_id',$shop)->get();
            if(!$info->isEmpty()){
                foreach($info as $value){
                    $value->status = 3;
                    $value->save();
                }
            }
        }
    }

}

<?php
/**
 * 短书系统消息
 */

namespace App\Http\Controllers\Admin\Notice;


use App\Events\SystemEvent;
use App\Http\Controllers\Admin\BaseController;
use App\Models\Shop;
use App\Models\SystemNotice;
use App\Models\SystemNoticeUser;
use App\Models\User;
use App\Models\UserShop;
use App\Models\VersionExpire;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Maatwebsite\Excel\Facades\Excel;


class SystemNoticeController extends BaseController
{

    /**
     * 系统通知列表接口
     */
    public function lists(){

        $count = request('count') ? : 10;
        $result = SystemNotice::where(['is_del'=>0,])
            ->where(function($query){
                $query->where('system_notice.shop_id',-1)
                    ->orWhere('system_notice.shop_id',$this->shop['id']);
            })
            ->leftJoin('system_notice_user as nu',function($join){
                $join->on('system_notice.id','=','nu.notice_id')
                    ->where('nu.shop_id',$this->shop['id']);
            })
            ->orderBy('send_time','desc')
            ->select('system_notice.id','title','content','send_time','is_read')
            ->paginate($count);

        $data = $this->listToPage($result);
        foreach ($data['data'] as $item){
            $item->send_time = $item->send_time ? date('Y-m-d H:i:s',$item->send_time) : '';
            $item->is_read = $item->is_read ? 1 : 0;
            $item->content = $this->processCountDown($item);
        }
        return $this->output($data);
    }

    /**
     * 系统通知详情接口 设置为已读
     */
    public function detail(){
        $this->validateWithAttribute(
            ['id' => 'required|numeric'],['id'=>'系统通知id']
        );
        $notice = SystemNotice::where('system_notice.shop_id' ,$this->shop['id'])->findOrFail(request('id'));
        SystemNoticeUser::updateOrCreate(['shop_id' => $this->shop['id'],'notice_id' => request('id')],['is_read' => 1]);
        $notice->send_time = $notice->send_time ? date('Y-m-d H:i:s',$notice->send_time) : '';
        $notice->is_read = 1;
        $notice->content = $this->processCountDown($notice);
        $notice->makeHidden('is_del');
        return $this->output($notice);
    }

    //处理倒计时
    private function processCountDown($notice){
        $expire = VersionExpire::where('hashid',$this->shop['id'])->value('expire');
        $day = floor(($expire-time())/(3600*24)); //倒计时天数
        $hour = floor(($expire-time()-$day*3600*24)/(3600)); //倒计时小时
        $content = str_replace('{count_down}',$day.'天'.$hour.'小时',$notice->content);
        return $content;
    }


    /**
     * @return \Illuminate\Http\JsonResponse
     * 横幅详情
     */
    public function bannerDetail(){
        $shop = Shop::where(['hashid'=>$this->shop['id']])->first();
        if($shop->version == 'advanced' || ($shop->verify_status=='success' && $shop->verify_expire > time())){
            $notice = [];
        }else{
            $sql = SystemNotice::select('*')->where('top',1)->where('is_del',0);
            $sql->where(function($query){
                $query->where('system_notice.shop_id',-1)
                    ->orWhere('system_notice.shop_id',$this->shop['id']);
            });
            $notice = $sql->orderByDesc('send_time')->first();
            $notice && $notice->content = $this->processCountDown($notice);
        }
        return $this->output($notice?:[]);
    }

    //同步老用户未认证通知
    public function sendUnVerify(){
        $sql = Shop::where('verify_status','!=','success');
        request('shop_id') && $sql->where(['hashid'=>request('shop_id')]);
        $shops = $sql->get();
        if($shops){
            foreach ($shops as $shop){
                if($shop->version=='basic'){
                    $version_expire = VersionExpire::where('hashid',$shop->hashid)->first();
                    if(!$version_expire){
                        $version_expire = new VersionExpire();
                        $version_expire->hashid = $shop->hashid;
                        $version_expire->version = VERSION_BASIC;
                        $version_expire->start = time();
                        $version_expire->expire = strtotime('+7day',time());
                        $version_expire->is_expire = 0;
                        $version_expire->method = 2;
                        $version_expire->save();
                    }
                }
                $content = trans('notice.content.verify.old_not');
                $content = str_replace('{expire_date}',date('Y年m月d日',strtotime('+7day',time())),$content);
                event(new SystemEvent($shop->hashid,'认证提醒',$content,0, -1, '系统管理员',1));
            }
        }
        return $this->output(['success'=>1]);
    }

    public function statisticsShopMobile(){
        $mobile = UserShop::where(['admin'=>1])->join('users','user_shop.user_id','=','users.id')->whereNotNull('users.mobile')->pluck('users.mobile')->toArray();
        if($mobile) {
            foreach ($mobile as $item){
                $data[] = ['mobile'=>$item];
            }
            Excel::create('店铺管理员手机号', function ($excel) use ($data) {
                $excel->sheet('mobile', function ($sheet) use ($data) {
                    $sheet->fromArray($data, null, 'A1', false, false);
                });
            })->export('xls');
        }
    }

    /**
     * 点击未读通知列表时 更新阅读状态
     */
    public function update(){
        $this->validateWithAttribute(
            ['id' => 'required|numeric'],['id'=>'系统通知id']
        );
        SystemNoticeUser::updateOrCreate(['shop_id' => $this->shop['id'],'notice_id' => request('id')],['is_read' => 1]);
        return $this->output(['success' => 1]);
    }

    /**
     * 未读系统通知列表
     */
    public function noReadLists(){
        $noticeIds = SystemNoticeUser::where('shop_id',$this->shop['id'])
            ->where('is_read',1)
            ->pluck('notice_id');
        $count = request('count') ? : 10;
        $result = SystemNotice::whereNotIn('id',$noticeIds)
            ->where(function($query){
                $query->where('shop_id',$this->shop['id'])->orWhere('shop_id',-1);
            })
            ->where('is_del',0)
            ->orderBy('send_time','desc')
            ->select('id','title','content','send_time','shop_id')
            ->paginate($count);
        $data = $this->listToPage($result);
        foreach ($data['data'] as $item){
            $item->send_time = $item->send_time ? date('Y-m-d H:i:s',$item->send_time) : '';
        }
        return $this->output($data);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * 获取某条消息之后的消息数量
     */
    public function noticeNum(){
        $this->validateWithAttribute(['id'=>'required'],['id'=>'消息id']);
        $notice = SystemNotice::where(['id'=>request('id')])->first();
        $num = SystemNotice::where('send_time','>',$notice->send_time)->where('is_del',0)
            ->where(function($query){
                $query->where('shop_id',$this->shop['id'])->orWhere('shop_id',-1);
            })
            ->count();
        return $this->output(['noticeNum'=>intval($num)]);
    }


}
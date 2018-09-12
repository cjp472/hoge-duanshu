<?php
/**
 * 短书系统消息
 */

namespace App\Http\Controllers\Admin\Notice;


use App\Http\Controllers\Admin\BaseController;
use App\Models\AppletCommit;
use App\Models\AppletNoticeUser;
use App\Models\AppletRelease;
use App\Models\AppletSubmitAudit;
use App\Models\Manage\AppletNotice;
use App\Models\Manage\AppletTemplate;


class AppletNoticeController extends BaseController
{

//    /**
//     * 小程序通知列表接口
//     */
//    public function lists(){
//        $result = AppletNotice::where('applet_notice.shop_id',$this->shop['id'])
//            ->leftJoin('applet_notice_user as nu',function($join){
//                $join->on('applet_notice.id','=','nu.notice_id')
//                    ->where('nu.shop_id',$this->shop['id']);
//            })
//            ->orderBy('send_time','desc')
//            ->select('applet_notice.*','nu.is_read')
//            ->paginate(request('count') ? : 10);
//
//        $data = $this->listToPage($result);
//        foreach ($data['data'] as $item){
//            $item->send_time = $item->send_time ? hg_format_date($item->send_time) : 0;
//            $item->is_read = $item->is_read ? 1 : 0;
//        }
//        return $this->output($data);
//    }

    /**
     * 小程序通知详情接口
     */
    public function detail(){
        $checkApplet = $this->checkShopApplet();
        $notice = [];
        if($checkApplet){
            $notice = $this->getNewNotice();
            if($notice){
                $notice->send_time = $notice->send_time ? hg_format_date($notice->send_time) : 0;
            }
        }
        return $this->output($notice?:[]);
    }

    /**
     * 点击X时 更新阅读状态
     */
    public function update(){
        $this->validateWithAttribute(['id' => 'required|numeric'],['id'=>'小程序通知id']);
        AppletNoticeUser::updateOrCreate(['shop_id' => $this->shop['id'],'notice_id' => request('id')],['is_read' => 1]);
        return $this->output(['success' => 1]);
    }

    //获取最新一条未读消息
    private function getNewNotice(){
        $noticeIds = AppletNoticeUser::where('shop_id',$this->shop['id'])
            ->where('is_read',1)
            ->pluck('notice_id');
        $result = AppletNotice::whereNotIn('id',$noticeIds)
            ->where(function ($query){
                $query->where('shop_id',$this->shop['id'])->orWhere('shop_id',-1);
            })
            ->where('is_del',0)
            ->orderBy('send_time','desc')
            ->select('id','content','send_time','shop_id')
            ->first();
        return $result;
    }

    /**
     * 小程序通知状态（角标）
     */
    public function status(){
        $checkApplet = $this->checkShopApplet();
        if($checkApplet){
            $applet_commit = AppletCommit::where(['shop_id' => $this->shop['id'], 'appid' => $checkApplet->appid])->orderBy('create_time', 'desc')->first();
            $applet_release = AppletRelease::where(['shop_id' => $this->shop['id'], 'appid' => $checkApplet->appid])->orderBy('release_time', 'desc')->first();
            $appletTemplate = AppletTemplate::where(['edition'=>'basic','is_display'=>1])->orderByDesc('user_version')->first();
            $notice = $this->getNewNotice();
            $status = $notice ? 1 : (($appletTemplate->user_version > $applet_commit->user_version)||($appletTemplate->template_id > $applet_release->template_id) ? 1 : 0);
        }else{
            $status = 0;
        }
        return $this->output(['status'=>$status]);
    }


    private function checkShopApplet(){
        $submit_audit = AppletSubmitAudit::where(['shop_id'=>$this->shop['id'], 'status' => 0, 'is_release' => 1])->first();
        return $submit_audit;
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * 获取小程序模板版本
     */
    public function version(){
        $appletTemplate = AppletTemplate::where(['edition'=>'basic','is_display'=>1])->orderByDesc('user_version')->first();
        if($appletTemplate){
            $appletTemplate->create_time = $appletTemplate->create_time ? date('Y-m-d H:i:s', $appletTemplate->create_time) : '';
            $appletTemplate->is_display = intval($appletTemplate->is_display);
        }
        return $this->output($appletTemplate?:[]);
    }


}
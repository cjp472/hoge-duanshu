<?php
/**
 * Created by PhpStorm.
 * User: an
 * Date: 2018/3/28
 * Time: 上午9:35
 */
namespace App\Http\Controllers\Manage\Notice;

use App\Events\AppletNoticeEvent;
use App\Http\Controllers\Manage\BaseController;
use App\Models\AppletSubmitAudit;
use App\Models\Manage\AppletNotice;

class AppletNoticeController extends BaseController{


    /**
     * @return \Illuminate\Http\JsonResponse
     * 新建小程序更新通知
     */
    public function appletNoticeCreate(){
        $this->validateWithAttribute([
            'shop_id'   => 'alpha_dash',
            'content'   => 'required',
            'user_id'   => 'numeric',
            'user_name' => 'alpha_dash'
        ],[
            'shop_id'   => '接收消息店铺id',
            'content'   => '消息内容',
            'user_id'   => '发送人id',
            'user_name' => '发送人名称',
        ]);
        event(new AppletNoticeEvent(request('shop_id')?:-1,request('content'),$this->user['id'],$this->user['name']));
        return $this->output([
            'content'   => request('content'),
            'user_name' => $this->user['name'],
            'send_time' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * 消息更新
     */
    public function appletNoticeUpdate(){
        $this->validateWithAttribute([
            'id'        => 'required',
            'shop_id'   => 'alpha_dash',
            'content'   => 'required',
            'user_id'   => 'numeric',
            'user_name' => 'alpha_dash'
        ],[
            'id'        => '消息id',
            'shop_id'   => '接收消息店铺id',
            'content'   => '消息内容',
            'user_id'   => '发送人id',
            'user_name' => '发送人名称',
        ]);
        $data = AppletNotice::find(request('id'));
        $data->content = request('content');
        $data->send_time = time();
        $data->user_id = $this->user['id'];
        $data->user_name = $this->user['name'];
        $data->save();
        return $this->output(['success'=>1]);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * 小程序通知列表
     */
    public function appletNoticeLists(){
        $sql = AppletNotice::select('*');
        request('content') && $sql->where('content','like','%'.request('content').'%');
        $applet_notice  = $sql->orderBy('send_time','desc')->paginate(request('count')?:15);
        foreach ($applet_notice as $item) {
            $item->send_time = $item->send_time ? hg_format_date($item->send_time) : '';
        }
        return $this->output($this->listToPage($applet_notice));
    }

    /**
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     * 小程序通知详情
     */
    public function appletNoticeDetail($id){
        $data = AppletNotice::find($id);
        $data && $data->send_time && $data->send_time = hg_format_date($data->send_time);
        return $this->output($data);
    }

    /**
     * @param
     * @return \Illuminate\Http\JsonResponse
     * 小程序通知删除
     */
    public function appletNoticeDelete(){
        $data = AppletNotice::find(request('id'));
        $data->is_del = request('del');
        $data->save();
        return $this->output($data);
    }



}
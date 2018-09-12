<?php

/**
 * Created by PhpStorm.
 * User: zhoujie
 * Date: 2017/9/4
 * Time: 下午2:58
 */
namespace App\Http\Controllers\Manage\OpenPlatform;

use App\Http\Controllers\Manage\BaseController;
use App\Http\Requests\Request;
use App\Models\Manage\AppletCommit;
use App\Models\Manage\AppletSubmitAudit;
use App\Models\Manage\AppletTemplate;
use App\Models\Manage\OpenPlatformApplet;
use App\Models\Manage\OpenPlatformPublic;
use App\Models\Manage\Users;
use App\Models\Manage\UserShop;

class OpenPlatformController extends BaseController
{
    /**
     * OpenPlatformController constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @return mixed
     * 小程序授权列表
     */
    public function appletAuthorizedLists()
    {
        $this->validateWith([
            'count'      => 'numeric|max:10000',
            'start_time' => 'date',
            'end_time'   => 'date',
            'appid'      => 'alpha_dash',
            'shop_id'    => 'alpha_dash',
//            'mobile'     => 'regex:/^(1)[3,4,5,7,8]\d{9}$/'
        ]);
        $data = $this->getAppletAuthorizedLists();
        if ($data['data']) {
            foreach ($data['data'] as $item) {
                $item->mobile = UserShop::where('shop_id',$item->shop_id)->first() && UserShop::where('shop_id',$item->shop_id)->first()->user ? UserShop::where('shop_id',$item->shop_id)->first()->user->mobile : '';
                $this->handleAuthorized($item);
            }
        }
        return $this->output($data);
    }

    /**
     * @return array
     */
    private function getAppletAuthorizedLists()
    {
        $shop = [];
        if($val = request('key')){
            $key = hg_search_type($val);
            $search[$key] = $val;
            request()->merge($search);
        }
        if(request('mobile')){
            $shop = Users::where('mobile','like','%'.request('mobile').'%')->leftJoin('user_shop as us','us.user_id','=','users.id')->pluck('shop_id')->toArray();
        }
        $shop_id = $shop ? : (request('shop_id') ? [trim(request('shop_id'))] : []);
        $authorized_lists = OpenPlatformApplet::select('id', 'shop_id', 'appid', 'primitive_name', 'diy_name',
            'create_time', 'update_time', 'is_commit', 'is_domain');
        $shop_id && $authorized_lists->whereIn('shop_id', $shop_id);
        request('appid') && $authorized_lists->where('appid', request('appid'));
        request('name') && $authorized_lists->where('diy_name','like','%'.request('name').'%');
        $start_time = request('start_time') ? strtotime(request('start_time')) : 0;
        $end_time = request('end_time') ? strtotime(request('end_time')) : time();
        $count = request('count') ?: 50;
        $page = $authorized_lists
            ->whereBetween('create_time', [$start_time, $end_time])
            ->orderBy('create_time', 'desc')
            ->paginate($count);
        return $this->listToPage($page);
    }

    /**
     * @param $item
     */
    private function handleAuthorized($item)
    {
        $item->create_time = $item->create_time ? date('Y-m-d H:i:s', $item->create_time) : '';
        $item->update_time = $item->update_time ? date('Y-m-d H:i:s', $item->update_time) : '';
        $item->authorizer_info = $item->authorizer_info ? unserialize($item->authorizer_info) : [];
        $item->is_release = $item->submitAudit && !$item->submitAudit->isEmpty() ? intval($item->submitAudit->last()->getAttribute('is_release')) : 0;
        $item->makeHidden('submitAudit');
    }

    /**
     * @return mixed
     * 公众号授权列表
     */
    public function publicAuthorizedLists()
    {
        $this->validateWith([
            'count'      => 'numeric|max:10000',
            'start_time' => 'date',
            'end_time'   => 'date',
            'appid'      => 'alpha_dash',
            'shop_id'    => 'alpha_dash'
        ]);
        $data = $this->getPublicAuthorizedLists();
        if ($data['data']) {
            foreach ($data['data'] as $item) {
                $this->handleAuthorized($item);
            }
        }
        return $this->output($data);
    }

    /**
     * @return array
     */
    private function getPublicAuthorizedLists()
    {
        $authorized_lists = OpenPlatformPublic::select('id', 'shop_id', 'appid', 'primitive_name', 'create_time',
            'update_time');
        request('shop_id') && $authorized_lists->where('shop_id', request('shop_id'));
        request('appid') && $authorized_lists->where('appid', request('appid'));
        $start_time = request('start_time') ? strtotime(request('start_time')) : 0;
        $end_time = request('end_time') ? strtotime(request('end_time')) : time();
        $count = request('count') ?: 50;
        $page = $authorized_lists
            ->whereBetween('create_time', [$start_time, $end_time])
            ->orderBy('create_time', 'desc')
            ->paginate($count);
        return $this->listToPage($page);
    }

    /**
     * @return mixed
     * 小程序授权详情
     */
    public function appletAuthorizedDetail()
    {
        $this->validateWith(['id' => 'required']);
        $authorized_detail = OpenPlatformApplet::where('id', request('id'))->first();
        $this->handleAuthorized($authorized_detail);
        return $this->output($authorized_detail);
    }

    /**
     * @return mixed
     * 公众号授权详情
     */
    public function publicAuthorizedDetail()
    {
        $this->validateWith(['id' => 'required']);
        $authorized_detail = OpenPlatformPublic::where('id', request('id'))->first();
        $this->handleAuthorized($authorized_detail);
        return $this->output($authorized_detail);
    }

    /**
     * @return mixed
     * 小程序生成列表
     */
    public function commitLists()
    {
        $this->validateWith([
            'count'        => 'numeric|max:10000',
            'start_time'   => 'date',
            'end_time'     => 'date',
            'appid'        => 'alpha_dash',
            'shop_id'      => 'alpha_dash',
            'template_id'  => 'alpha_num',
            'user_version' => 'alpha_num',
        ]);
        $data = $this->getCommitLists();
        if ($data['data']) {
            foreach ($data['data'] as $item) {
                $item->create_time = $item->create_time ? date('Y-m-d H:i:s', $item->create_time) : '';
            }
        }
        return $this->output($data);
    }

    /**
     * 获取小程序体验二维码，因为平台端已经获取了，所以直接取cos的图片地址
     * @param $data
     * @return string
     */
    private function getTemporaryQrcode($data)
    {
        $file_name = md5($data->shop_id . 'temporaryQrcode');
        $qrcode_url = IMAGE_HOST.'/'.config('qcloud.folder') . '/image/' . $file_name;
        return $qrcode_url;
    }

    /**
     * @return array
     */
    private function getCommitLists()
    {
        $shop = '';
        if($val = request('key')){
            $key = hg_search_type($val);
            $search[$key] = $val;
            request()->merge($search);
        }
        if(request('mobile')){
            $shop = Users::where('mobile','like','%'.request('mobile').'%')->leftJoin('user_shop as us','us.user_id','=','users.id')->pluck('shop_id')->toArray();
        }
        $auth_applet = [];
        if(request('name')){
            $auth_applet = OpenPlatformApplet::where('diy_name','like','%'.request('name').'%')->pluck('appid')->toArray();
        }
        $shop_id = $shop ? : (request('shop_id') ? [trim(request('shop_id'))] : []);
        $appid = $auth_applet ? : (request('appid') ? [trim(request('appid'))] : []);
        $commit_lists = AppletCommit::select('id', 'shop_id', 'appid', 'template_id', 'user_version', 'create_time');
        $shop_id && $commit_lists->whereIn('shop_id', $shop_id);
        $appid && $commit_lists->where('appid', $appid);
        request('template_id') && $commit_lists->where('template_id', request('template_id'));
        request('user_version') && $commit_lists->where('user_version', request('user_version'));
        $start_time = request('start_time') ? strtotime(request('start_time')) : 0;
        $end_time = request('end_time') ? strtotime(request('end_time')) : time();
        $count = request('count') ?: 50;
        $page = $commit_lists
            ->whereBetween('create_time', [$start_time, $end_time])
            ->orderBy('create_time', 'desc')
            ->paginate($count);
        return $this->listToPage($page);
    }

    /**
     * @return mixed
     * 小程序生成详情
     */
    public function commitDetail()
    {
        $this->validateWith(['id' => 'required']);
        $commit_detail = AppletCommit::where('id', request('id'))->firstOrFail();
        $commit_detail->create_time = $commit_detail->create_time ? date('Y-m-d H:i:s',
            $commit_detail->create_time) : '';
        $commit_detail->applet_qrcode = $this->getTemporaryQrcode($commit_detail);
        $commit_detail->value = $commit_detail->value ? unserialize($commit_detail->value) : [];
        $commit_detail->category = $commit_detail->category ? unserialize($commit_detail->category) : [];
        $commit_detail->value['ext_json'] && $commit_detail->ext_json = json_decode($commit_detail->value['ext_json']);
        return $this->output($commit_detail);
    }

    /**
     * @return mixed
     * 小程序审核列表
     */
    public function submitAuditLists()
    {
        $this->validateWith([
            'count'      => 'numeric|max:10000',
            'start_time' => 'date',
            'end_time'   => 'date',
            'appid'      => 'alpha_dash',
            'shop_id'    => 'alpha_dash',
            'auditid'    => 'alpha_num',
            'status'     => 'alpha_num',
        ]);
        if($val = request('key')){
            $key = hg_search_type($val);
            $search[$key] = $val;
            request()->merge($search);
        }
        $data = $this->getSubmitAuditLists();
        if ($data['data']) {
            foreach ($data['data'] as $item) {
                $this->handleSubmitAudit($item);
            }
        }
        return $this->output($data);
    }

    /**
     * @return array
     */
    private function getSubmitAuditLists()
    {
        $shop = '';
        if(request('mobile')){
            $shop = Users::where('mobile','like','%'.request('mobile').'%')->leftJoin('user_shop as us','us.user_id','=','users.id')->pluck('shop_id')->toArray();
        }
        $auth_applet = [];
        if(request('name')){
            $auth_applet = OpenPlatformApplet::where('diy_name','like','%'.request('name').'%')->pluck('appid')->toArray();
        }
        $shop_id = $shop ? : (request('shop_id') ? [trim(request('shop_id'))] : []);
        $appid = $auth_applet ? : (request('appid') ? [trim(request('appid'))] : []);
        $submit_audit_lists = AppletSubmitAudit::leftJoin('applet_release', 'applet_release.sid', '=', 'applet_submitaudit.id')
            ->select('applet_submitaudit.*', 'applet_release.release_time');
        $shop_id && $submit_audit_lists->whereIn('applet_submitaudit.shop_id', $shop_id);
        $appid && $submit_audit_lists->where('applet_submitaudit.appid', $appid);
        request('auditid') && $submit_audit_lists->where('applet_submitaudit.auditid', request('auditid'));
        request('status') != null && $submit_audit_lists->where('applet_submitaudit.status', request('status'));
        $start_time = request('applet_submitaudit.start_time') ? strtotime(request('start_time')) : 0;
        $end_time = request('applet_submitaudit.end_time') ? strtotime(request('end_time')) : time();
        $release = request('is_release');
        if($release != null) {
            $submit_audit_lists->where('applet_submitaudit.is_release',$release);
        }
        $count = request('count') ?: 50;
        $page = $submit_audit_lists
            ->whereBetween('applet_submitaudit.create_time', [$start_time, $end_time])
            ->orderBy('applet_submitaudit.create_time', 'desc')
            ->paginate($count);
        return $this->listToPage($page);
    }

    /**
     * @param $item
     */
    private function handleSubmitAudit($item)
    {
        $item->status = intval($item->status);
        $item->create_time = $item->create_time ? date('Y-m-d H:i:s', $item->create_time) : '';
        $item->audit_time = $item->audit_time ? date('Y-m-d H:i:s', $item->audit_time) : '';
        $item->release_time = $item->release_time ? date('Y-m-d H:i:s', $item->release_time) : '';
        $item->item_list = $item->item_list ? unserialize($item->item_list) : [];
        $item->category = $item->category ? unserialize($item->category) : [];
        $item->status_name = $item->status ? (($item->status == 2) ? '审核中' : '审核失败') : '审核成功';
        $item->release_name = intval($item->is_release) ? '已上线' : '未上线';
    }

    /**
     * @return mixed
     * 小程序审核详情
     */
    public function submitAuditDetail()
    {
        $this->validateWith(['id' => 'required']);
        $submit_audit_detail = AppletSubmitAudit::where('applet_submitaudit.id', request('id'))
            ->leftJoin('applet_release', 'applet_release.sid', '=', 'applet_submitaudit.id')
            ->select('applet_submitaudit.*', 'applet_release.release_time')
            ->first();
        $this->handleSubmitAudit($submit_audit_detail);
        return $this->output($submit_audit_detail);
    }

    /**
     * @return mixed
     * 小程序模板列表
     */
    public function appletTemplateLists()
    {
        $appletTemplate = AppletTemplate::get();
        if ($appletTemplate) {
            foreach ($appletTemplate as $item) {
                $item->create_time = $item->create_time ? date('Y-m-d H:i:s', $item->create_time) : '';
                $item->is_display = $item->is_display ? true : false;
            }
        }
        return $this->output($appletTemplate);
    }

    /**
     * 新增小程序模板
     */
    public function appletTemplateCreate()
    {
        $this->validateWith([
            'appid'        => 'required|alpha_num',
            'title'        => 'required',
            'template_id'  => 'required|alpha_num',
            'user_version' => 'required',
        ]);
        $appletTemplate = new AppletTemplate();
        $appletTemplate->appid = request('appid');
        $appletTemplate->title = request('title');
        $appletTemplate->template_id = request('template_id');
        $appletTemplate->user_version = request('user_version');
        $appletTemplate->edition = request('edition') ?: 'basic';
        $appletTemplate->create_time = time();
        $appletTemplate->is_display = 0;
        $appletTemplate->save();
        $appletTemplate->is_display = false;
        return $this->output($appletTemplate);
    }

    public function appletTemplateUpdate()
    {
        $this->validateWith(['id' => 'required|numeric']);
        if (request('is_display') || request('is_display') === 0) {
            $params = ['is_display' => request('is_display')];
        } else {
            $this->validateWith([
                'appid'        => 'required|alpha_num',
                'title'        => 'required',
                'template_id'  => 'required|alpha_num',
                'user_version' => 'required',
            ]);
            $params = [
                'appid'        => request('appid'),
                'title'        => request('title'),
                'template_id'  => request('template_id'),
                'user_version' => request('user_version'),
                'edition'      => request('edition'),
            ];
        }
        AppletTemplate::where('id', request('id'))->update($params);
        return $this->output(['success' => 1]);
    }

    /**
     * @throws \Exception
     * 删除小程序模板
     */
    public function appletTemplateDelete()
    {
        $this->validateWith(['id' => 'required|numeric']);
        AppletTemplate::where('id', request('id'))->delete();
        return $this->output(['success' => 1]);
    }
}

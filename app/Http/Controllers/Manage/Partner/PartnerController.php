<?php
/**
 * Created by Guhao.
 * User: wzs
 * Date: 17/5/22
 * Time: 下午5:54
 */

namespace App\Http\Controllers\Manage\Partner;

use App\Http\Controllers\Manage\BaseController;
use App\Models\Manage\PartnerApply;

class PartnerController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 获取合伙人列表
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function lists()
    {
        $this->validateWith([
            'page'       => 'numeric',
            'count'      => 'numeric|max:10000',
            'start_time' => 'date',
            'end_time'   => 'date',
            'state'      => 'numeric',  // 状态
            'company'    => 'string',   // 公司名
            'tel'        => 'numeric',
            'email'      => 'string',
            'type'       => 'string|in:platform,partner'
        ]);
        $data = $this->getLists();
        foreach ($data['data'] as $v) {
            $v->apply_time = $v->apply_time ? hg_format_date($v->apply_time) : '';
            $v->operate_time = $v->operate_time ? hg_format_date($v->operate_time) : '';
        }
        return $this->output($data);
    }

    /**
     * 合伙人详情
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function detail($id = '')
    {
        if (!$id) return false;
        $data = PartnerApply::where('id', $id)->first();
        if (!$data) {
            return $this->output([]);
        }
        $data->apply_time = hg_format_date($data->apply_time);
        $data->operate_time = $data->operate_time ? hg_format_date($data->operate_time) : '';
        return $this->output($data);
    }

    /**
     * 合伙人申请状态修改
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function chgState()
    {
        $this->validateWith([
            'id' => 'required | numeric',
            'state' => 'required | numeric|in:0,1,2',
        ]);
        $pat = PartnerApply::find(request('id'));
        $pat->state = request('state');
        $pat->operate_time = time();
        $pat->save();
        return $this->output(['success'=>1]);
    }


    private function getLists()
    {
        $start_time = request('start_time') ?: 0;
        $end_time = request('end_time') ?: time();
        $count = request('count') ?: 15;
        $partner = PartnerApply::whereBetween('apply_time', [$start_time, $end_time]);
        // 状态
        is_numeric(request('state')) && $partner->where('state', (request('state') ?: 0));
        // 公司名
        request('company') && $partner->where('company_name', 'like', '%' .request('company'). '%');
        // 电话
        request('tel') && $partner->where('mobile', 'like', '%' .request('tel'). '%');
        // 邮箱
        request('email') && $partner->where('company_email','like','%'.request('email').'%');
        // 类型
        request('type') && $partner->where('type',request('type'));
        $page = $partner->orderBy('apply_time', 'desc')->paginate($count);
        return $this->listToPage($page);
    }
}

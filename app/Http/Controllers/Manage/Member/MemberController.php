<?php
/**
 * 后台会员
 * Gh 2017-4-26
 */
namespace App\Http\Controllers\Manage\Member;

use App\Http\Controllers\Manage\BaseController;
use App\Models\Manage\Member;
use Illuminate\Support\Facades\Redis;

class MemberController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 会员列表
     */
    public function lists()
    {
        $this->validateWith([
            'count'      => 'numeric|max:10000',
            'consume'    => 'numeric|size:1',
            'nick_name'  => 'alpha_dash|max:64',
            'tel'        => 'regex:/^(1)[3,4,5,7,8]\d{9}$/',
            'start_time' => 'date',
            'end_time'   => 'date',
            'shop_id'    => 'alpha_dash',
            'source'     => 'alpha_dash'
        ]);
        $data = $this->getMemberList();
        if ($data['data']) {
            foreach ($data['data'] as $item) {
                $item->create_time = $item->create_time ? date('Y-m-d H:i:s', $item->create_time) : '';
                $item->birthday = $item->birthday ? date('Y-m-d', $item->birthday) : '';
                $item->makeVisible(['sex', 'email', 'birthday', 'amount', 'create_time','source']);
                $item->setKeyType('string');
                $item->id = $item->uid ?: '';
            }
        }
        return $this->output($data);
    }

    /**
     * 获取会员列表数据
     *
     * @return array
     */
    private function getMemberList()
    {
        $member = Member::select('member.id', 'uid', 'shop_id', 'avatar', 'nick_name', 'sex', 'birthday', 'mobile', 'amount','source', 'member.create_time','shop.title');
        request('consume') && $member->where('amount', '!=', 0);
        request('nick_name') && $member->where('nick_name', 'like', '%' . request('nick_name') . '%');
        request('tel') && $member->where('mobile', request('tel'));
        request('shop_id') && $member->where('shop_id', request('shop_id'));
        request('source') && $member->where('source',request('source'));
        $start_time = request('start_time') ? strtotime(request('start_time')) : 0;
        $end_time = request('end_time') ? strtotime(request('end_time')) : time();
        $count = request('count') ?: 15;
        $page = $member
            ->leftJoin('shop','member.shop_id','=','shop.hashid')
            ->whereBetween('member.create_time', [$start_time, $end_time])
            ->orderBy('member.create_time', 'desc')
            ->paginate($count);
        return $this->listToPage($page);
    }

    /**
     * 用户详细信息
     *
     * @param $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function detail($id)
    {
        $member = Member::where(['uid' => $id])->firstOrFail();
        $member->setKeyType('string');
        $member->birthday = $member->birthday ? date('Y-m-d', $member->birthday) : '';
        $member->makeVisible(['sex']);
        $member->id = $member->uid;
        if ($member->is_black == 1) {
            $member->is_black = true;
        } elseif ($member->is_black == 0) {
            $member->is_black = false;
        }
        if($member->mobile){
            $bind = Member::where(
                [
                    'mobile'=>$member->mobile,
                    'shop_id'=>$member->shop_id
                ])->select('openid','source')->get();
            $member->openid = $bind;
        }else{
            $member->openid = [
                [
                    'openid' =>$member->openid,
                    'source' => $member->source,
                ]
            ];
        }
        return $this->output($member);
    }

    /**
     * 会员黑名单管理
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function memberBlack()
    {
        $this->validateWith([
            'id'    => 'required|alpha_dash',
            'black' => 'required|numeric|in:0,1'
        ]);
        Member::where('uid', request('id'))->update(['is_black' => request('black')]);
        if (intval(request('black')) == 1) {
            Redis::sadd('black:member', request('id'));
        } else {
            Redis::srem('black:member', request('id'));
        }
        return $this->output(['success' => 1]);
    }

    /**
     *
     * 会员内容查看权限设置
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function memberAuth()
    {
        $this->validateWith([
            'member_id' => 'required|alpha_dash',
            'auth'      => 'required|numeric|in:0,1'
        ]);
//        Member::where('uid',request('member_id'))->update(['is_auth'=>request('auth')]);
        $openid = Member::where('uid', request('member_id'))->value('openid');
        Member::where('openid', $openid)->update(['is_auth' => request('auth')]);
        if (intval(request('auth')) == 1) {
            Redis::sadd('auth:member', $openid);
        } else {
            Redis::srem('auth:member', $openid);
        }
        return $this->output(['success' => 1]);

    }

}

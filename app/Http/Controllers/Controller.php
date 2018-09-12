<?php

namespace App\Http\Controllers;

use App\Events\CurlLogsEvent;
use App\Events\ErrorHandle;
use App\Models\FightGroup;
use App\Models\FightGroupActivity;
use App\Models\FightGroupMember;
use App\Models\Member;
use GuzzleHttp\Client;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Cache;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    protected function error($error,$attribute = [])
    {
        if($error){
            $this->errorWithText($error,trans('validation.'.$error,$attribute));
        }
    }

    protected function errorWithText($error,$message)
    {
        if($error && $message){
            $response = new Response([
                'error'     => $error,
                'message'   => $message,
            ], 200);
            throw new HttpResponseException($response);
        }
    }

    protected function output($data = [],$status = 200,$header = [])
    {
        return response()->json([
            'response' => $data
        ],$status,$header);
    }

    protected function listToPage(LengthAwarePaginator $page, $hiddens=[])
    {
        $items = $page->items();
        if($hiddens) {
            foreach ($items as $item) {
                $item->makeHidden($hiddens);
            }
        }
        return [
            'page' => [
                'total'         => $page->total(),
                'current_page'  => $page->currentPage(),
                'last_page'     => $page->lastPage(),
            ],
            'data' => $items,
        ];
    }

    protected function validateWithAttribute($validator,$attribute = [])
    {
        $this->validate(app('request'),$validator,[],$attribute);
    }

    /**
     * curl 封装
     * @param $param
     * @param $url
     * @param string $method
     * @return bool|mixed
     */
    protected function curlClient($param,$url,$method='POST'){
        $client = new Client([
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body'  => $param ? json_encode($param) : '',
        ]);
        try {
            $return = $client->request($method, $url,$param);
        }catch (\Exception $exception){
            event(new ErrorHandle($exception,'duanshu'));
            $this->error('error-curl');
        }
        $response = json_decode($return->getBody()->getContents(), 1);
        event(new CurlLogsEvent(json_encode($response), $client, $url));
        return $response;


    }

    /**
     * 验证拼团信息
     */
    protected function validatePintuan(){
        if(request('fight_group_activity_id')) {
            $fight_group_activity_id = request('fight_group_activity_id');
        }else {
            $fight_group_activity = FightGroupActivity::where(['product_identifier' => request('content_id'), 'product_category' => request('content_type'), 'is_del' => 0,'activation'=>1])->orderByDesc('end_time')->first();
            //活动不存在、删除、未上架等，统一错误处理
            if (!$fight_group_activity || $fight_group_activity->is_del || !$fight_group_activity->on_shelf) {
                $this->error('no-fight-group-activity');
            }
            $fight_group_activity_id = $fight_group_activity->id;
        }

        request()->merge(['fight_group_activity_id'=>$fight_group_activity_id]);

        $param = [
            'fight_group_activity'  => $fight_group_activity_id,
            'member'                => $this->member['id'],
        ];
        if(request('group_id')){
            $param['fight_group'] = request('group_id');
        }
        $appId = config('define.inner_config.sign.key');
        $appSecret = config('define.inner_config.sign.secret');
        $timesTamp = time();
        $client = hg_verify_signature($param,$timesTamp,$appId,$appSecret,$this->shop['id']);
        try{
            if(isset($param['fight_group'])){
                $url = config('define.python_duanshu.api.group_join_check');
            }else {
                $url = config('define.python_duanshu.api.group_create_check');
            }
            $res = $client->request('POST',$url);
            $return = $res->getBody()->getContents();
            event(new CurlLogsEvent($return,$client,$url));
        }catch (\Exception $exception){
            event(new ErrorHandle($exception,'order_center'));
            $this->error('error-curl');
        }

        $response = json_decode($return,1);
        if($response && $response['error_code']){
            $this->errorWithText($response['error_code'],$response['error_message']);
        }




//        $fight_group_activity = FightGroupActivity::where(['product_identifier'=>request('content_id'),'product_category'=>request('content_type'),'is_del'=>0])->orderByDesc('end_time')->first();
//        //活动不存在、删除、未上架等，统一错误处理
//        if(!$fight_group_activity || $fight_group_activity->is_del || !$fight_group_activity->on_shelf){
//            $this->error('no-fight-group-activity');
//        }
//
//        //时区不同处理
//        $fight_group_activity->start_time = strtotime('+8 hour',strtotime($fight_group_activity->start_time));
//        $fight_group_activity->end_time = strtotime('+8 hour',strtotime($fight_group_activity->end_time));
//
//        //未开始
//        if($fight_group_activity->start_time > time()){
//            $this->error('not-start');
//        }
//        //已结束
//        if($fight_group_activity->end_time < time()){
//            $this->error('activity-end');
//        }
//        $this->checkJoinGroup();
//        //参与拼团，验证拼团组是否已经拼团成功
//        if(request('group_id')){
//            $fight_group = FightGroup::find(request('group_id'));
//            //团组不存在或者已删除
//            if(!$fight_group || $fight_group->is_del || $fight_group->status == 'failed'){
//                $this->error('fight-group-not-find');
//            }
//            //团组已拼成功
//            if($fight_group->status == 'complete'){
//                $this->error('fight-group-finish');
//            }
//            $group_member = FightGroupMember::where([
//                'fight_group_id'=>request('group_id'),
//                'member_id'     => $this->member['id'],
//                'is_del'        => 0
//            ])->first();
//            if($group_member){
//                $this->error('already-group-member');
//            }
//            $group_num = Cache::get('pintuan:group:member:num:'.request('group_id'));
//            if($group_num >= $fight_group_activity->people_number){
//                $this->error('fight-group-finish');
//            }
//        }
    }

    /**
     * 验证当前用户是否参与过当前内容的拼团活动
     */
    private function checkJoinGroup(){

        $member_id = Member::where(['uid'=>$this->member['id']])->value('id');

        $fight_group_activity_ids = FightGroupActivity::where(['product_identifier'=>request('content_id'),'product_category'=>request('content_type')])->pluck('id')->toArray();
        $fight_group_member = FightGroupMember::where(['member_id'=>$member_id,'status'=>'complete'])
            ->leftJoin('fightgroup','fightgroup.id','=','fightgroupmember.fight_group_id')
            ->whereIn('fightgroup.fight_group_activity_id',$fight_group_activity_ids)
            ->first();
        //已经购买过
        if($fight_group_member){
            $this->error('goods_already_buy');
        }
    }

    // 是否参与营销活动
    public function is_join_market_activity($type, $hashid) {
        $pur_ids = hg_check_marketing($this->shop['id'], $type);
        $is_marketing = in_array($hashid,$pur_ids)?1:0;
        return intVal($is_marketing);
    }

}

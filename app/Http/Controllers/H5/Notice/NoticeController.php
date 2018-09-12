<?php
/**
 * 我的消息
 */
namespace App\Http\Controllers\H5\Notice;

use App\Http\Controllers\H5\BaseController;
use App\Models\Column;
use App\Models\CommunityNote;
use App\Models\Content;
use App\Models\FightGroup;
use App\Models\InteractNotify;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\MemberNotify;
use App\Models\Notice;
use App\Models\Course;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Http\Request;

class NoticeController extends BaseController
{

    /**
     * 我的消息列表
     */
    public function lists(){
        $applet_audit_status = $this->checkAppletAuditStatus();
        if($applet_audit_status){
            $page = ['total'=>0, 'current_page'=>1, 'last_page'=>0];
            $data = [];
            $response = ['page'=>$page, 'data'=>$data];
            return $this->output($response);    
        }
        $this->validateWithAttribute([
            'page'  => 'numeric',
            'count' => 'numeric|max:10000',
        ],[
            'page'  => '页数',
            'count' => '每页显示条数',
        ]);
        $notify = $this->getNotifyList();
        $response = $this->getNotifyListResponse($notify);
        if (is_null(request('show_page'))){
            $this->cacheReadTime();
        }
        return $this->output($response);

    }


    private function cacheReadTime() {
        Cache::put($this->getCacheKey(), time(),60*24*30*3);
    }

    private function getCacheKey() {
        return 'notify:time:' . $this->shop['id'] . ':' . $this->member['id'];
    }

    private function lastReadTime()
    {
        return Cache::get($this->getCacheKey()) ? : time() - 60*60*24*7;
    }

    /**
     * 获取我的消息列表
     * @return array
     */
    private function getNotifyList(){
        $where = ['recipients'=>$this->member['id']];
        $count = request('count') ? : 10;
//        $mobile = Member::where('uid',$this->member['id'])->value('mobile');
//        $member_ids = Redis::smembers('mobileBind:'.$this->shop['id'].':'.$mobile);
        $member_ids = hg_is_same_member($this->member['id'],$this->shop['id']);
        if($member_ids) {
            $sql = Notice::where(function($query) use ($member_ids){
                        $query->whereIn('recipients',$member_ids)->orWhere([
                            'recipients'    =>-1,
                        ]);
            });
        }else{
            $sql = Notice::where(function($query) use ($where){
                $query->where($where)->orWhere([
                    'recipients'    =>-1,
                ]);
            });
        }
        $notify = $sql->Where('status',1)
            ->where('shop_id',$this->shop['id'])
            ->where('send_time','<',time())
            ->leftJoin('member_notify as mn','mn.notify_id','=','notify.id')
            ->select('notify.*','mn.is_read');
        
        if(request('show_page')) {
            $lastTime = $this->lastReadTime();
            $notify = $notify->where('send_time','>', $lastTime);
            $notify = $notify->whereNull('mn.id')->where(['notify.show_page'=>request('show_page')]);
        }
        $notify = $notify->orderBy('notify.send_time','desc')
                ->paginate($count);
        return $this->listToPage($notify);
    }


    private function checkoutReadNotice($notify){
        // 要处理多个member id 的情况 很麻烦，还是按之前的逻辑走
    }

    /**
     * 获取我的消息返回值
     * @param $list
     * @return mixed
     */
    private function getNotifyListResponse($list){
        if($list){
            foreach($list['data'] as $item){
                $link_info = unserialize($item->link_info);
                $info = [];
                if($link_info && isset($link_info['type'])){
                    $content = $column = $member_card = '';
                    ($link_info['type'] != 'column') && $content= Content::where(['hashid' => $link_info['content_id'],'type' => $link_info['type']])
                        ->select('price','column_id')->first();
                    ($link_info['type'] == 'column') && $column = Column::where('hashid',$link_info['content_id'])->select('price','charge')->first();
                    ($link_info['type'] == 'member_card') && $member_card = MemberCard::where('hashid',$link_info['content_id'])->first();
                    if($content || $column || $member_card){
                        if($link_info['type'] != 'column' && $link_info['type'] != 'live' && $link_info['type'] != 'member_card'){ //除了直播、专栏、会员卡外的内容
                            if($content->price > 0 ){ //收费内容
                                $info = [
                                    'content_id' => $link_info['content_id'],
                                    'out_link'   => isset($link_info['out_link']) ? $link_info['out_link'] : '',
                                    'title'      => $link_info['title'],
                                    'type'       => $link_info['type'],
                                    'pay'        => $content->price > 0 ? 1 : 0,
                                ];
                            }elseif($content->price == 0 && $content->column_id > 0){  //免费内容，判断是否属于收费专栏
                                $column = Column::where('id',$content->column_id)->select('price','charge')->first();
                                $column && $info = [
                                    'content_id' => $link_info['content_id'],
                                    'out_link'   => isset($link_info['out_link']) ? $link_info['out_link'] : '',
                                    'title'      => $link_info['title'],
                                    'type'       => $link_info['type'],
                                    'pay'        => ((intval($column->charge)==1) || ($column->price > 0)) ? 1 : 0,
                                ];
                                !$column && $info = [
                                    'content_id' => '',
                                    'out_link'   => '',
                                    'title'      => '',
                                    'type'       => '',
                                    'pay'        =>  0,
                                ];
                            }else{
                                $info = [
                                    'content_id' => $link_info['content_id'],
                                    'out_link'   => isset($link_info['out_link']) ? $link_info['out_link'] : '',
                                    'title'      => $link_info['title'],
                                    'type'       => $link_info['type'],
                                    'pay'        =>  0,
                                ];
                            }
                        }elseif($link_info['type'] == 'live' || $link_info['type'] == 'member_card'){ //直播内容
                            $info = [
                                'content_id' => $link_info['content_id'],
                                'out_link'   => isset($link_info['out_link']) ? $link_info['out_link'] : '',
                                'title'      => $link_info['title'],
                                'type'       => $link_info['type'],
                                'pay'        => 1 ,
                            ];
                        }else{  //专栏
                            $info = [
                                'content_id' => $link_info['content_id'],
                                'out_link'   => isset($link_info['out_link']) ? $link_info['out_link'] : '',
                                'title'      => $link_info['title'],
                                'type'       => $link_info['type'],
                                'pay'        => ((intval($column->charge)==1) || ($column->price > 0)) ? 1 : 0,
                            ];
                        }
                    }elseif($link_info['type'] == 'outLink' ){
                        $info = [
                            'content_id' => $link_info['content_id'],
                            'out_link'   => isset($link_info['out_link']) ? $link_info['out_link'] : '',
                            'title'      => $link_info['title'],
                            'type'       => $link_info['type'],
                            'pay'        => 0,
                        ];
                    } else{
                        $info = $link_info;
                    }

                }
                if(isset($link_info['fight_group_id'])){
                    $info['fight_group_id'] = $link_info['fight_group_id'];
                    $info['fight_group_activity_id'] = isset($link_info['fight_group_activity_id']) ? $link_info['fight_group_activity_id'] : FightGroup::where(['id'=>$link_info['fight_group_id']])->value('fight_group_activity_id');
                }
                $item->send_time = $item->send_time ? hg_friendly_date($item->send_time) : '';
                $item->link_info = $info;
                $item->is_read = intval($item->is_read);
                $item->makeHidden(['recipients','recipients_name']);
            }
        }
        return $list;
    }

    /**
     * 获取消息详情 并置为已读
     * @param $id
     * @return mixed
     */
    public function detail($id){

        $notice = Notice::findOrFail($id);
        $notice->send_time = $notice->send_time ? date('Y-m-d H:i:s',$notice->send_time) : '';
        $notice->link_info = $notice->link_info ? unserialize($notice->link_info) : [];
        $notice->recipients_name = $notice->member ? $notice->member->nick_name : [];
        MemberNotify::where(['member_id'=>$this->member['id'],'notify_id'=>$id])->updateOrCreate(['member_id'=>$this->member['id'],'notify_id'=>$id,'is_read'=>1]);
        $notice->is_read = 1;
        $notice->makeVisible('is_read');
        return $this->output($notice);
    }

    /**
     * 互动通知列表
     * @return mixed
     */
    public function interactNotify(){
        $count = request('count') ? : 10;
        $applet_audit_status = $this->checkAppletAuditStatus();
        if($applet_audit_status) {
            $page = ['total'=>0, 'current_page'=>1, 'last_page'=>0];
            $data = [];
            $response = ['page'=>$page, 'data'=>$data];
            return $this->output($response);    
        }
        $member_ids = hg_is_same_member($this->member['id'],$this->shop['id']);
        if($member_ids) {
            $notify = InteractNotify::whereIn('member_id',$member_ids)->orderBy('interact_time','desc')->paginate($count);
        }else{
            $notify = InteractNotify::where('member_id',$this->member['id'])->orderBy('interact_time','desc')->paginate($count);
        }
        if($notify->total()){
            foreach ($notify as $item) {
                if($item->content_type=='note'){
                    $item->community_id = CommunityNote::where(['shop_id'=>$this->shop['id'],'hashid'=>$item->content_id])->value('community_id')?:'';
                }
                if($item->content_type=='course'){
                    $c = Course::where(['shop_id'=>$this->shop['id'],'hashid'=>$item->content_id])->select('course_type')->get()->pluck('course_type');
                    $item->course_type = $c->first();
                }
                $item->interact_time = $item->interact_time ? hg_friendly_date($item->interact_time) : '';
                $item->content_indexpic = $item->content_indexpic ? hg_unserialize_image_link($item->content_indexpic) : '';
            }
        }
        Cache::forget('interact:notify:number:'.$this->member['id']);   //清理redis里面存的互动通知数量
        return $this->output($this->listToPage($notify));
    }


    /**
     * @return mixed
     * 系统消息未读数量,未读互动通知数量
     */
    public function unreadNumber(){
        $applet_audit_status = $this->checkAppletAuditStatus();
        if($applet_audit_status){
            $response = [
                'interact_numbers' => 0,
                'system_numbers'   => 0,
                'sum'              => 0
            ];
            return $this->output($response);    
        }
        $interactNum = Cache::get('interact:notify:number:'.$this->member['id']);
        $time = $this->lastReadTime();
        $systemNum = Notice::where(['shop_id'=>$this->shop['id'],'status'=>1])
            ->where(function ($query){
                $query->where('recipients',$this->member['id'])->orWhere('recipients',-1);
            })
            ->where('send_time','>',$time)
            ->where('send_time','<',time())
            ->count();
        return $this->output([
            'interact_numbers' => intval($interactNum),
            'system_numbers'   => intval($systemNum),
            'sum'              => intval($interactNum) + intval($systemNum)
        ]);
    }

    public function ignoreNotice(Request $request, $id) {
        $n = Notice::where('shop_id', $this->shop['id'])->where('id',$id)->firstOrFail();
        MemberNotify::updateOrCreate(['member_id' => $this->member['id'], 'notify_id' => $id],['is_ignored' => 1, 'ignore_time'=>date('Y-m-d H:i:s')]);
        return $this->output(['success'=>1]);   
    }

}
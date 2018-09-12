<?php
/**
 * 直播h5端
 */
namespace App\Http\Controllers\H5\Content;

use App\Events\CommentEvent;
use App\Models\Alive;
use App\Models\AliveMessage;
use App\Http\Controllers\H5\BaseController;
use App\Models\Member;
use App\Models\MemberGag;
use Doctrine\Common\Cache\PredisCache;
use EasyWeChat\Foundation\Application;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use qcloudcos\Cosapi;


class AliveController extends BaseController
{

    /**
     * @return mixed
     *直播消息列表
     */
    public function messageLists(){
        $this->validateWithAttribute(['content_id'=>'required'],['content_id'=>'直播id']);
        $lists = $this->getMessageLists();
        $response = $this->formatMessageLists($lists);
        return $this->output($this->listToPage($response));
    }

    /**
     * 问题区小红点
     */
    public function problemStatus(){
        $this->validateWithAttribute(['content_id'=>'required'],['content_id'=>'直播id']);
        $problemNum = Redis::scard('problem:status:'.$this->shop['id'].':'.request('content_id'));
        $problemNum > 0 && $problemNum = 1;
        return $this->output(['problemStatus'=>$problemNum]);
    }

    /**
     * @return mixed
     * 轮循接口
     */
    public function roundLists(){
        $this->validateWithAttribute(['content_id'=>'required'],['content_id'=>'直播id']);
        $lists = $this->getRoundLists();
//        $response = $this->formatMessageLists($lists);
        return $this->output($lists);
    }

    /**
     *直播消息发送
     */
    public function messageSend(){
        $this->validateMessage();
        $param = $this->formatMessage();
        $id = $this->createMessage($param);
        $response = $this->getResponse($id);
        return $this->output($response);
    }

    /**
     * @return int
     * 直播在线人数统计
     */
    public function onlinePeople(){
        $this->validateWith(['content_id'=>'required','sign'=>'required']);
        $onlinePeople = $this->formatOnlinePeople();
        $showPeople = $this->validateOnlinePeople($onlinePeople);
        $number['data'] = $showPeople?$this->formatMultiple($showPeople):0;
        return $this->output($number);
    }

    //在线数倍数处理
    private function formatMultiple($count){
        return hg_caculate_multiple($count,'online',$this->shop['id']);
    }


    private function formatOnlinePeople(){
        $key = 'onlinePeople:'.$this->shop['id'].':'.request('content_id');
        $end_time = Alive::where('content_id',request('content_id'))->value('end_time');
        $time = ($end_time - time()) > 0 ? ($end_time - time()) : 0;    //直播过期时间
        switch (request('sign')){
            case 'in':
                Redis::sadd($key,$this->member['id']);
                if(Redis::ttl($key) == -1){
                    Redis::expire($key,$time);
                }
                break;
            case 'out':
                Redis::srem($key,$this->member['id']);
                Redis::srem('live:lecture:online:'.$this->shop['id'].':'.request('content_id'),$this->member['id']);
                break;
        }
        return  Redis::scard($key);

    }

    //直播人数转换
    private function validateOnlinePeople($realPeople){
        switch ($realPeople){  //对直播人数进行处理
            case 0 < $realPeople && $realPeople <= 100:
                $showPeople = round($realPeople*1.2);
                break;
            case 100 < $realPeople && $realPeople <= 1000:
                $showPeople = round($realPeople*1.3);
                break;
            case 1000 < $realPeople && $realPeople <= 5000:
                $showPeople = round($realPeople*1.4 - 0.01*$realPeople);
                break;
            case 5000 < $realPeople && $realPeople <= 10000:
                $showPeople = round($realPeople*1.5 - 0.01*$realPeople);
                break;
            default:
                $showPeople = round($realPeople*1.4 - 0.01*$realPeople);
                break;
        }
        return $showPeople;
    }

    /**
     * @return mixed
     * 直播在线人数返回
     */
    public function onlineCount(){
        $this->validateWith(['content_id'=>'required']);
        $key = 'onlinePeople:'.$this->shop['id'].':'.request('content_id');
        $number = Redis::scard($key);
        $showPeople = $this->validateOnlinePeople($number);
        $count['count'] = $showPeople?$this->formatMultiple($showPeople):0;
        return $this->output($count);
    }

    public function messageRead(){
        $this->validateWithAttribute(['content_id'=>'required'],['content_id'=>'直播id']);

        $cid = request('content_id');
        $id = request('id');
        Redis::set('messageRead:'.$this->shop['id'].':'.$cid.':'.$this->member['id'],$id);
        return $this->output(['success'=>1]);
    }

    private function getResponse($id){
        $data = AliveMessage::findOrFail($id);
        if($data->type==1){
            $data->message = trim(request('message'));
        }
        $cid = request('content_id');

        //将发送的消息内容存储到hash中去
        $rHashKey = 'live:msgdata:'.$this->shop['id'].':'.$cid;

        $len = 0 ;
        //设置hash的长度为直播消息队列的score,可以通过score来排序
        if(Redis::exists($rHashKey)){
            $len = Redis::hlen($rHashKey);
        }
        $kid = $len + 1;
        $data->kid = $kid;
        Redis::hset($rHashKey,$id,$data->toJson());

        //将消息的id作为value,lenth作为score存储到消息队列
        $rZsetKey = 'live:msglist:'.$this->shop['id'].':'.$cid;
        Redis::zadd($rZsetKey,$kid,$id);

        //判断是讲师,添加一条讲师队列
        $live = $this->getAliveData();
        if($live->lecturer==1){
            //添加一条讲师的队列,权重值为当前队列的长度+1
            $rLectureZsetKey = 'live:lecturer:msglist:'.$this->shop['id'].':'.$cid;
            $lLen = 1;
            if(Redis::zcard($rLectureZsetKey)){
                $lLen = Redis::zcard($rLectureZsetKey)+1;
            }
            Redis::zadd($rLectureZsetKey,$lLen,$id);
        }

        if($data){
            $response['data'] = $this->formateHashData($data);
            return $response;
        }else{
            return $this->error('no_data');
        }
    }

    /**
     * 处理存储到hash中的数据
     * $data object 直播消息
     * $return object 返回信息
     */
    private function formateHashData($data)
    {
        //判断是否需要赞赏
        $data->admire = in_array($data->member_id,Redis::smembers('admire:'.$this->shop['id'].':'.$data->content_id ) )?1:0;

        //格式化不同类型的消息
        switch ( $data->type ) {
            case 3: //语音消息
                $data->audio = $data->audio ? unserialize($data->audio) : [];
                $data->is_read = 0;
                unset($data->message);
                unset($data->indexpic);
                break;
            case 2: //图片消息
                $data->indexpic = $data->indexpic ? hg_unserialize_image_link($data->indexpic) : [];
                unset($data->message);
                unset($data->audio);
                break;
            default:
                unset($data->indexpic);
                unset($data->audio);
                break;
        }
        //格式化时间
        $data->time =  date('Y-m-d H:i:s',$data->time);

        //如果是回答问题,关联查询出相关的问题信息
        if($data->pid){
            $problem = AliveMessage::where('is_del',0)->find($data->pid);
            if( $problem ) {
                $data->problem_info = [
                    'nick_name' => $problem->nick_name,
                    'problem'   => $problem->message,//因为普通会员仅支持普通消息,所以此处仅处理普通消息
                ];
            }
        }
        //判断是否被禁言
        $data->member_gag = MemberGag::where('shop_id',$this->shop['id'])
            ->where('member_id',$data->member_id)
            ->where('content_id',$data->content_id)
            ->where('content_type','live')
            ->where('is_gag',1)->first() ? 1 : 0;

        //普通会员不输出标签
        $data->tag = $data->tag=='普通会员' ? '' : $data->tag;
        return $data;
    }

    private function createMessage($param){
        $id = AliveMessage::insertGetId($param);
        request('type')==3 && $this->createAudioRead($param['content_id'],$id);
        $id && $param['tag']=='普通会员' && event(new CommentEvent($param['content_id'],'live',$this->shop['id']));
        if(isset($param['problem']) && $param['problem'] ==1 ){
            Redis::sadd('problem:status:'.$this->shop['id'].':'.request('content_id'),$id);
        }
        if(isset($param['pid']) && $param['pid'] !=0 ){
            Redis::srem('problem:status:'.$this->shop['id'].':'.request('content_id'),$param['pid']);
        }
        return $id;
    }

    private function formatMessageLists($lists){
        if($lists){
            foreach ($lists as $v){
                $v = $this->formateHashData($v);
            }
        }
        return $lists;
    }

    private function getAudioRead($data){
        $key = 'liveAudioRead:'.$data->shop_id.':'.$data->content_id.':'.$data->id;
        $member = Redis::lrange($key,0,-1);
        if($member && in_array($this->member['id'],$member)){
            $isRead = 1;
        }else{
            $isRead = 0;
        }
        return $isRead;
    }

    private function getRoundLists(){
        //初始化返回值为空数组
        $listData = [];

        $cid = request('content_id');
        //设置消息内容缓存的key
        $rHashKey = 'live:msgdata:'.$this->shop['id'].':'.$cid;

        //如果没有缓存,则重新生成缓存
        if( !Redis::exists($rHashKey) ){
            $this->reCreateCache( $cid );
        }

        if( request('tag') == 1 ) { //取讲师区数据
            //讲师区消息key
            $rZsetKey = 'live:lecturer:msglist:'.$this->shop['id'].':'.$cid;
        } else { //讨论区区数据
            //讨论区消息key
            $rZsetKey = 'live:msglist:'.$this->shop['id'].':'.$cid;
        }
        //判断直播是否存在,以及开始状态
        $live = Alive::where('content_id',$cid)->first(['start_time','end_time']);
        if(!$live){
            $this->error('no_content');
        }

        //如果直播正在进行中取最新的n条数据
        $count = request('count') ?: 20;
        if ( ($live->start_time < time() && time() < $live->end_time) || intval(request('is_new'))==1) {
            $len = Redis::zcard($rZsetKey);
            $c = $len < 20 ? $len : $count;
            $min = 0-$c;
            $max = -1;
            //返回最新n条数据 -1倒数第一条 -20倒数第20条
            $list = Redis::zrevrange($rZsetKey,$min,$max);
        }else{
            $min = 0;
            $max = $count;
            $list = Redis::zrevrange($rZsetKey,$min,$max);
        }
        $sign = request('sign'); //上下拉动方向
        $kid = intval(request('kid')); //向上或者向下最后一条数据的kid值

        //如果是向上拉,查看上20条数据
        if( $sign == 'up' ) {
            if( $kid == 0 ) { //如果上一条的Kid已经为0,则说明没有数据了
                $list = [];
            } else {
                //取kid对应的前20条数据
                $min = (($kid-$count)>0) ? $kid-$count : 0 ;
                $max = $kid-1;
                $list = Redis::zrevrangebyscore($rZsetKey,$max,$min);
            }
        }

        //如果是向下拉,查看下20条数据
        if( $sign == 'down') {
            $min = $kid+1;
            $max = $kid + $count;
            $list = Redis::zrevrangebyscore($rZsetKey,$max,$min);
        }

        if( isset($list) && is_array($list) && count($list) > 0 ) {
            //取hash中具体内容
            $data = Redis::hmget($rHashKey,$list);
            if($data){
                foreach ( $data as $item){
                    $new_item =  json_decode($item);
                    //格式化返回的数据
                    $listData[] = $this->formateHashData($new_item);
                }
            }
        }

        return $listData;
    }

    //重新生成缓存
    private function reCreateCache($cid)
    {
        $alive = Alive::where(['content_id'=>$cid])->firstOrFail();
        //获取讲师信息
        $person_id = array_pluck(json_decode($alive->live_person, true),'id');

        $liveMessages = AliveMessage::where('shop_id',$this->shop['id'])
            ->where('content_id',$cid)
            ->where('is_del',0)
            ->orderBy('time','asc')
            ->get();

        $teacherKey = 1;
        if($liveMessages->isNotEmpty()){
            foreach ($liveMessages->all() as $key=>$liveMessage){
                //将发送的消息内容存储到hash中去
                $rHashKey = 'live:msgdata:'.$this->shop['id'].':'.$cid;

                $liveMessage->kid  = $kid = $key + 1;
                $id = $liveMessage->id;
                Redis::hset($rHashKey,$id,$liveMessage->toJson());

                //存储内容到普通会员的队列
                $rZsetKey = 'live:msglist:'.$this->shop['id'].':'.$cid;
                Redis::zadd($rZsetKey,$kid,$id);

                //存储内容到讲师的队列
                if(in_array($liveMessage->member_id,$person_id)){
                    $rZsetKey = 'live:lecturer:msglist:'.$this->shop['id'].':'.$cid;
                    Redis::zadd($rZsetKey,$teacherKey,$id);
                    $teacherKey++;
                }
            }
        }
    }

    private function getMessageLists(){
        $sql = AliveMessage::where(['content_id'=>request('content_id'),'shop_id'=>$this->shop['id']]);
        request('tag')==1 && $sql->where('tag','!=','普通会员');
        request('problem')==1 && $sql->where('problem',1);
        $sql->where('is_del',0);
        $list = $sql->orderBy('time','desc')->paginate(request('count')?:10);
        return $list;
    }

    private function formatMessage(){
        $data = [
            'content_id' => request('content_id'),
            'shop_id' => $this->shop['id'],
            'member_id' => $this->member['id'],
            'type' => request('type'),
            'problem' => intval(request('problem')),
            'time' => time(),
            'tag' => '普通会员',
        ];
        switch (request('type')){
//            case 1: $data['message'] = utf8_encode(trim(request('message')))?:$this->error('message_not_null'); break;
            case 1: case 4:$data['message'] = trim(request('message'))?:$this->error('message_not_null'); break;
            case 2: $data['indexpic'] = hg_explore_image_link(request('indexpic')); break;
            case 3: $data['audio'] = serialize(request('audio')); break;
        }
        $live = $this->getAliveData();
        $data['nick_name'] = $this->member['nick_name'];
        $data['avatar'] = $this->member['avatar'];
//        $member = Member::where(['uid'=>$this->member['id'],'shop_id'=>$this->shop['id']])->firstOrFail();
//        if($member){
//            $data['nick_name'] = $member->nick_name?:'';
//            $data['avatar'] = $member->avatar?:'';
//        }else{
//            return $this->error('error_member');
//        }
        if($live->gag == 1 && $live->lecturer!=1){
            return $this->error('all_gag');
        }
        $member_gag = $this->getMemberGag();
        if($member_gag && $member_gag->is_gag ==1 && $live->lecturer!=1){
            return $this->error('live_gag');
        }
        if($live->lecturer == 1){
            $key = 'live:lecture:online:'.$this->shop['id'].':'.request('content_id');
            Redis::sadd($key,$this->member['id']);
            Redis::expire($key,LIVE_LECTURE_STATUS_TIME);
        }
        $live && $data['tag'] = $this->getAliveTag($live);
        return $data;
    }

    private function validateMessage(){
        $this->validateWith([
            'content_id'=>'required',
            'type'=>'required',
            'problem'=>'required',
        ]);
    }

    private function getAliveTag($live){
        $person = json_decode($live->live_person, true);
        $tag = '普通会员';
        if($person && is_array($person)){
            foreach ($person as $v){
                if($this->member['id'] == $v['id']){
                    $tag = $v['tag'];
                }
            }
        }
        return $tag;
    }

    /**
     * @return mixed
     * 消息撤销
     */
    public function messageRevoke(){
        $this->validateWith(['content_id'=>'required','id'=>'required']);
        $this->formatMsgDel();
        $where = [
            'shop_id' => $this->shop['id'],
            'content_id' => request('content_id'),
            'id' => request('id'),
        ];
        AliveMessage::where($where)
            ->where('member_id',$this->member['id'])
            ->update(['is_del'=>1]);
        return $this->output(['success'=>1]);
    }

    private function formatMsgDel(){

        $cid = request('content_id');
        $id = request('id');

        $rZsetKey = 'live:msglist:'.$this->shop['id'].':'.$cid;
        $rLecturerZsetKey = 'live:lecturer:msglist:'.$this->shop['id'].':'.$cid;

        $score = Redis::zscore($rZsetKey,$id); //删除对象的score

        Redis::zrem($rZsetKey,$id);
        Redis::zrem($rLecturerZsetKey,$id);

        //hash表中的key 值
        $rHashKey = 'live:msgdata:'.$this->shop['id'].':'.$cid;
        Redis::hdel($rHashKey,$id);

        $members = Redis::zrangebyscore($rZsetKey,$score,99999);
        if($members && count($members)>0){
            foreach ($members as $v)
            {
                Redis::zincrby($rZsetKey,-1,$v);
                Redis::zincrby($rLecturerZsetKey,-1,$v);
            }
        }
    }

    //获取会员禁言状态
    private function getMemberGag(){
        $mid = hg_is_same_member($this->member['id'],$this->shop['id']);
        return  MemberGag::where(['shop_id'=>$this->shop['id'],
            'content_id'=>request('content_id'),
            'content_type'=>'live'])
            ->whereIn('member_id',$mid)
            ->first();
    }

    /**
     * @return mixed
     * 禁言操作 (管理或讲师可操作)
     */
    public function messageGag(){
        $this->validateWith(['content_id'=>'required','type'=>'required|in:all,single','gag'=>'required|numeric']);
        $mid = request('member_id');
        $cid = request('content_id');
        $gag = intval(request('gag'));
        $alive = $this->getAliveData();
        if($alive->lecturer == 1){
            switch (request('type')){
                case 'single':
                    if($alive->manage==1) {
                        $mg = MemberGag::where('shop_id',$this->shop['id'])
                            ->where('member_id',$mid)
                            ->where('content_id',$cid)
                            ->first();
                        if($mg && $mg->is_gag != $gag ) {
                            $mg->is_gag = $gag;
                            $mg->save();
                        }
                        if(!$mg){
                            $new_mg = new MemberGag([
                                'shop_id' => $this->shop['id'],
                                'member_id' => $mid,
                                'content_id' => $cid,
                                'content_type' => 'live',
                                'is_gag' => 1,
                            ]);
                            $new_mg->save();
                        }
                    }else{
                        return $this->error('no_manage');
                    }
                    break;
                case 'all':
                    Alive::where(['content_id'=>request('content_id')])->update(['gag'=>intval(request('gag'))]);
                    Cache::forever('live:pattern:gag:'.request('content_id'),intval(request('gag')));
                    break;
            }
            return $this->output(['type'=>request('type'),'gag'=>request('gag')]);
        }
        return $this->error('no_lecturer');
    }

    private function getAliveData(){
        $alive = Alive::where(['content_id'=>request('content_id')])->firstOrFail();
        $person_id = array_pluck(json_decode($alive->live_person, true),'id');
        $alive->lecturer = in_array($this->member['id'],$person_id) ? 1 : 0;
        return $alive;
    }

    /**
     * @return mixed
     * 消息删除(管理或讲师可操作)
     */
    public function messageDelete(){
        $this->validateWith(['content_id'=>'required','member_id'=>'required','id'=>'required']);
        $alive = $this->getAliveData();
        if($alive->lecturer == 1){
            if($alive->manage == 1){
                $this->formatMsgDel();
                $where = [
                    'shop_id' => $this->shop['id'],
                    'member_id' => request('member_id'),
                    'content_id' => request('content_id'),
                    'id' => request('id'),
                ];
                AliveMessage::where($where)->update(['is_del'=>1]);
                return $this->output(['success'=>1]);
            }
            return $this->error('no_manage');
        }
        return $this->error('no_lecturer');
    }

    /**
     * @return mixed
     * 结束直播(管理或讲师可操作)
     */
    public function endAlive(){
        $this->validateWith(['content_id'=>'required']);
        $alive = $this->getAliveData();
        if($alive->lecturer==1){
            Alive::where(['content_id'=>request('content_id')])->update(['end_time'=>time()]);
            return $this->output(['success'=>1]);
        }
        return $this->error('no_lecturer');
    }

    /**
     * @return mixed
     *  管理模式开启和关闭
     */
    public function liveManage(){
        $this->validateWith(['content_id'=>'required','manage'=>'required|numeric']);
        $alive = $this->getAliveData();
        if($alive->lecturer==1){
            $where = ['content_id'=>request('content_id')];
            Alive::where($where)->update(['manage'=>intval(request('manage'))]);
            Cache::forever('live:pattern:manage:'.request('content_id'),intval(request('manage')));
            return $this->output(['success'=>1]);
        }
        return $this->error('no_lecturer');
    }

    /**
     * @return mixed
     * 直播模式轮循接口
     */
    public function livePattern(){
        $this->validateWithAttribute(['content_id'=>'required'],['content_id'=>'直播id']);
        $manage = Cache::get('live:pattern:manage:'.request('content_id'));
        $gag = Cache::get('live:pattern:gag:'.request('content_id'));
        $lecturer_online = Redis::scard('live:lecture:online:'.$this->shop['id'].':'.request('content_id'));
        if(request('tag')==1){
            $key = 'alive:message:lecturer:'.$this->shop['id'].':'.request('content_id');
        }else{
            $key = 'alive:message:'.$this->shop['id'].':'.request('content_id');
        }
        $msg = unserialize(Redis::lindex($key,-1));
        return $this->output([
            'gag'       => $gag?:0,
            'manage'    => $manage?:0,
            'online'    => intval($lecturer_online) ? 1 : 0,
            'input'     => Redis::scard('alive:input:status:'.$this->shop['id'].':'.request('content_id'))? 1:0,//0结束输入  大于0正在输入
            'unread'    => ($msg ? $msg->kid : 0)-(Redis::get('messageRead:'.$this->shop['id'].':'.request('content_id').':'.$this->member['id'])?:0),
        ]);
    }

    /**
     * @return mixed
     * 直播语音消息读取接口
     */
    public function audioRead(){
        $this->validateWithAttribute(['content_id'=>'required','id'=>'required'],['content_id'=>'直播id','id'=>'消息id']);
        $content_id = request('content_id');
        $id = request('id');
        $this->createAudioRead($content_id,$id);
        return $this->output(['success'=>1]);
    }

    private function createAudioRead($content_id,$id){
        $key = 'liveAudioRead:'.$this->shop['id'].':'.$content_id.':'.$id;
        $value = $this->member['id'];
        Redis::rpush($key,$value);
    }

    /**
     * 直播问题回答
     */
    public function problemAnswer(){
        $this->validateProblemAnswer();
        $where = ['shop_id'=>$this->shop['id'],'content_id'=>request('content_id'),'id'=>request('id'),'problem'=>1];
        $problem = AliveMessage::where($where)->first();
        if($problem){
            $data = $this->formatProblemAnswer();
            $id = $this->createMessage($data);
            AliveMessage::find(request('id'))->update(['problem_state'=>1]);
            $response = $this->getResponse($id);
            return $this->output($response);
        }
        return $this->error('no_problem');
    }

    private function formatProblemAnswer(){
        switch (request('type')){
            case 1: $data['message'] = trim(request('message'))?:$this->error('message_not_null'); break;
            case 2: $data['indexpic'] = hg_explore_image_link(request('indexpic')); break;
            case 3: $data['audio'] = serialize(request('audio')); break;
        }
        $data['type'] = request('type');
        $data['shop_id'] = $this->shop['id'];
        $data['member_id'] = $this->member['id'];
        $data['content_id'] = request('content_id');
        $data['pid'] = request('id');
        $data['time'] = time();
        //获取当前用户的昵称和头像信息
        $member = Member::where(['uid'=>$this->member['id'],'shop_id'=>$this->shop['id']])->firstOrFail();
        if($member){
            $data['nick_name'] = $member->nick_name?:'';
            $data['avatar'] = $member->avatar?:'';
        }
        //获取当前用户的tag(身份)信息
        $live = $this->getAliveData();
        $live && $data['tag']= $this->getAliveTag($live);
        return $data;
    }


    private function validateProblemAnswer(){
        $this->validateWithAttribute([
            'content_id'=>'required',
            'id'=>'required',
            'type'=>'required'
        ],[
            'content_id'=>'直播id',
            'id'=>'问题消息id',
            'type'=>'消息类型'
        ]);
    }


    /**
     * 讲师正在输入状态设置
     */
    public function inputStatus(){
        $this->validateWithAttribute([
            'content_id'=>'required',
            'input'      => 'required|in:begin,end'
        ],[
            'content_id'=>'直播id',
            'input'      => '进出类型',
        ]);
        if(request('input') == 'begin'){
            Redis::sadd('alive:input:status:'.$this->shop['id'].':'.request('content_id'),$this->member['id']);
            Redis::expire('alive:input:status:'.$this->shop['id'].':'.request('content_id'),LIVE_INPUT_STATUS_TIME); //过期时间设置为12个小时
        }else{
            Redis::srem('alive:input:status:'.$this->shop['id'].':'.request('content_id'),$this->member['id']);
        }
        return $this->output(['success' => 1]);
    }


}
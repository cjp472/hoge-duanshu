<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2017/3/30
 * Time: 09:29
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use App\Models\MemberNotify;
use Illuminate\Support\Facades\DB;

class Notice extends Model
{
    protected $table = 'notify';
    public $timestamps = false;

    protected $visible = ['id','shop_id','sender','sender_name','recipients','recipients_name','content','send_time','status','type','is_read','link_info'];

    public function MemberNotify()
    {
        return $this->hasOne('App\Models\MemberNotify','notify_id','id');
    }

    public function member(){
        return $this->hasOne('App\Models\Member','uid','recipients');
    }

    static function formatQunFaGiftNotice($inviteCode,$member, $shop) {
        $a = [
            "shop_id"=>$shop->hashid,
            "sender"=>-1,
            "send_time"=>time(),
            "sender_name"=>$shop->title,
            "recipients"=>$member->uid,
            "recipients_name"=>$member->nick_name,
            "content"=>"恭喜你！获得一个赠送权益。\n". '['.config('define.content_type.' . $inviteCode->content_type).'] '. $inviteCode->content_title. '。赶紧点击领取吧！',
            "type"=>0,
            "show_page"=>'index',
            "link_info"=>serialize(['out_link'=>'','content_id'=> $inviteCode->id,'type'=>'qunfazengsong','title'=>'点击领取'])
        ];
        return $a;
    }

    public function read($uid){
        MemberNotify::updateOrCreate(['member_id' => $uid, 'notify_id' => $this->id], ['is_read' => 1, 'read_time' => date('Y-m-d H:i:s')]);
    }

    public function ignore($uid)
    {
        MemberNotify::updateOrCreate(['member_id' => $uid, 'notify_id' => $this->id], ['is_ignored' => 1, 'ignore_time' => date('Y-m-d H:i:s')]);
    }

    static function readQunfaZengSongNotify($uid){ //获取我的权益列表后自动阅读掉所有赠送权益消息
        $notify = self::where('notify.recipients',$uid)->where('notify.content','LIKE', '%赠送权益%')
            ->leftjoin('member_notify', 'member_notify.notify_id','=', 'notify.id')
            ->whereNull('member_notify.notify_id')
            ->select('notify.*')
            ->get();
        $rows = [];
        foreach ($notify as $n) {
            $rows[] = [
                'member_id'=>$uid,
                'notify_id'=>$n->id,
                'is_read'=>1,
                'read_time'=> date('Y-m-d H:i:s')
            ];
        }
        DB::table('member_notify')->insert($rows);
    }
}
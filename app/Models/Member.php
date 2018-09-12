<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2017/3/29
 * Time: 15:03
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

use App\Models\CardRecord;
use App\Models\InviteCode;
use App\Models\Code;

class Member extends Model
{
    protected $table = 'member';
    public $timestamps = false;

    public $visible = ['pk','id', 'uid','shop_id','avatar','nick_name','true_name','email','mobile','address','company','position', 'groups','source', 'create_time', 'login_time','amount'];


    const SOURCE = ['app' => 'app', 'applet' => '微信小程序', 'dingdone' => 'dingdone', 'inner' => 'inner', 'smartcity' => 'smartcity', 'wechat' => '微信公众号'];

    const SEX = [0=>'未知',1=>'男',2=>'女'];


    static function verboseSex($sex)
    {
        return array_key_exists($sex, self::SEX) ? self::SEX[$sex] : $sex;
    }

    
    static function verboseSource($source) {
        return array_key_exists($source, self::SOURCE) ? self::SOURCE[$source]:$source;
    }

    public function getUnionUids() {
        if (!property_exists('union_uids', $this) && is_null($this->union_uids)) {
            $this->union_uids = hg_is_same_member($this->uid, $this->shop_id);
        }
        return $this->union_uids;
    }

    /**
     * 是否有全场免费会员卡
     *
     * @param [type] $member_id
     * @param [type] $shopId
     * @return boolean
     */
    static function getFreeMemberCard($member_id,$shopId){
        $union_uids = hg_is_same_member($member_id, $shopId);
        $r = CardRecord::whereIn('member_id', $union_uids)->where(['shop_id'=>$shopId, 'discount'=>'-1'])->where('end_time', '>', time())->orderBy('end_time','desc')->first();
        return $r;
    }

    static function hasFreeMemberCard($memberId,$shopId){
        return boolVal(self::getFreeMemberCard($memberId,$shopId));
    }

    public function myMemberCards() {
        $member_ids = $this->getUnionUids();
        if($this->union_uids){
            $record = CardRecord::whereIn('member_id', $member_ids)->where(['shop_id'=>$this->shop_id])->where('end_time','>',time())->get()->toArray(); //获取该会员订购的所有会员卡（在有效期内的）
        }else{
            $record = CardRecord::where(['member_id'=>$this->uid,'shop_id'=>$this->shop_id])->where('end_time','>',time())->get()->toArray(); //获取该会员订购的所有会员卡（在有效期内的）
        }
        array_multisort(array_column($record,'discount'),SORT_ASC,$record); //根据折扣高低排序数组
        $this->membercards = $record;
        return $record;
    }

    public function hasTheMemberCard($content_id) {
        $member_ids = $this->getUnionUids();
        return CardRecord::where(['card_id' => $content_id])->whereIn('member_id', $member_ids)->where('end_time', '>', time())->first();
    }
    
    public function memberShipPrice($content) {
        // 会员价, 
        // member 会员 content  community 小社群 content 普通内容 （图文，视频等）course 课程 column 专栏
        // return memberShipPrice
        $discount = $this->memberShipDiscount($content);
        $contentPrice = $content->price;
        $price = number_format(round($order_price*(($discount<0?0:$discount)/10),2),2);  //折扣后的价格
        $price = str_replace(',','',$price);
        return $price;
    }
    public function memberShipDiscount($content) {
        // 对某条内容的会员（最低）折扣
        // member 会员 content  community 小社群 content 普通内容 （图文，视频等）course 课程 column 专栏
        // return memberShipPrice
        if ($content->join_membercard && $this->membercards) { // join_membercard 是否参与会员卡 1 参与
            return floatVal($this->membercards[0]['discount']);
        }
        return 10;

    }

    // 我的权益
    public function myPresents() {
        $member_ids = hg_is_same_member($this->uid, $this->shop_id);
        $queryA = DB::table('code')->select('id as a_id', 'code_id as a_code_id')->whereIn('user_id', $member_ids);
        $queryB = DB::table('code')->select('id as b_id', 'code_id as b_code_id')->whereIn('user_id', $member_ids);
        $main = DB::connection('mysql')
            ->table(DB::raw("({$queryA->toSql()}) as hg_tb_a"))
            ->leftjoin(DB::raw("({$queryB->toSql()}) as hg_tb_b"), function ($query) {
                $query->on('tb_a.a_code_id', '=', 'tb_b.b_code_id')->on('tb_a.a_id','>', 'tb_b.b_id');
            })
            ->whereNull('b_id')
            ->select('a_id as id')
            ->mergeBindings($queryA)
            ->mergeBindings($queryB)
            ;
        $codes_id = $main->get()->pluck('id')->toArray();//排除重复的赠送（绑定了同一个手机时）

        $base_sql = Code::join('invite_code', 'invite_code.id', 'code.code_id')
            ->select('invite_code.id as invite_code_id','invite_code.*', 'code.id','code.code','code.status','code.user_id as receiver_id', 'code.user_name as receiver_user_name', 'code.user_avatar as receiver_avatar', 'code.gift_word', 'code.copy','code.mobile')
            ->whereIn('code.status',[0,2])
            ->whereIn('code.id', $codes_id)
            ->orderBy('code.status','asc')
            ->orderBy('invite_code.created_at','desc')
            ;
        // dd($member_ids,$base_sql->toSql(), $base_sql->get());
        return $base_sql;
    
    }


}
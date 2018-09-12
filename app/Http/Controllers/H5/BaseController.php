<?php
/**
 * h5端的基类
 */
namespace App\Http\Controllers\H5;

use App\Http\Controllers\Controller;
use App\Models\LimitPurchase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Collection;


use App\Models\Manage\CardRecord;
use App\Models\Payment;
use App\Models\Promotion;
use App\Models\PromotionContent;
use App\Models\PromotionShop;
use App\Models\AppletSubmitAudit;
use App\Models\OpenPlatformApplet;
use App\Models\MemberCard;
use App\Models\MarketingActivity;
use App\Models\FightGroupActivity;
use App\Models\Shop;

use App\Models;
use App\Models\Member;

class BaseController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {

            $sign = $request->memberInfo ;
            $this->member = $sign ? [
                'id'        => $sign['id'],
                'nick_name' => $sign['nick_name'],
                'openid'    => $sign['openid'],
                'source'    => $sign['source'],
                'avatar'    => $sign['avatar'],
            ] :  [
                'id'        => 'test12345',
                'nick_name' => 'test-member',
                'openid'    => 'test-666666666',
                'source'    => 'wechat',
                'avatar'    => 'http://wx.qlogo.cn/mmopen/PiajxSqBRaEJQ5LJuzIVYAsNdcIpZnIIhmNLH2PhBPhC9rzg8K0P92oQIy9f5ERwibtJTrxwSB791tDGwKSwXufJHQxf02ibicVomj81cGXpa1w/0',
            ] ;

            $shop_id = $request->shop_id;
//            if (!$request->shop_id) {
//                return $this->error('shop-id-empty');
//            }
            
            if($shop_id){
                $key = 'shop:hashid:' . $shop_id;
                if (!Redis::get($key)) {
                    $shop = Shop::where('hashid', $shop_id)->first();
                    if (!$shop) {
                        return $this->error('shop-does-not-exist');
                    }
                    $time = 24 * 60 * 60;
                    Redis::setex('shop:hashid:' . $shop_id, $time, 1);
                }
            }

            $this->shop = [
                'id' => $shop_id,
            ];

            return $next($request);
        });
    }

    public function promoter($shop_id,$member_id,$content_id,$content_type,$content_price)
    {
        $commission = 0;
        $promotion_content = PromotionContent::where(['promotion_content.shop_id' => $shop_id,
            'promotion_content.content_id' => $content_id, 'promotion_content.content_type' => $content_type, 'promotion_content.is_participate' => 1])
            ->leftJoin('promotion_rate', 'promotion_rate.id', 'promotion_content.promotion_rate_id')->first();
        $is_promotion = $promotion_content ? true : false;
        //判断是否参加了推广员
        $member_id = hg_is_same_member($member_id, $shop_id);
        $promoter = Promotion::whereIn('promotion_id', $member_id)
            ->where('shop_id', $shop_id)->where('is_delete', 0)
            ->where('state', 1)
            ->first();
        $is_promoter = $promoter ? true : false;
        if ($promotion_content) {
            $promoter_rate = $promotion_content->promoter_rate;
            $commission = $content_price * $promoter_rate / 100;
        }
        $commission = number_format(round($commission, 2), 2, '.', '');
        return [
            'is_promotion' => $is_promotion,
            'is_promoter' => $is_promoter,
            'money' => str_replace(',', '', $commission),
        ];
    }

    /**
    * 检查当前版本小程序是否审核中
    **/
    public function checkAppletAuditStatus()
    {
        $request = app('request');
        $version = $request->header('x-version');
        $platform = $request->header('x-platform');
        if($platform === 'applet' && $version) {
            $open_platform_applet = OpenPlatformApplet::where('shop_id', $this->shop['id'])->first();
            if ($open_platform_applet) {
                $where = ['shop_id' => $this->shop['id'], 'appid' => $open_platform_applet->appid, 'applet_commit_id' => $version];
                $submit_audit = AppletSubmitAudit::where($where)->orderBy('create_time', 'desc')->first();                
                if($submit_audit && $submit_audit->status === 2) {
                    return true;
                }
            }
        }
        return false;
        // return true;
    }

    public function contentCommonFilters() {
        $applet_audit_status = $this->checkAppletAuditStatus();
        if($applet_audit_status) {
            $filter = ['key' => 'price', 'operator' => '=', 'value' => 0];
            $filters = [];
            array_push($filters, $filter);
            return $filters;
        }
    }

    public function contentTypeFilters() {
        $applet_audit_status = $this->checkAppletAuditStatus();
        if($applet_audit_status) {
            $filter = ['key' => 'type', 'operator' => '=', 'value' => 'article'];
            $filters = [];
            array_push($filters, $filter);
            return $filters;
        }
    }

    public function filterSql($sql, $filters){
        if($filters && count($filters) > 0){
            foreach ($filters as $filter) {
                $sql = $sql->where($filter['key'], $filter['operator'], $filter['value']);
            }
        }
        return $sql;
    }

    //会员价格处理
    /**
     * @param $price 内容价格
     * @param string $cid 内容Id
     * @param string $type 内容类型
     * @param bool $join_membercard 是否适用会员卡
     * @return mixed 折扣后的价格
     */
    public function getDiscountPrice($price,$cid = '',$type = '', $join_membercard=false)
    {
        //获取小程序和h5端会员id
        $mid = hg_is_same_member($this->member['id'],$this->shop['id']);

        $discount_rate = 1; //初始化价格折扣100%

        /*************获取会员卡折扣开始*************/
        //取最小折扣的会员卡
        $memberCard = CardRecord::whereIn('member_id',$mid)
            ->where('shop_id',$this->shop['id'])
            ->where('end_time','>=',time())
            ->where('start_time','<',time())
            ->orderBy('discount','asc')
            ->first(['discount']);

        if( $memberCard && $memberCard->discount && $join_membercard){
            //折扣转换为10%,即0.1 例如: 5折为0.5
            $card_discount = $memberCard->discount>0 ? $memberCard->discount*0.1 : 0;
            //如果开通会员卡,则取会员卡折扣,到期时间未会员卡时间
            if( $card_discount < $discount_rate ) {
                $discount_rate = $card_discount;
            }
        }
        /*************获取会员卡折扣结束*************/

        /*************获取限时购折扣开始*************/
        if($cid && $type){ //如果传入了内容id和type
            $limit_id = Redis::get('purchase:'.$this->shop['id'].':'.$type.':'.$cid);
            //判断此内容是否参与了限时购,且限时购正在进行中,并返回限时购id
            if($limit_id){
                $purchase = LimitPurchase::where([
                    'shop_id'=>$this->shop['id'],
                    'hashid'=>$limit_id]
                )
                    ->where('range',2)
                    ->where('end_time','>=',time())
                    ->where('start_time','<=',time())
                    ->first(['discount']);
                //查看限时购的情况
                if($purchase && $purchase->discount){
                    $purchase_discount = $purchase->discount>0 ? $purchase->discount*0.1 : 0;
                    if( $purchase_discount < $discount_rate) {
                        //如果开通限时购,且限时购价格低于会员卡价格,到期时间为限时购
                        $discount_rate = $purchase_discount;
                    }
                }
            }
        }
        /*************获取限时购折扣结束*************/

        return hg_discount_price($price,$discount_rate);
    }

    /**
     * 返回内容限时购信息
     * @param $price 内容价格
     * @param string $cid 内容Id
     * @param string $type 内容类型
     * @return array
     */
    public function limitPurchase($price,$cid,$type)
    {
        if(!$cid || !$type || !$price) {
            return false;
        }
        $limit_id = Redis::get('purchase:'.$this->shop['id'].':'.$type.':'.$cid);
        //判断此内容是否参与了限时购,且限时购正在进行或即将进行,并返回限时购id
        if($limit_id){
            $purchase = LimitPurchase::where([
                    'shop_id'=>$this->shop['id'],
                    'hashid'=>$limit_id]
            )
                ->where('range',2)
                ->where('end_time','>=',time())
                ->first();
            //查看限时购的情况
            if($purchase && $purchase->discount){
                $purchase_discount = $purchase->discount>0 ? $purchase->discount*0.1 : 0;
                return [
                    'market_sign' => 'limit_purchase',
                    'limit_start' => hg_format_date($purchase->start_time),
                    'limit_end' => hg_format_date($purchase->end_time),
                    'limit_id' => $limit_id,
                    'limit_state'   => $purchase->start_time > time() ? 0 : 1 ,
                    'limit_price'  => hg_discount_price($price,$purchase_discount),
                ];
            }
        }
        return false;
    }

    /**
     * 店铺最高折扣会员卡
     *
     * @return MemberCard instance
     */

     public function shopHighestDiscountMembercard() {
        $membercard = MemberCard::availableMembercards($this->shop['id'])->orderByRaw('CAST(discount AS DECIMAL(2,1))')->first();
        return $membercard;
     }

     public function shopHighestDiscount($membercard,$join_membercard) {
        $m = $membercard ? $membercard:$this->shopHighestDiscountMembercard();
        if(is_null($m) || !$join_membercard) {
            return 10;
        }
        return floatVal($m->discount);

     }
     
     /*
     * 返回内容拼团信息
     * @param $hashid
     * @param string $type 内容类型
     * @return null / fightgroup instance
     */
    public function contentFightGroup($shopId,$shopHashId, $contentType, $hashid) {
        $ac = content_market_activities($shopHashId,$contentType,$hashid);
        if (in_array(MarketingActivity::FIGHTGROUP,$ac)) {
            $utc_now = date_create(null, timezone_open('UTC'));
            $utc_now_str = $utc_now->format('Y-m-d H:i:s');
            $f = FightGroupActivity::where(['shop_id'=>$shopId,
                'product_identifier'=>$hashid,
                'product_category'=>$contentType,
                'is_del'=>0,
                'activation'=>1])
                ->where('start_time', '<=', $utc_now_str)
                ->where('end_time', '>', $utc_now_str)
                ->first();
            return $f;
        } else {
            return null;
        }
    }

    public function getContentByType_($contentType, $contentId, $raise404=true){
        $sql = null;
        $filters = ['shop_id'=>$this->shop['id']];
        switch ($contentType) {
            case 'note':
                $filters['hashid'] = $contentId;
                $sql = Models\CommunityNote::where($filters);
                break;
            
            case 'article':
            case 'video':
            case 'live':
            case 'audio':
                $filters['hashid'] = $contentId;
                $sql = Models\Content::where($filters);
                break;
            case 'course':
                $filters['hashid'] = $contentId;
                $sql = Models\Course::where($filters);
                break;
            case 'column':
                $filters['hashid'] = $contentId;
                $sql = Models\Column::where($filters);
                break;
            default:
                break;
        }
        if($raise404){
            return $sql->firstOrFail();
        }else{
            return $sql->first();
        }
    }

    
    public function getMember()
    {
        $member = Member::where(['uid' => $this->member['id'], 'shop_id' => $this->shop['id']])->firstOrFail();
        return $member;
    }

    public function getShop()
    {
        $shop = Shop::where('hashid', $this->shop['id'])->firstOrFail();
        return $shop;
    }

    /**
     * 检测用户是否购买
     * @param $content_id
     * @param $content_type
     * @return int
     * $checkMemberCard=true
     */
    public function checkProductPayment($content_type,$content_id,$checkMemberCard=false){
        $shop_id = $this->shop['id'];
        $member_id = $this->member['id'];
        $paied = Payment::checkProductPayment($shop_id,$member_id,$content_type,$content_id);
        
        if(!$paied && $checkMemberCard){
            $hasFreeMemberCard = Member::hasFreeMemberCard($member_id,$shop_id);
            $paied = $hasFreeMemberCard;
        }
        return $paied;
    }
    
    /**
     * 检查是否有免费订阅的权限
     *
     * @param [type] $member
     * @param [type] $price
     * @return void
     */
    public function checkFreeSubscribePermission($member, $price, $joinMembercard=true){
        if($price == 0){
            return ['perm'=>true,'payment_type'=>4,'expire_time'=>0];
        }

        if(!$joinMembercard){
            return ['perm'=>false];
        }
        $memberCards = $member->myMemberCards();
        
        if(count($memberCards)==0){
            return ['perm'=>false];
        }

        $memberCards = new Collection($memberCards);

        $freeMemberCards = $memberCards->filter(function($value,$key){
           return $value['discount'] == '-1'; 
        });

        if($freeMemberCards->count()==0){
            return ['perm'=>false];
        }

        $sortedFreeMemberCards = $freeMemberCards->sortByDesc('end_time');
        $primaryCard = $sortedFreeMemberCards->first();

        return ['perm'=>true,'payment_type'=>5,'expire_time'=>$primaryCard['end_time']];
    }

}
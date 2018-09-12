<?php
/**
 * Created by PhpStorm.
 * User: an
 * Date: 2018/2/1
 * Time: 下午3:12
 */
namespace App\Http\Controllers\H5\LimitPurchase;

use App\Http\Controllers\H5\BaseController;
use App\Models\CardRecord;
use App\Models\Column;
use App\Models\Content;
use App\Models\Course;
use App\Models\LimitPurchase;

class LimitPurchaseController extends BaseController{


    public function lists(){
        $lists = LimitPurchase::where(['shop_id'=>$this->shop['id'],'switch'=>1])
            ->where('end_time','>',time())
            ->orderBy('order_id')->orderByDesc('top')->orderByDesc('updated_at')->paginate(request('count')?:10);
        $limit_purchase = $this->listToPage($lists);
        if($limit_purchase && $limit_purchase['data']){
            foreach ($limit_purchase['data'] as $item){
                if($item->start_time < time() && $item->end_time > time()){
                    $item->status = 1;
                }elseif($item->start_time > time()){
                    $item->status = 0;
                }
                $item->indexpic = $item->indexpic?hg_unserialize_image_link($item->indexpic):[];
                $item->start_time = $item->start_time?hg_format_date($item->start_time):0;
                $item->end_time = $item->end_time?hg_format_date($item->end_time):0;
            }
        }
        return $this->output($limit_purchase);
    }

    public function contents() {
        $lpLists = LimitPurchase::select('hashid','shop_id','title','indexpic','start_time','end_time','discount','range','condition','contents')
            ->where(['shop_id' => $this->shop['id'], 'switch' => 1])
            ->where('end_time', '>', time())
            ->orderBy('order_id')->orderByDesc('top')->orderByDesc('updated_at')
            ->get();
        $content = ['type'=> [], 'hashid'=>[], 'map'=>[]];
        foreach ($lpLists as $lp) {
            $_ = unserialize($lp->contents)[0];
            $_ = array_filter($_); // 过滤掉空的数组
            foreach ($_ as $key => $item) {
                $content['type'][] = $key;
                foreach ($item as $value) {
                     $content['hashid'][] = $value;
                     $content['map'][$value] = $lp;
                }
            }
        }
        $content['type'] = array_unique($content['type']);
        $lpContentsLen = count($content['hashid']); // 所有限时购的内容数量
        $temp = intval(request('page'));
        $requestPage = $temp ? $temp : 1;
        $temp = intval(request('count'));
        $requestCount =  $temp ? $temp : 10;
        $totalPage = ceil($lpContentsLen / $requestCount);
        $result = [
            'page' => [
            'total' => $lpContentsLen,
            'current_page' => $requestPage,
            'last_page' => null,
            ],
            'data' => [],
        ];

        if ($requestPage > $totalPage) {
            
        } else {
            $start = ($requestPage - 1) * $requestCount;
            $lpContents_ = $this->getContentsByhasId(array_slice($content['hashid'], $start, $requestCount));
            $lpContents = [];
            foreach ($lpContents_ as $value) {
                $lpContents[] = $value;
            }
            $lambda = function($a, $b) use ($content) {
                $indexA = array_search($a->hashid, $content['hashid']);
                $indexB = array_search($b->hashid, $content['hashid']);
                if ($indexA > $indexB) {
                    return 1;
                } elseif ($indexA < $indexB) {
                    return -1;
                } else {
                    return 0;
                }
            };
            usort($lpContents, $lambda); // 按$content['hashid']index排序
            $memberId =  $this->member['id'];
            $mcDiscount = $this->getMemberCardDiscount($memberId); // 会员卡最高折扣
            $serializeredLp = [];
            foreach ($lpContents as $i) {
                $m = $this->contentSerializer($i, $content['map'][$i->hashid]->discount, $mcDiscount);
                if (!in_array($content['map'][$i->hashid]->hashid, $serializeredLp)) {
                    LimitPurchase::serializer($content['map'][$i->hashid]);
                    $serializeredLp[] = $content['map'][$i->hashid]->hashid;
                }
                $m->limit_purshase = $content['map'][$i->hashid];
                $result['data'][] = $m;
            }
            $result['page']['last_page'] = $totalPage;


        }
        return $this->output($result);
    }

    public function detail($id){
        $limit_purchase = $this->getLimitPurchase($id);
        $limit_purchase->contents = $this->getLimitPurchaseContents($limit_purchase);
        $limit_purchase->indexpic = $limit_purchase->indexpic?hg_unserialize_image_link($limit_purchase->indexpic):[];
        if($limit_purchase->start_time < time() && $limit_purchase->end_time > time()){
            $limit_purchase->status = 1;
        }elseif($limit_purchase->start_time > time()){
            $limit_purchase->status = 0;
        }elseif($limit_purchase->end_time < time()){
            $limit_purchase->status = 2;
        }
        $limit_purchase->start_time = $limit_purchase->start_time?hg_format_date($limit_purchase->start_time):0;
        $limit_purchase->end_time = $limit_purchase->end_time?hg_format_date($limit_purchase->end_time):0;
        return $this->output($limit_purchase);
    }

    private function getLimitPurchase($id){
        return LimitPurchase::where(['hashid'=>$id,'shop_id'=>$this->shop['id']])->firstOrFail();
    }

    private function getContentsByhasId($hashid) {
        $data = Content::where(['shop_id' => $this->shop['id']])->whereIn('hashid', $hashid)->select('type', 'indexpic', 'title', 'hashid', 'price', 'join_membercard')
                ->orderByDesc('create_time')
                ->get();
        return $data;
    }

    private function getLimitPurchaseContents($purchase)
    {
        $type = $ids = $content = [];
        if ($purchase->range == 2) {
            foreach (unserialize($purchase->contents)[0] as $key => $item) {
                foreach ($item as $value) {
                    array_push($content, ['type' => $key, 'hashid' => $value]);
                }
            }
            foreach ($content as $v) {
                $type[] = $v['type']; $ids[] = $v['hashid'];
            }
            $data = Content::where(['shop_id' => $this->shop['id']])->whereIn('type', $type)->whereIn('hashid', $ids)->select('type', 'indexpic', 'title', 'hashid', 'price')->paginate(request('count') ?: 10);
            $this->processContentPrice($data->items(), $purchase->discount);
        }
        return $this->listToPage($data);
    }


    //价格处理
    private function processContentPrice($contents,$pur_discount){
        $response = [];
        if($contents){
            foreach ($contents as $item){
                $limit_price = number_format($item->price*($pur_discount/10),2);
                $limit_price = str_replace(',', '', $limit_price);
                $item->limit_price = $limit_price<0?'0.00':$limit_price;
                $record = CardRecord::where(['member_id' => $this->member['id'], 'shop_id' => $this->shop['id']])->where('end_time', '>', time())->get()->toArray(); //获取该会员订购的所有会员卡（在有效期内的）
                $record && array_multisort(array_column($record, 'discount'), SORT_ASC, $record); //根据折扣高低排序数组
                $discount = (($record ? $record[0]['discount'] : 10) > $pur_discount) ? $pur_discount : ($record ? $record[0]['discount'] : 10);
                $item->cost_price = $item->price;
                $price = number_format($item->price * (($discount<0?0:$discount) / 10), 2);  //折扣后的价格
                $price = str_replace(',', '', $price);
                $item->price = $price<0?'0.00':$price;
                $item->content_id = $item->hashid;
                $item->indexpic = $item->indexpic?hg_unserialize_image_link($item->indexpic):[];
                $item->type=='course' && $item->course_type = Course::where(['shop_id' => $this->shop['id'],'hashid'=>$item->hashid])->value('course_type')?:'';
                $response[] = $item;
            }
        }
        return $response;
    }

    private function getMemberCardReocrd($memberId) {
        $record = CardRecord::where(['member_id' => $memberId, 'shop_id' => $this->shop['id']])->where('end_time', '>', time())->get()->toArray(); //获取该会员订购的所有会员卡（在有效期内的）
        return $record;
    }

    private function sortMemberCardRecord($record) {
        array_multisort(array_column($record, 'discount'), SORT_ASC, $record); //根据折扣高低排序数组
    }

    private function getHighPriorityMemberCard($memberId) {
        $m = $this->getMemberCardReocrd($memberId);
        if ($m) {
            $this->sortMemberCardRecord($m);
            return $m[0];
        } else {
            return null;
        }
    }

    private function getMemberCardDiscount($memberId) {
        $mc = $this->getHighPriorityMemberCard($memberId);
        $mcDiscount = $mc ? $mc['discount'] : 10;
        return $mcDiscount;
    }

    private function contentSerializer($content,$pur_discount, $mcDiscount) {
        $limit_price = number_format($content->price * ($pur_discount / 10), 2);
        $limit_price = str_replace(',', '', $limit_price);
        $content->limit_price = $limit_price < 0 ? '0.00' : $limit_price;
        $discount = ($mcDiscount > $pur_discount) ? $pur_discount : $mcDiscount;
        $content->cost_price = $content->price;
        $price = number_format($content->price * (($discount < 0 ? 0 : $discount) / 10), 2); //折扣后的价格
        $price = str_replace(',', '', $price);
        $content->price = $price < 0 ? '0.00' : $price;
        $content->content_id = $content->hashid;
        $content->indexpic = $content->indexpic ? hg_unserialize_image_link($content->indexpic) : [];
        $content->type == 'course' && $content->course_type = Course::where(['shop_id' => $this->shop['id'], 'hashid' => $content->hashid])->value('course_type') ?: '';
        return $content;
    }

}
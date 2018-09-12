<?php
namespace App\Http\Controllers\H5\Content;

use DateTime;
use DateTimeZone;
use App\Http\Controllers\H5\BaseController;
use App\Models\Banner;
use App\Models\Column;
use App\Models\Community;
use App\Models\Content;
use App\Models\ContentType;
use App\Models\Course;
use App\Models\DecorateComponent;
use App\Models\MarketingActivity;
use App\Models\MemberCard;
use App\Models\CardRecord;
use App\Models\Navigation;
use App\Models\Shop;
use App\Models\Type;
use App\Models\LimitPurchase;
use App\Models\FightGroupActivity;
use App\Models\OfflineCourse;
use App\Models\DjangoContentType;


class ComponentContentsController extends BaseController
{
    /**
     * 组件内容接口
     */

    public function ComponentContentsList()
    {
        $shopId = $this->shop['id'];
        $shopInstance = $this->getShop($shopId);
        $comIdToIns = [];
        $componentIds = request('component_ids', '');
        $componentIds = explode(',', $componentIds);
        $components = $this->getComponents($componentIds, $shopInstance);
        // dd($components);
        $data = [];
        foreach ($components as $component) {
            $componentContents = $this->getComponentContents($component, $shopInstance);
            $data[$component->id] = $componentContents;
        }
        return $this->output($data);
    }
    protected function getComponents($componentIds, $shopInstance)
    {
        $components = DecorateComponent::whereIn('id', $componentIds)->take(20)->get();
        return $components;
    }

    protected function getComponentContents($component, $shopInstance)
    {
        //小程序是否审核中
        $applet_audit_status = $this->checkAppletAuditStatus();
        $common_filters = $this->contentCommonFilters();
        $content_type_filters = $this->contentTypeFilters();
        $content_filters = [];
        if (is_array($common_filters) && is_array($content_type_filters)){
            $content_filters = array_merge($common_filters, $content_type_filters);
        }
        $component->settings = json_decode($component->settings);
        $limit = property_exists($component->settings, 'limits') ? $component->settings->limits : 20;
        $contents = [];
        $onlyFreeContent = onlyFreeContent();
        // 小程序审核中 这几个组件返回空数据
        if ($applet_audit_status && in_array($component->type, ['slider', 'navigation', 'audio', 'video', 'category',
                'live_bigpic', 'live_list', 'column', 'course', 'pintuan', 'limited_discount', 'community', 'offline_course'])) {
            return $contents;
        }
        switch ($component->type) {
            case 'slider':
                $filters = [
                    'shop_id' => $shopInstance->hashid,
                    'state' => 1,
                    'type' => 'home',
                ];
                $contents = $this->getSliders($filters, $limit);
                $contents = $this->sliderSerializer($contents);
                break;
            case 'navigation':
                $filters = [
                    'shop_id' => $shopInstance->hashid,
                    'status' => 1,
                ];
                $contents = $this->getNavigations($filters, $limit);
                $contents = $this->navigationSerializer($contents);
                break;
            case 'article':
                $filters = [
                    'shop_id' => $shopInstance->hashid,
                    'display' => 1,
                ];
                $types = ['article'];
                if ($onlyFreeContent) {
                   $filters['payment_type'] = 3; 
                }
                $contents = $this->getContents($types, $filters, $limit, $content_filters);
                $contents = $this->contentSerializer($contents);
                break;
            case 'audio':
                $filters = [
                    'shop_id' => $shopInstance->hashid,
                    'display' => 1,
                ];
                $types = ['audio'];
                if ($onlyFreeContent) {
                   $filters['payment_type'] = 3; 
                }
                $contents = $this->getContents($types, $filters, $limit, $common_filters);
                $contents = $this->contentSerializer($contents);
                break;
            case 'video':
                $filters = [
                    'shop_id' => $shopInstance->hashid,
                    'display' => 1,
                ];
                $types = ['video'];
                if ($onlyFreeContent) {
                   $filters['payment_type'] = 3; 
                }
                $contents = $this->getContents($types, $filters, $limit, $common_filters);
                $contents = $this->contentSerializer($contents);
                break;
            case 'live_bigpic': // 直播大图
                //all: 全部的直播, started: 直播中的直播, ready: 未开始的直播, ended: 已结束的直播
                $status = property_exists($component->settings, 'live_status') ? $component->settings->live_status : '';
                $filters = [
                    'content.type' => 'live',
                    'content.display' => 1,
                    'content.shop_id' => $shopInstance->hashid,
                ];
                if ($onlyFreeContent) {
                   $filters['content.payment_type'] = 3; 
                }
                $contents = $this->getLives($status, $filters, $limit, $common_filters);
                $contents = $this->contentSerializer($contents);
                break;
            case 'live_list'; // 直播列表
                $status = property_exists($component->settings, 'live_status') ? $component->settings->live_status : '';
                $filters = [
                    'content.type' => 'live',
                    'content.display' => 1,
                    'content.shop_id' => $shopInstance->hashid,
                ];
                if ($onlyFreeContent) {
                   $filters['content.payment_type'] = 3; 
                }
                $contents = $this->getLives($status, $filters, $limit, $common_filters);
                $contents = $this->contentSerializer($contents);
                break;
            case 'column': // 专栏
                $filters = [
                    'state' => 1,
                    'display' => 1,
                    'shop_id' => $shopInstance->hashid,
                ];
                if ($onlyFreeContent) {
                    $filters['charge'] = 0;
                    $filters['price'] = 0.00;
                }
                $contents = $this->getColumns($filters, $limit, $common_filters);
                $contents = $this->columnSerializer($contents);
                break;
            case 'course': // 课程
                $filters = [
                    'shop_id' => $shopInstance->hashid,
                    'state' => 1,
                    'is_lock' => 0,
                ];
                if ($onlyFreeContent) {
                    $filters['pay_type'] = 0; # 是否收费   0-免费  1-收费
                }
                $contents = $this->getCourses($filters, $limit, $common_filters);
                $contents = $this->courseSerializer($contents);
                break;
            case 'category': // 内容分类
                $filters = [
                    'shop_id' => $shopInstance->hashid,
                ];
                $contents = $this->getCategory($filters, $limit);
                $contents = $this->categorySerializer($contents);
                break;

            case 'latest_content': // 最新内容
                $filters = [
                    'shop_id' => $shopInstance->hashid,
                    'display' => 1,
                ];
                $types = ['article', 'video', 'audio'];
                if ($onlyFreeContent) {
                    $filters['payment_type'] = 3;
                }
                $contents = $this->getContents($types, $filters, $limit, $content_filters,false);
                $contents = $this->contentSerializer($contents);
                break;
            case 'member_card': // 会员卡
                $filters = [
                    'shop_id' => $shopInstance->hashid,
                    'status' => 1,
                    'is_del' => 0
                ];
                $contents = $this->getMemberCards($filters, $limit, $common_filters);
                break;
            case 'pintuan': // 拼团
                $filters = [
                  'shop_id' => $shopInstance->id,
                  'is_del' => 0,
                  'activation' => 1
                ];
                if ($onlyFreeContent) {
                    $contents = [];
                } else {
                    $contents = $this->getPinTuan($filters, $limit);
                }
                $contents = $this->pinTuanSerializer($contents);
                break;
            case 'limited_discount': // 限时购
                $filters = [
                  'shop_id' => $shopInstance->hashid,
                  'switch' => 1
                ];
                if($onlyFreeContent) {
                    $limitPurchases = [];
                } else {
                    $limitPurchases = $this->getLimitedPurchase($filters);
                }
                foreach ($limitPurchases as $item) {
                        $_ = $this->getLimitedPurchaseContents($item, $limit);
                        $lSerializer = LimitPurchase::serializer($item);
                        foreach ($_ as $i) {
                            $i->limit_purshase = $lSerializer;
                            $contents[] = $i;
                        }
                }
                break;
            case 'community': // 小社群
                $filters = [
                    'shop_id' => $shopInstance->hashid,
                    'display' => 1
                ];
                if ($onlyFreeContent) {
                    $filters['pay_type'] = 0;
                }
                $contents = $this->getCommunity($filters, $limit);
                $contents = $this->communitySerializer($contents);
                break;
            case 'offline_course': // 线下课程
                $filters = [
                    'shop_id' => $shopInstance->id,
                    'is_del' => 0,
                    'status' => 2 // 已上架
                ];
            if ($onlyFreeContent) {
                $filters['highest_price'] = 0;
                }
                $contents = $this->getOfflineCourse($filters, $limit);
                $contents = $this->OfflineCourseSerializer($contents);
                break;
            default:
                break;

        }
        return $contents;
    }

    protected function getSliders($filters, $limit = 20)
    { // 轮播图
        $banners = Banner::where($filters)->orderBy('order_id')
            ->orderByDesc('top')
            ->orderByDesc('update_time')
            ->select('id', 'shop_id', 'title', 'indexpic', 'link', 'top')
            ->take($limit)
            ->get();
        return $banners;
    }
    protected function getNavigations($filters, $limit)
    { // 导航
        $navigations = Navigation::where($filters)
            ->orderBy('order_id', 'asc')
            ->take($limit)
            ->get();
        return $navigations;
    }

    protected function getContents($types, $filters, $limit, $common_filters = [], $orderByOrderId=true)
    { // 图文 音频 视频
        $not_select_pay_type = [1, 4]; # 1-专栏，2-单卖，3-免费
        $sql = Content::whereIn('type', $types);
        $this->filterSql($sql, $common_filters);
        $contents = $sql->where($filters)
            ->where(function ($query) {
                $query->where('state', 1)->orWhere('state', 0);
            })
            ->where('up_time', '<', time())
            ->whereNotIn('payment_type', $not_select_pay_type)
            ->when($orderByOrderId,function($query){
                $query->orderBy('order_id','asc');
            })
            ->orderBy('up_time','desc')
            ->orderBy('update_time','desc')
            ->orderBy('create_time','desc')
            ->take($limit)
            ->get();
        return $contents;
    }
    protected function getArticles($filters, $limit)
    { // 图文
        return;
    }
    protected function getAudios($filters, $limit)
    { // 音频
        return;
    }
    protected function getVideos($filters, $limit)
    { // 视频
        return;
    }
    protected function getLives($status, $filters, $limit, $common_filters = [])
    { // 直播
        $now = time();
        $sql = Content::join('live', 'live.content_id', '=', 'content.hashid')
            ->where($filters)
            ->where(function ($query) {
                $query->where('content.state', 1)->orWhere('content.state', 0);
            })
            ->where('content.payment_type', '!=', 1)
            ->where('content.up_time', '<', $now);
        $this->filterSql($sql, $common_filters);
        switch ($status) {
            case 'all':
                break;
            case 'started':
                $sql->where('live.start_time','<', $now)
                    ->where('live.end_time', '>', $now);
                break;
            case 'ready':
                $sql->where('live.start_time','>', $now);
                break;
            case 'ended':
                $sql->where('live.end_time', '<', $now);
                break;
            default:
                break;
            }
        $lives = $sql
            ->orderBy('order_id')
            ->orderBy('up_time','desc')
            ->orderBy('update_time','desc')
            ->orderBy('create_time','desc')
            ->take($limit)
            ->get();
        return $lives;
    }
    protected function getColumns($filters, $limit, $common_filters = [])
    { // 专栏
        $sql = Column::where($filters);
        $this->filterSql($sql, $common_filters);
        $columns = $sql->orderBy('column.order_id')
            ->orderBy('column.top', 'desc')
            ->orderBy('column.update_time', 'desc')
            ->take($limit)
            ->get();
        return $columns;
    }
    protected function getCourses($filters, $limit, $common_filters = [])
    { // 课程
        $sql = Course::where($filters);
        $this->filterSql($sql, $common_filters);
        $courses = $sql->select('hashid', 'shop_id', 'title', 'indexpic', 'subscribe', 'price', 'is_finish', 'pay_type', 'course_type', 'describe', 'brief', 'join_membercard')
            ->orderBy('order_id')
            ->orderBy('top', 'desc')
            ->orderBy('create_time', 'desc')
            ->take($limit)
            ->get();
        return $courses;
    }
    protected function getCategory($filters, $limit)
    { // 分类
        $categories = Type::select('id', 'title', 'create_time', 'indexpic', 'brief')
            ->where($filters)
            ->orderBy('order_id')
            ->orderBy('create_time', 'desc')
            ->take($limit)
            ->get();
        return $categories;
    }
    protected function getLatestContent($filters, $limit)
    { // 最新内容
        return;
    }
    protected function getMemberCards($filters, $limit, $common_filters = [])
    { // 会员卡
        $sql = MemberCard::where($filters);
        $this->filterSql($sql, $common_filters);
        $cards = $sql->orderBy('top', 'desc')
            ->orderBy('updated_at', 'desc')
            ->take($limit)
            ->get();
        return $cards;
    }
    protected function getPinTuan($filters, $limit)
    { // 拼团
      $now = date_create(null, timezone_open('UTC'));
      $now_str = $now->format('Y-m-d H:i:s');
      $list = FightGroupActivity::select('id', 'create_time', 'update_time', 'name', 'start_time', 'end_time', 'people_number', 'origin_price',
       'now_price','chip_join','redundancy_product','product_category','product_identifier')
      ->where($filters)
      ->where('start_time', '<=', $now_str)
      ->where('end_time', '>', $now_str)
      ->orderBy('ordering', 'desc')
      ->orderBy('create_time', 'desc')
      ->take($limit)
      ->get();
      return $list;
    }

    protected function getLimitedPurchase($filters)
    { // 限时购
      $list = LimitPurchase::select('hashid', 'title','start_time', 'end_time','discount','condition', 'id', 'contents')
        ->where($filters)
        ->where('end_time', '>', time())
        ->orderBy('order_id')->orderByDesc('top')->orderByDesc('updated_at')
        ->get();
        return $list;
    }

    private function getLimitedPurchaseContents($purchase, $limit)
    {
        $type = $ids = $content = [];
        foreach (unserialize($purchase->contents)[0] as $key => $item) {
                foreach ($item as $value) {
                    array_push($content, ['type' => $key, 'hashid' => $value]);
                }
            }
        foreach ($content as $v) {
            $type[] = $v['type'];
            $ids[] = $v['hashid'];
        }
        $data = Content::where(['shop_id' => $this->shop['id']])
                ->whereIn('type', $type)->whereIn('hashid', $ids)
                ->select('type', 'indexpic', 'title', 'hashid', 'price', 'join_membercard')
                ->take($limit)
                ->get();
        // dd($data);
        $this->processContentPrice($data, $purchase->discount);
        return $data;
    }

    //限时购价格处理 copy from LimitPurchaseController@processContentPrice
    private function processContentPrice($contents,$pur_discount){
        $response = [];
        $shopHighestMembercard = $this->shopHighestDiscountMembercard();
        $this->member['id'] = '8cf1879f79e2033cb5391f27ed31fdcb';
        $record = CardRecord::where(['member_id' => $this->member['id'], 'shop_id' => $this->shop['id']])->where('end_time', '>', time())->get()->toArray(); //获取该会员订购的所有会员卡（在有效期内的）
        $record && array_multisort(array_column($record, 'discount'), SORT_ASC, $record); //根据折扣高低排序数组
        if($contents){
            foreach ($contents as $item){
                $limit_price = number_format($item->price*($pur_discount/10),2);
                $limit_price = str_replace(',', '', $limit_price);
                $item->limit_price = $limit_price<0?'0.00':$limit_price;
                $discount = (($record ? $record[0]['discount'] : 10) > $pur_discount) ? $pur_discount : ($record ? $record[0]['discount'] : 10);
                $item->cost_price = $item->price;
                $price = number_format($item->price * (($discount<0?0:$discount) / 10), 2);  //折扣后的价格
                $price = str_replace(',', '', $price);
                $item->price = $price<0?'0.00':$price;
                $item->content_id = $item->hashid;
                $item->indexpic = $item->indexpic?hg_unserialize_image_link($item->indexpic):[];
                $item->type=='course' && $item->course_type = Course::where(['shop_id' => $this->shop['id'],'hashid'=>$item->hashid])->value('course_type')?:'';
                $item->membercard_discount = $this->shopHighestDiscount($shopHighestMembercard, $item->join_membercard);
                $response[] = $item;
            }
        }
        return $response;
    }

    protected function getCommunity($filters, $limit)
    { // 小社群
        $communities = Community::select('hashid as community_id', 'shop_id', 'title', 'brief', 'indexpic', 'pay_type', 'price', 'member_num', 'created_at','join_membercard')
            ->where($filters)
            ->take($limit)
            ->get();
        return $communities;
    }

    protected function getOfflineCourse($filters, $limit)
    { // 线下课程
        // $dj_ct_id = DjangoContentType::getOfflineCourseContentType();
        $ofs = OfflineCourse::select('id', 'name', 'brief', 'cover_image', 'start_time', 'end_time', 'registration_deadline', 'course_place','lowest_price', 'highest_price')
            ->where($filters)
            ->orderBy('ordering', 'asc')
            ->take($limit)
            ->get();
        return $ofs;
    }

    protected function OfflineCourseSerializer($contents)
    { // 线下课程
        
        return $contents;
    }

    protected function _getShop($hashId)
    { // 店铺
        $shop = Shop::where('hashid', $hashId)->firstOrFail();
        return $shop;
    }

    protected function contentSerializer($contents)
    { // 序列化内容（图文，音频，视频，直播）
        $shopHighestMembercard = $this->shopHighestDiscountMembercard();
        foreach ($contents as $k => $v) {
            if ($v->type == 'live') {
                $start_time = $v->start_time ?: $v->alive->start_time;
                $end_time = $v->end_time ?: $v->alive->end_time;
                $start_time > time() && $v->live_state = 0;
                $start_time < time() && $end_time > time() && $v->live_state = 1;
                $end_time < time() && $v->live_state = 2;
                $v->live_indexpic = hg_unserialize_image_link($v->live_indexpic);
                $v->live_person = json_decode($v->live_person, true);
            }
            if ($v->column_id != 0) {
                $column = $v->column;
                $v->column_title = $column->title;
                $v->column_price = $column->price;
                $v->column_charge = intval($column->charge) ? 1 : 0;
                $v->column_finish = intval($column->finish);
                $v->column_indexpic = hg_unserialize_image_link($column->indexpic);
                $v->column_subscribe = intval($column->subscribe);
                $v->column_stage = count($column->content);
            }
            $v->membercard_discount = $this->shopHighestDiscount($shopHighestMembercard, $v->join_membercard);
            $v->content_id = $v->hashid;
            $v->create_time = $v->create_time ? date('m-d', $v->create_time) : 0;
            $v->update_time = $v->update_time ? date('m-d', $v->update_time) : 0;
            $v->up_time = $v->up_time ? date('Y-m-d H:i:s', $v->up_time) : 0;
            $v->start_time && $v->start_time = date('Y-m-d H:i:s', $v->start_time);
            $v->end_time && $v->end_time = date('Y-m-d H:i:s', $v->end_time);
            $v->brief && $v->brief = htmlspecialchars_decode(str_ireplace('&nbsp;', '', mb_substr(strip_tags($v->brief), 0, 100)));
            $v->column_id = $v->column_id ? intval($v->column_id) : 0;
            $v->indexpic = hg_unserialize_image_link($v->indexpic);
            $v->view_count = $this->formatMultiple('view', $v->view_count);
            $v->is_test = intval($v->is_test) ? 1 : 0;
            $v->market_sign = MarketingActivity::where(['shop_id' => $this->shop['id'], 'content_id' => $v->hashid, 'content_type' => $v->type])->value('marketing_type') ?: '';
        }
        return $contents;
    }

    protected function courseSerializer($contents)
    { // 序列化课程
        $shopHighestMembercard = $this->shopHighestDiscountMembercard();
        foreach ($contents as $item) {
            $item->content_id = $item->hashid;
            $item->hour_count = $item->class_hour ? $item->class_hour->count() : 0;
            $item->indexpic = $item->indexpic ? hg_unserialize_image_link($item->indexpic) : '';
            $item->subscribe = hg_caculate_multiple($item->subscribe, 'subscribe', $this->shop['id']);
            $item->type = 'course';
            $item->market_sign = MarketingActivity::where(['shop_id' => $this->shop['id'], 'content_id' => $item->hashid, 'content_type' => 'course'])->value('marketing_type') ?: '';
            $item->membercard_discount = $this->shopHighestDiscount($shopHighestMembercard, $item->join_membercard);

        }
        return $contents;

    }

    protected function columnSerializer($contents)
    { // 序列化专栏
        $shopHighestMembercard = $this->shopHighestDiscountMembercard();
        foreach ($contents as $k => $v) {
            $v->column_id = $v->hashid;
            $v->create_time = $v->create_time ? date('Y-m-d', $v->create_time) : 0;
            $v->update_time = $v->update_time ? date('Y-m-d', $v->update_time) : 0;
            if (request('source') == 'wx_applet') {
                $v->stage = count($v->content->where('payment_type', 1));
            } else {
                $v->stage = count($v->content);
            }
            $v->indexpic = hg_unserialize_image_link($v->indexpic);
            $v->subscribe = $this->formatMultiple('subscribe', $v->subscribe);
            $v->market_sign = MarketingActivity::where(['shop_id' => $this->shop['id'], 'content_id' => $v->hashid, 'content_type' => 'column'])->value('marketing_type') ?: '';
            $v->membercard_discount = $this->shopHighestDiscount($shopHighestMembercard, $v->join_membercard);
            $v->makeHidden('describe');
            unset($v->content);
        }
        return $contents;

    }
    protected function memberCardSerializer($contents, $many)
    { // 序列化专栏
        foreach ($contents as $item) {
            $item->up_time = $item->up_time ? hg_format_date($item->up_time) : '';
            $item->expire = $item->expire ? intval($item->expire) : 0;
            $item->status = $item->status ? intval($item->status) : 0;
            $item->style = $item->style ? intval($item->style) : 1;
        }
        return $contents;

    }
    protected function pinTuanSerializer($contents)
    { // 序列化拼团
    $localTimezone = new DateTimeZone('PRC');
    foreach ($contents as $item) {
        $item->redundancy_product = json_decode($item->redundancy_product);
        unset($item->redundancy_product->describe);
        unset($item->create_time);
        unset($item->update_time);
        $item->start_time = new DateTime($item->start_time);
        $item->start_time->setTimeZone($localTimezone);
        $item->start_time = $item->start_time->format('Y-m-d H:i:s');
        $item->end_time = new DateTime($item->end_time);
        $item->end_time->setTimeZone($localTimezone);
        $item->end_time = $item->end_time->format('Y-m-d H:i:s');
      }
      return $contents;
    }

    protected function communitySerializer($contents)
    { // 序列化小社群
        $shopHighestMembercard = $this->shopHighestDiscountMembercard();
        foreach ($contents as $item) {
            $item->indexpic = $item->indexpic ? hg_unserialize_image_link($item->indexpic) : '';
            $item->membercard_discount = $this->shopHighestDiscount($shopHighestMembercard, $item->join_membercard);
        }
        return $contents;

    }

    protected function sliderSerializer($contents)
    { // 序列化轮播图
        foreach ($contents as $item) {
            $link = $item->link ? unserialize($item->link) : [];
            if (isset($link['id'], $link['type'])) {
                $link['is_free'] = $this->bannerFormatFree($link);
                $link['type'] == 'course' && $link['course_type'] = Course::where('hashid', $link['id'])->value('course_type');
            }
            $item->link = $link;
            $item->makeVisible(['link']);
            $item->indexpic = hg_unserialize_image_link($item->indexpic);
        }
        return $contents;

    }
    protected function navigationSerializer($contents)
    { // 序列化导航
        foreach ($contents as $val) {
            $val->index_pic = $val->index_pic ? unserialize($val->index_pic) : '';
            $val->link = $val->link ? unserialize($val->link) : '';
            $val->makeHidden('status');
            $val->makeHidden('order_id');
            $val->makeHidden('shop_id');
            $val->makeHidden('create_time');
        }
        return $contents;

    }
    protected function categorySerializer($contents)
    { // 序列化导航
        foreach ($contents as $val) {
            $val->indexpic = $val->indexpic ? unserialize($val->indexpic) : '';
            $val->child = ContentType::where('type_id', $val->id)->distinct()->count('content_id');
        }

        return $contents;

    }

    private function bannerFormatFree($link)
    {
        if ($link['type'] == 'column') {
            $is_free = Column::where('hashid', $link['id'])->value('price') == '0.00' ? 1 : 0;
        } elseif ($link['type'] == 'course') {
            $is_free = Course::where('hashid', $link['id'])->value('pay_type') ? 0 : 1;
        } else {
            $is_free = Content::where('hashid', $link['id'])->value('price') == '0.00' ? 1 : 0;
        }
        return $is_free;
    }

    private function formatContent($contents)
    {
        foreach ($contents as $k => $v) {
            if ($v->type == 'live') {
                $start_time = $v->start_time ?: $v->alive->start_time;
                $end_time = $v->end_time ?: $v->alive->end_time;
                $start_time > time() && $v->live_state = 0;
                $start_time < time() && $end_time > time() && $v->live_state = 1;
                $end_time < time() && $v->live_state = 2;
                $v->live_indexpic = hg_unserialize_image_link($v->live_indexpic);
            }
            if ($v->column_id != 0) {
                $column = $v->column;
                $v->column_title = $column->title;
                $v->column_price = $column->price;
                $v->column_charge = intval($column->charge) ? 1 : 0;
                $v->column_finish = intval($column->finish);
                $v->column_indexpic = hg_unserialize_image_link($column->indexpic);
                $v->column_subscribe = intval($column->subscribe);
                $v->column_stage = count($column->content);
            }
            $v->content_id = $v->hashid;
            $v->create_time = $v->create_time ? date('m-d', $v->create_time) : 0;
            $v->update_time = $v->update_time ? date('m-d', $v->update_time) : 0;
            $v->up_time = $v->up_time ? date('Y-m-d H:i:s', $v->up_time) : 0;
            $v->start_time && $v->start_time = date('Y-m-d H:i:s', $v->start_time);
            $v->end_time && $v->end_time = date('Y-m-d H:i:s', $v->end_time);
            $v->brief && $v->brief = htmlspecialchars_decode(str_ireplace('&nbsp;', '', mb_substr(strip_tags($v->brief), 0, 100)));
            $v->column_id = $v->column_id ? intval($v->column_id) : 0;
            $v->indexpic = hg_unserialize_image_link($v->indexpic);
            $v->view_count = $this->formatMultiple('view', $v->view_count);
            $v->is_test = intval($v->is_test) ? 1 : 0;
            $v->market_sign = MarketingActivity::where(['shop_id' => $this->shop['id'], 'content_id' => $v->hashid, 'content_type' => $v->type])->value('marketing_type') ?: '';
        }
        return $contents;
    }
    private function formatMultiple($range, $count)
    {
        return hg_caculate_multiple($count, $range, $this->shop['id']);
    }

}

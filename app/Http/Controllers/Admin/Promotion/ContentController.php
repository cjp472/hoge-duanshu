<?php
/**
 * Created by PhpStorm.
 * User: Allen
 * Date: 17/12/28
 * Time: 下午3:49
 */
namespace App\Http\Controllers\Admin\Promotion;
use App\Http\Controllers\Admin\BaseController;
use App\Models\Column;
use App\Models\Content;
use App\Models\Course;
use App\Models\MarketingActivity;
use App\Models\Order;
use App\Models\PromotionContent;
use App\Models\PromotionRate;
use App\Models\PromotionShop;
use App\Models\PromotionRecord;
use App\Models\MemberCard;
use Illuminate\Support\Facades\DB;

class ContentController extends BaseController
{

    const ORDER_BY_TYPE = ['create_time', 'commission', '-create_time', '-commission', 'price', '-price'];

    /**
     * 根据类型获取店铺下内容
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function allContents()
    {
        $this->validateWith([
            'count'  => 'numeric',
            'type'   => 'required|alpha_dash|in:column,course,article,audio,video,live,member_card'
        ]);
        $type = request('type');
        $count = request('count') ? : 10;
        $promotion = PromotionContent::where(['shop_id'=>$this->shop['id'],'content_type'=>$type])->pluck('content_id')->toArray();
        if ($type == 'column') {
            $market_ids = hg_check_marketing($this->shop['id'],$type);
            $data = Column::select('hashid as content_id','title','price')
                ->where('shop_id',$this->shop['id'])
                ->whereNotIn('hashid',$promotion)
                ->whereNotIn('hashid',$market_ids)
                ->where('charge',1)
                ->where('state',1)
                ->orderBy('top','desc')
                ->orderBy('update_time','desc')
                ->paginate($count);
        } elseif ($type == 'course') {
            $market_ids = hg_check_marketing($this->shop['id'],$type);
            $data = Course::select('hashid as content_id','title','price')
                ->where('shop_id',$this->shop['id'])
                ->whereNotIn('hashid',$promotion)
                ->whereNotIn('hashid',$market_ids)
                ->where('pay_type',1)
                ->where('state',1)
                ->orderBy('top','desc')
                ->orderBy('update_time','desc')
                ->orderBy('create_time','desc')
                ->paginate($count);
        } elseif ($type == 'member_card') {
            $market_ids = hg_check_marketing($this->shop['id'], $type);
            $data = MemberCard::select('hashid as content_id','title','price')
                ->where(['shop_id'=>$this->shop['id'],'status'=>1, 'is_del'=>0])
                ->whereNotIn('hashid',$promotion)
                ->whereNotIn('hashid',$market_ids)
                ->where('price','>', 0)
                ->orderBy('order_id')
                ->orderBy('created_at')
                ->paginate($count);
        } else {
            $market_ids = hg_check_marketing($this->shop['id'],$type);
            $data = Content::select('hashid as content_id','title','price')
                ->where(['shop_id'=>$this->shop['id'],'type'=>$type])
                ->whereNotIn('hashid',$promotion)
                ->whereNotIn('hashid',$market_ids)
                ->whereIn('payment_type',[2,4])
                ->where('up_time', '<', time())
                ->where(function ($query) {
                    $query->where('state', 1)->orWhere('state', 0);
                })
                ->orderBy('top','desc')
                ->orderBy('update_time','desc')
                ->orderBy('up_time','desc')
                ->paginate($count);
        }
        if ($data->items()) {
            foreach ($data->items() as $item) {
                $item->type= $type;
            }
        }
        return $this->output($this->listToPage($data));
    }

    /**
     * 设置推广内容(商品)
     *
     * @return \Illuminate\Http\JsonResponse\
     */
    public function setContent()
    {
        $this->validateWith([
            'content'     => 'array'
        ]);
        $content = request('content');
        $data = [];
        if ($content) {      //指定内容导入
            $columnContent = Column::where('shop_id',$this->shop['id'])->pluck('title','hashid')->toArray(); //专栏title
            $courseContent = Course::where('shop_id',$this->shop['id'])->pluck('title','hashid')->toArray();//课程title
            $otherContent = Content::where('shop_id',$this->shop['id'])->pluck('title','hashid')->toArray();//其他四种title
            $otherContent = Content::where('shop_id', $this->shop['id'])->pluck('title', 'hashid')->toArray(); //其他四种title
            $mcContent = MemberCard::where('shop_id', $this->shop['id'])->pluck('title', 'hashid')->toArray(); // 会员卡
            $column = $course = $article = $audio = $video = $live = $member_card = [];
            foreach ($content[0] as $k => $v) {
                switch ($k)
                {
                    case 'column':
                        $column = $this->contentData('column',$v,$columnContent);
                        break;
                    case 'course':
                        $course = $this->contentData('course',$v,$courseContent);
                        break;
                    case 'member_card':
                        $member_card = $this->contentData('member_card',$v,$mcContent);
                        break;
                    case 'article':
                        $article = $this->contentData('article',$v,$otherContent);
                        break;
                    case 'audio':
                        $audio = $this->contentData('audio',$v,$otherContent);
                        break;
                    case 'video':
                        $video = $this->contentData('video',$v,$otherContent);
                        break;
                    case 'live':
                        $live = $this->contentData('live',$v,$otherContent);
                        break;
                }
            }
            $data = array_merge($column,$course,$article,$audio,$video,$live, $member_card);
        } else {  //所有导入
            $column = $course = $other = $memberCard = $promotionData = [];
            $promotion = PromotionContent::where('shop_id',$this->shop['id'])->pluck('content_type','content_id')->toArray();
            foreach ($promotion as $k=>$v) {
                $promotionData[] = $k.$v;
            }
            $columnContent = Column::select('hashid','title')->where('shop_id',$this->shop['id'])->where('price','>',0)->get(); //专栏
            if (!$columnContent->isEmpty()) {
                $column = $this->allContent($columnContent,$promotionData,'column');
            }
            $courseContent = Course::select('hashid','title')->where('shop_id',$this->shop['id'])->where('price','>',0)->get();//课程
            if (!$courseContent->isEmpty()) {
                $course = $this->allContent($courseContent,$promotionData,'course');
            }
            $memberCardContent = MemberCard::select('hashid', 'title')->where('shop_id', $this->shop['id'])->where('price', '>', 0)->where(['status'=>1,'is_del'=>0])->get(); //会员卡
            if (!$memberCardContent->isEmpty()) {
                $memberCard = $this->allContent($memberCardContent, $promotionData, 'member_card');
            }
            $otherContent = Content::select('hashid','title','type')->where('shop_id',$this->shop['id'])->where('price','>',0)->get();//其他四种
            if (!$otherContent->isEmpty()) {
                $other = $this->allContent($otherContent,$promotionData);
            }
            $data = array_merge($column,$course,$other);
        }
        PromotionContent::insert($data);
        return $this->output(['success'=>1]);
    }

    /**
     * 所有内容导入数据格式化
     *
     * @param $content
     * @param $shop
     * @param string $type
     * @return array
     */
    private function allContent($content,$promotion,$type = '')
    {
        $data = [];
        foreach ($content as $item) {
            $content_type = $type ? $type : $item->type;
            if (!in_array($item->hashid.$content_type,$promotion)) {
                $data[] = [
                    'shop_id'       => $this->shop['id'],
                    'content_id'    => $item->hashid,
                    'content_type'  => $content_type,
                    'content_title' => $item->title,
                    'money_percent' => -1,
                    'visit_percent' => -1
                ];
                $market = MarketingActivity::where(['shop_id'=>$this->shop['id'],'content_id'=>$item->hashid,'content_type'=>$content_type,'marketing_type'=>'promotion'])->first();
                if(!$market){
                    $market = new MarketingActivity();
                    $market->shop_id = $this->shop['id'];
                    $market->content_id = $item->hashid;
                    $market->content_type = $content_type;
                    $market->marketing_type = 'promotion';
                    $market->save();
                }
            }
        }
        return $data;
    }

    /**
     * 内容数据格式化
     *
     * @param $type
     * @param $content
     * @param $shop
     * @return array
     */
    private function contentData($type,$content,$all)
    {
        $data = [];
        if ($content) {
            foreach ($content as $item) {
                $data[] = [
                    'shop_id'       => $this->shop['id'],
                    'content_id'    => $item,
                    'content_type'  => $type,
                    'content_title' => array_key_exists($item,$all) ? $all[$item] : '',
                    'money_percent' => -1,
                    'visit_percent' => -1
                ];
                $market = MarketingActivity::where(['shop_id'=>$this->shop['id'],'content_id'=>$item,'content_type'=>$type,'marketing_type'=>'promotion'])->first();
                if(!$market){
                    $market = new MarketingActivity();
                    $market->shop_id = $this->shop['id'];
                    $market->content_id = $item;
                    $market->content_type = $type;
                    $market->marketing_type = 'promotion';
                    $market->save();
                }
            }
        }
        return $data;
    }

    /**
     * 移除内容
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteContent()
    {
        $this->validateWith([
            'id'     => 'required|regex:/^[1-9]+(,[1-9]+)*/'
        ]);
        $id = explode(',',request('id'));
        if ($id && is_array($id)) {
            $content_ids = PromotionContent::where('shop_id',$this->shop['id'])->whereIn('id',$id)->pluck('content_id')->toArray();
            MarketingActivity::where(['shop_id'=>$this->shop['id'],'marketing_type'=>'promotion'])->whereIn('content_id',$content_ids)->delete();
            PromotionContent::where('shop_id',$this->shop['id'])->whereIn('id',$id)->delete();
        }
        return $this->output(['success'=>1]);
    }


    /**
     * 推广商品设置
     * is_participate 配置是否参与推广
     * @return \Illuminate\Http\JsonResponse
     */
    public function setting()
    {

        $this->validateWith([
            'content_ids' => 'required',
            'type' => 'required|alpha_dash|in:column,course,article,audio,video,live,member_card',
            'is_default_rate' => 'required|in:0,1',
            'promoter_rate' => 'numeric|min:0|max:80',
            'invite_rate' => 'numeric|min:0|max:80',
            'is_participate_promotion' => 'required|numeric|in:0,1',
        ]);

        $content_ids = request('content_ids');
        $type = request('type');
        $is_default_rate = request('is_default_rate');
        $promoter_rate = request('promoter_rate');
        $invite_rate = request('invite_rate');
        $is_participate_promotion = request('is_participate_promotion');

        if ($promoter_rate + $invite_rate > 100) {
            $this->error('max-percent-error');
        }
        $shop_id = $this->shop['id'];

//        switch ($type) {
//            case 'column':
//                Column::where(['shop_id' => $shop_id])
//                    ->whereIn('hashid', $content_ids)->update(['promoter_rate' => $promoter_rate,
//                        'invite_rate' => $invite_rate, 'is_participate_promotion' => $is_participate_promotion]);
//                break;
//            case 'course':
//                Course::where(['shop_id' => $shop_id])
//                    ->whereIn('hashid', $content_ids)->update(['promoter_rate' => $promoter_rate,
//                        'invite_rate' => $invite_rate, 'is_participate_promotion' => $is_participate_promotion]);
//                break;
//            case 'member_card':
//                MemberCard::where(['shop_id' => $shop_id])
//                    ->whereIn('hashid', $content_ids)->update(['promoter_rate' => $promoter_rate,
//                        'invite_rate' => $invite_rate, 'is_participate_promotion' => $is_participate_promotion]);
//                break;
//            default:
//                Content::where(['shop_id' => $shop_id, 'type' => $type])
//                    ->whereIn('hashid', $content_ids)->update(['promoter_rate' => $promoter_rate,
//                        'invite_rate' => $invite_rate, 'is_participate_promotion' => $is_participate_promotion]);
//                break;
//        }
        PromotionContent::where(['shop_id' => $shop_id, 'content_type' => $type])
            ->whereIn('content_id', $content_ids)->update(['is_participate' => $is_participate_promotion]);
        $promotion_shop = PromotionShop::where('shop_id', $shop_id)->firstOrFail();
        $promotion_rate_id = $promotion_shop->promotion_rate_id;
        if ($is_default_rate) {
            $query_set = PromotionContent::where(['shop_id' => $shop_id, 'content_type' => $type])
                ->whereIn('content_id', $content_ids);
            $promotion_rate_ids = $query_set->where('promotion_rate_id', '!=', $promotion_rate_id)
                ->pluck('promotion_rate_id')->toArray();
            $result = PromotionContent::where(['shop_id' => $shop_id, 'content_type' => $type])
                ->whereIn('content_id', $content_ids)
                ->update(['promotion_rate_id' => $promotion_rate_id]);
            if ($result) {
                if ($promotion_rate_ids && count($promotion_rate_ids) > 0) {
                    PromotionRate::whereIn('id', $promotion_rate_ids)->delete();
                }
            }
        } else {
            $ids = PromotionContent::where(['shop_id' => $shop_id, 'content_type' => $type])
                ->whereIn('content_id', $content_ids)
                ->where('promotion_rate_id', $promotion_rate_id)->pluck('id')->toArray();
            if ($ids && count($ids) > 0) {
                foreach ($ids as $id) {
                    $params[] = [
                        'shop_id' => $shop_id,
                        'promoter_rate' => $promoter_rate,
                        'invite_rate' => $invite_rate,
                        'promotion_content_id' => $id,
                    ];
                }
                isset($params) && PromotionRate::insert($params);
                $promotion_rates = PromotionRate::whereIn('promotion_content_id', $ids)->pluck('id', 'promotion_content_id')->toArray();
                if($promotion_rates && count($promotion_rates) > 0){
                    foreach ($promotion_rates as $key => $value) {
                        $update[] = ['id' => $key, 'promotion_rate_id' => $value];
                    }
                }
                isset($update) && $this->updateBatch('promotion_content', $update);
            }
            $ids = PromotionContent::where(['shop_id' => $shop_id, 'content_type' => $type])
                ->whereIn('content_id', $content_ids)
                ->where('promotion_rate_id', '!=', $promotion_rate_id)->pluck('promotion_rate_id')->toArray();
            if($ids && count($ids) > 0){
                PromotionRate::whereIn('id', $ids)->update(['promoter_rate' => $promoter_rate, 'invite_rate' => $invite_rate]);
            }
        }
        return $this->output(['success' => 1]);
    }

    /**
     * (商品)内容列表
     *
     * @return \Illuminate\Http\JsonResponse
     */
//    public function listContent()
//    {
//        $this->validateWith([
//            'count' => 'numeric',
//            'type'  => 'required|alpha_dash|in:column,course,article,audio,video,live,member_card',
//            'title' => 'alpha_dash'
//        ]);
//        $count = request('count') ? : 10;
//        $content = PromotionContent::select('id', 'content_id', 'content_type as type', 'content_title as title', 'money_percent', 'visit_percent', 'is_participate')
//            ->where('shop_id', $this->shop['id']);
//        request('type') && $content->where('content_type',request('type'));
//        request('title') && $content->where('content_title','like','%'.request('title').'%');
//        $data = $content->paginate($count);
//        $dataItems = $data->items();
//        $dataItemsContentId = [];
//        foreach ($dataItems as $i) {
//            $dataItemsContentId[] = $i->content_id;
//        }
//        $promotionSale = PromotionRecord::select(DB::raw("count(id) as nums, CONCAT(content_type,'-',content_id) as content"))   //推广总销售量
//            ->where(['shop_id'=>$this->shop['id'],'state'=>1, 'promotion_type'=>'promotion'])
//            ->whereIn('content_id', $dataItemsContentId)
//            ->groupBy('content_type','content_id') // content_id字段不是唯一的
//            ->pluck('nums','content')
//            ->toArray();
//        $totalSale = Order::select(DB::raw("count(id) as nums, CONCAT(content_type,'-',content_id) as content"))   //总销售量
//            ->where(['shop_id'=>$this->shop['id'],'pay_status'=>1])
//            ->whereIn('content_id', $dataItemsContentId)
//            ->groupBy('content_type','content_id')
//            ->pluck('nums','content')
//            ->toArray();
//        $promotion_shop = PromotionShop::select('money_percent','visit_percent','is_visit')->where('shop_id',$this->shop['id'])->firstOrFail();
//        if ($data->items()) {
//            foreach ($data->items() as $item) {
//                $_ = $item->type.'-'.$item->content_id; // CONCAT(content_type,'-',content_id)
//                if ($item->type == 'column') {
//                    $item->indexpic = $item->belongsColumn ? hg_unserialize_image_link($item->belongsColumn->indexpic) : [];
//                    $item->price = $item->belongsColumn ? $item->belongsColumn->price : 0;
//                } elseif ($item->type == 'course') {
//                    $item->indexpic = $item->belongsCourse ? hg_unserialize_image_link($item->belongsCourse->indexpic) : [];
//                    $item->price = $item->belongsCourse ? $item->belongsCourse->price : 0;
//                } elseif ($item->type == 'member_card') {
//                    $item->indexpic = MemberCard::INDEXPIC;
//                    $item->price = $item->belongsMemberCard ? $item->belongsMemberCard->price : 0;
//                } else {
//                    $item->indexpic = $item->belongsContent ? hg_unserialize_image_link($item->belongsContent->indexpic) : [];
//                    $item->price = $item->belongsContent ? $item->belongsContent->price : 0;
//                }
//                $item->promotion_sale_count = array_key_exists($_,$promotionSale) ? $promotionSale[$_] : 0;
//                $item->sale_count = array_key_exists($_,$totalSale) ? $totalSale[$_] : 0;
//                $item->mp_default = ($item->money_percent < 0) ? 1 : 0;
//                $item->vp_default = ($item->visit_percent < 0) ? 1 : 0;
//                $item->money_percent = ($item->money_percent < 0) ? $promotion_shop->money_percent : $item->money_percent;
//                $item->visit_percent = ($item->visit_percent < 0) ? $promotion_shop->visit_percent : $item->visit_percent;
//                $item->title = $item->title ? : '';
//                $item->is_visit = intval($promotion_shop->is_visit);
//            }
//        }
//        return $this->output($this->listToPage($data));
//    }

    /**
     * 推广商品列表
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function listContent()
    {
        $this->validateWith([
            'count' => 'numeric',
            'type' => 'required|alpha_dash|in:column,course,article,audio,video,live,member_card',
            'is_participate_promotion' => 'numeric|in:0,1',
        ]);
        $request = app('request');
        $shop_id = $this->shop['id'];
        $type = request('type');
        $order_by = 'create_time';
        $direction = 'desc';
        $count = request('count') ?: 10;
        $title = request('title');
        $order_by_param = request('order_by');
        if ($order_by_param && in_array($order_by_param, self::ORDER_BY_TYPE)) {
            $direction = strpos($order_by_param, '-') === 0 ? 'desc' : 'asc';
            $order_by = str_replace('-', '', $order_by_param);
        }
        if ($type == 'member_card' && $order_by == 'create_time') {
            $order_by = 'created_at';
        }

        $table = $type;
        switch ($type) {
            case 'column':
                $query_set = Column::where([$table . '.shop_id' => $shop_id, 'state' => 1])
                    ->where($table . '.price', '>', 0);
                $select = [$table . '.indexpic', $table . '.create_time'];
                break;
            case 'course':
                $query_set = Course::where([$table . '.shop_id' => $shop_id, 'state' => 1])
                    ->where($table . '.price', '>', 0);
                $select = [$table . '.indexpic', $table . '.create_time', $table.'.course_type'];
                break;
            case 'member_card':
                $query_set = MemberCard::where([$table . '.shop_id' => $shop_id, 'status' => 1])
                    ->where($table . '.price', '>', 0);
                $select = [$table . '.created_at'];
                break;
            default:
                $table = 'content';
                $query_set = Content::where([$table . '.shop_id' => $shop_id, $table . '.type' => $type])
                    ->where($table . '.price', '>', 0)
                    ->where($table . '.column_id', 0)
                    ->where(function ($query) {
                        $query->where('state', 1)->orWhere('state', 0);
                    })->where('up_time', '<', time());
                $select = [$table . '.indexpic', $table . '.create_time'];
                break;
        }

        $promotion_rate_id = PromotionShop::where('shop_id', $shop_id)->value('promotion_rate_id');

        $select_list = [$table . '.hashid as content_id', $table . '.title', $table . '.price', $table . '.sales_total',
            'promotion_content.promotion_sales_total', 'promotion_rate.promoter_rate', 'promotion_content.promotion_rate_id',
            'promotion_rate.invite_rate', 'promotion_content.is_participate as is_participate_promotion'];

        $select_list = array_merge($select_list, $select);
        $query_set = $query_set->select($select_list)->selectRaw('hg_promotion_rate.promoter_rate*price as commission')
            ->join('promotion_content', function ($join) use ($table, $type) {
                $join->on('promotion_content.shop_id', '=', $table . '.shop_id')
                    ->whereColumn('promotion_content.content_id', $table . '.hashid')
                    ->where('promotion_content.content_type', $type);
            }, '', '', 'inner')
            ->leftJoin('promotion_rate', 'promotion_rate.id', 'promotion_content.promotion_rate_id');

        if ($title) {
            $query_set->where($table . '.title', 'like', '%' . $title . '%');
        }
        if ($request->has('is_participate_promotion')) {
            $is_participate_promotion = request('is_participate_promotion');
            $query_set->where(['promotion_content.is_participate' => $is_participate_promotion]);
        }
        $data = $query_set->orderBy($order_by, $direction)->paginate($count);

        if ($data->items()) {
            foreach ($data->items() as $item) {
                $item->is_default_rate = $promotion_rate_id == $item->promotion_rate_id ? 1 : 0;
                $item->commission = number_format($item->commission / 100, 2, '.', '');
                if ($item->create_time) {
                    $item->create_time = hg_format_date($item->create_time);
                }
                if ($item->created_at) {
                    $item->create_time = hg_format_date(strtotime($item->created_at));
                }
                $item->type = $type;
                if($type == 'member_card'){
                    $item->indexpic = MemberCard::INDEX_PIC_DEFAULT;
                } else {
                    $item->indexpic = hg_parse_image_link($item->indexpic);
                }

                $item->makeHidden(['promotion_rate_id']);
            }
        }

        return $this->output($this->listToPage($data));
    }

    /**
     * 内容设置比列
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function percentContent()
    {
        $this->validateWith([
            'content_id'    => 'required|alpha_dash',
            'type'          => 'required|alpha_dash|in:column,course,article,audio,video,live,member_card',
            'money_percent' => 'required|numeric|max:80',
            'visit_percent' => 'required|numeric|max:80'
        ]);
        $money_percent = request('money_percent');
        $visit_percent = request('visit_percent');
        if($money_percent + $visit_percent > 100){
            $this->error('max-percent-error');
        }
        if (($money_percent < 0 && $visit_percent >= 0) || ($money_percent >= 0 && $visit_percent < 0)) {
            $this->error('promotion-percent-setting-error');
        }
        PromotionContent::where(['shop_id'=>$this->shop['id'],'content_id'=>request('content_id'),'content_type'=>request('type')])->update(['money_percent'=>$money_percent,'visit_percent'=>$visit_percent]);
        return $this->output(['success'=>1]);
    }

    /**
     * 根据content_id获取比例
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPercent()
    {
        $this->validateWith([
            'content_id'    => 'required|alpha_dash',
            'type'          => 'required|alpha_dash|in:column,course,article,audio,video,live,member_card'
        ]);
        $promotion_shop = PromotionShop::select('money_percent','visit_percent','is_visit')
            ->where('shop_id',$this->shop['id'])
            ->firstOrFail();
        $percent = PromotionContent::select('money_percent','visit_percent')
            ->where(['shop_id'=>$this->shop['id'],'content_id'=>request('content_id'),'content_type'=>request('type')])
            ->firstOrFail();
        $percent->mp_default = ($percent->money_percent < 0) ? 1: 0;
        $percent->vp_default = ($percent->visit_percent < 0) ? 1: 0;
        $percent->money_percent = ($percent->money_percent < 0) ? $promotion_shop->money_percent : $percent->money_percent;
        $percent->visit_percent = ($percent->visit_percent < 0) ? $promotion_shop->visit_percent : $percent->visit_percent;
        $percent->is_visit = intval($promotion_shop->is_visit);
        return $this->output($percent);
    }
}
<?php
namespace App\Http\Controllers\H5\Content;

use App\Models\Banner;
use App\Models\Column;
use App\Models\Course;
use App\Models\Content;
use App\Models\Community;
use App\Models\MarketingActivity;
use App\Models\Navigation;
use App\Models\MemberCard;
use App\Http\Controllers\H5\BaseController;
use App\Models\Shop;
use App\Models\AppletCommit;

class MultiTypeListController extends BaseController
{
    /**
     * 首页列表接口整合
     */
    public function multiTypeList()
    {
        $shopId = $this->shop['id'];
        $data = [];

        //小程序是否审核中
        $applet_audit_status = $this->checkAppletAuditStatus();
        $common_filters = $this->contentCommonFilters();
        
        //banner数据
        if(request('banner')){
            $data['bannner'] = $applet_audit_status ? []:$this->bannerContent($shopId);
        }
        //导航数据
        if(request('navigation')){
            $data['navigation'] = $applet_audit_status ? []:$this->navigationContent($shopId);
        }
        //直播数据
        if(request('live')){
            $data['live'] = $this->liveContent($shopId, $common_filters);
        }
        //会员卡数据
        if(request('card')){
            $data['card'] = $this->cardContent($shopId, $common_filters);
        }
        //专栏数据
        if(request('column')){
            $data['column'] = $this->columnContent($shopId, $common_filters);
        }
        //课程数据
        if(request('course')){
            $data['course'] = $this->courseContent($shopId, $common_filters);
        }
        //小社群数据
        if(request('community')){
            $data['community'] = $applet_audit_status ? []:$this->communityContent($shopId);
        }
        //内容数据
        if(request('content')){
            $data['content'] = $this->content($shopId, $common_filters);
        }

        return $this->output($data);
    }

    protected function bannerContent($shopId)
    {
        $banner = request('banner');
        $bannerType = isset($banner['type']) && !empty($banner['type']) ? $banner['type'] : 'home';
        $bannerCount = isset($banner['count']) && !empty($banner['count']) ? $banner['count'] : 10;
        $bannerWhere = [
            'shop_id' => $shopId,
            'state' => 1,
            'type' => $bannerType
        ];
        if(isset($banner['type_id']) && !empty($banner['type_id'])){
            $bannerWhere['type_id'] = $banner['type_id'];
        }
        $bannerData = Banner::where($bannerWhere)->orderBy('order_id')
            ->orderByDesc('top')
            ->orderByDesc('update_time')
            ->select('id','shop_id','title','indexpic','link','top')
            ->take($bannerCount)
            ->get();
        return $this->bannerResponse($bannerData);
    }

    protected function navigationContent($shopId)
    {
        $navigation = request('navigation');
        $navigationCount = isset($navigation['count']) && !empty($navigation['count']) ? $navigation['count'] : 10;
        $navigationData = Navigation::where(['shop_id'=>$shopId,'status'=>1])
            ->orderBy('order_id','asc')
            ->take($navigationCount)
            ->get();
        if($navigationData){
            foreach($navigationData as $item){
                $link = $item->link ? unserialize($item->link) : [];
                if(isset($link['id'],$link['type'])) {
                    $link['is_free'] = $this->bannerFormatFree($link);
                    $link['type'] == 'course' && $link['course_type'] = Course::where('hashid',$link['id'])->value('course_type');
                }
                $item->link = $link;
                $item->makeVisible(['link']);
                $item->index_pic = hg_unserialize_image_link($item->index_pic);
            }
        }
        return $navigationData;
    }

    protected function liveContent($shopId, $filters)
    {
        $live = request('live');
        $liveCount = isset($live['count']) && !empty($live['count']) ? $live['count'] : 5;
        $sql = Content::join('live','live.content_id','=','content.hashid');
        $sql = $this->filterSql($sql, $filters);
        $liveData = $sql->where(['content.type'=>'live','content.display'=>1,'content.shop_id'=>$shopId])
            ->where(function ($query) {
                $query->where('content.state',1)->orWhere('content.state',0);
            })
            ->where('content.payment_type', '!=', 1)
            ->where('content.up_time' ,'<', time())
            ->orderBy('order_id')
            ->orderBy('start_time','desc')
            ->take($liveCount)
            ->get();
        return $this->formatContent($liveData);
    }

    protected function cardContent($shopId, $filters)
    {
        $card = request('card');
        $cardCount = isset($card['count']) && !empty($card['count']) ? $card['count'] : 10;
        $sql = MemberCard::where(['shop_id'=>$shopId,'status'=>1]);
        $sql = $this->filterSql($sql, $filters);
        $cardData = $sql->orderBy('top','desc')
            ->orderBy('updated_at','desc')
            ->take($cardCount)
            ->get();
        if($cardData){
            foreach ($cardData as $item){
                $item->up_time = $item->up_time ? hg_format_date($item->up_time) : '';
                $item->expire = $item->expire ? intval($item->expire) : 0;
                $item->status = $item->status ? intval($item->status) : 0;
                $item->style = $item->style ? intval($item->style) : 1;
            }
        }
        return $cardData;
    }

    protected function columnContent($shopId, $filters)
    {
        $column = request('column');
        $columnCount = isset($column['count']) && !empty($column['count']) ? $column['count'] : 5;
        $where = ['state'=>1,'display'=>1,'column.shop_id'=>$shopId];
        if(trim(request('source'))=='wx_applet'){
            $where['charge'] = 0;
            $where['price'] = 0.00;
        }
        $sql = Column::where($where);
        $sql = $this->filterSql($sql, $filters);
        request('type_id') && $sql->join('content_type','content_type.content_id','=','column.hashid')
            ->where('content_type.type_id',request('type_id'))
            ->select('column.*');
        $columnData = $sql->orderBy('column.order_id')
            ->orderBy('column.top','desc')
            ->orderBy('column.update_time','desc')
            ->take($columnCount)
            ->get();
        return $this->formatColumn($columnData);
    }

    protected function courseContent($shopId, $filters)
    {
        $course = request('course');
        $courseCount = isset($course['count']) && !empty($course['count']) ? $course['count'] : 10;
        $version = Shop::where('hashid',$this->shop['id'])->value('applet_version');
        $where = ['shop_id' => $shopId, 'state' => 1, 'is_lock' => 0];
        if(request('source') == 'wx_applet' && $version == 'basic'){
            $where['pay_type'] = 0;
        }
        $sql = Course::where($where);
        $sql = $this->filterSql($sql, $filters);
        $courseData = $sql->select('hashid', 'shop_id', 'title', 'indexpic', 'subscribe', 'price', 'is_finish', 'pay_type', 'course_type', 'describe', 'brief')
                ->orderBy('order_id')
                ->orderBy('top', 'desc')
                ->orderBy('create_time', 'desc')
                ->take($courseCount)
                ->get();
        if($courseData){
            foreach ($courseData as $item) {
                $item->content_id = $item->hashid;
                $item->hour_count = $item->class_hour ? $item->class_hour->count() : 0;
                $item->indexpic = $item->indexpic ? hg_unserialize_image_link($item->indexpic ) : '';
                $item->subscribe =hg_caculate_multiple($item->subscribe,'subscribe',$this->shop['id']);
                $item->type = 'course';
                $item->market_sign = MarketingActivity::where(['shop_id'=>$this->shop['id'],'content_id'=>$item->hashid,'content_type'=>'course'])->value('marketing_type')?:'';

            }
        }
        return $courseData;
    }

    private function communityContent($shopId)
    {
        $community = request('community');
        $columnCount = isset($community['count']) && !empty($community['count']) ? $community['count'] : 10;
        $sql = Community::select('hashid as community_id','shop_id','title','brief','indexpic','pay_type','price','member_num','created_at');
        $communityData = $sql->where(['shop_id'=>$shopId,'display'=>1])
            ->take($columnCount)
            ->get();
        if($communityData){
            foreach($communityData as $item){
                $item->indexpic = $item->indexpic ? hg_unserialize_image_link($item->indexpic ) : '';
            }
        }
        return $communityData;
    }

    protected function content($shopId, $filters)
    {
        $content = request('content');
        $contentCount = isset($content['count']) && !empty($content['count']) ? $content['count'] : 8;
        $types = ['audio','video','article'];
        $not_select_pay_type = [1,4];
        $where = ['display'=>1,'shop_id'=>$shopId];
        trim(request('source')) == 'wx_applet' && $where['payment_type'] = 3;
        $sql = Content::whereIn('type',$types);
        $sql = $this->filterSql($sql, $filters);
        $contentData = $sql->where($where)
            ->where(function ($query) {
                $query->where('state',1)->orWhere('state',0);
            })
            ->where('up_time','<', time())
            ->whereNotIn('payment_type', $not_select_pay_type)
//            ->orderBy('order_id')
//            ->orderBy('top','desc')
            ->orderBy('update_time','desc')
            ->paginate($contentCount);
        $columnData = $this->formatContent($contentData);
        return $this->listToPage($columnData);
    }

    private function bannerResponse($banner){
        if($banner){
            foreach($banner as $item){
                $link = $item->link ? unserialize($item->link) : [];
                if(isset($link['id'],$link['type'])) {
                    $link['is_free'] = $this->bannerFormatFree($link);
                    $link['type'] == 'course' && $link['course_type'] = Course::where('hashid',$link['id'])->value('course_type');
                }
                $item->link = $link;
                $item->makeVisible(['link']);
                $item->indexpic = hg_unserialize_image_link($item->indexpic);
            }
        }
        return $banner;
    }

    private function bannerFormatFree($link){
        if($link['type']=='column'){
            $is_free = Column::where('hashid',$link['id'])->value('price')=='0.00'?1:0;
        }elseif($link['type']=='course'){
            $is_free = Course::where('hashid',$link['id'])->value('pay_type') ? 0 : 1;
        }elseif($link['type']=='community'){
            $is_free = Community::where('hashid',$link['id'])->value('pay_type') ? 0 : 1;
        } else{
            $is_free = Content::where('hashid',$link['id'])->value('price')=='0.00'?1:0;
        }
        return $is_free;
    }

    private function formatContent($data){
        if($data){
            foreach ($data as $k=>$v){
                if($v->type=='live'){
                    $start_time = $v->start_time?:$v->alive->start_time;
                    $end_time = $v->end_time?:$v->alive->end_time;
                    $start_time > time() && $v->live_state = 0;
                    $start_time < time() && $end_time > time() && $v->live_state = 1;
                    $end_time < time() && $v->live_state = 2;
                    $v->live_indexpic = hg_unserialize_image_link($v->live_indexpic);
                }
                if($v->column_id!=0){
                    $column = $v->column;
                    $v->column_title = $column->title;
                    $v->column_price = $column->price;
                    $v->column_charge = intval($column->charge) ? 1 : 0;
                    $v->column_finish = intval($column->finish);
                    $v->column_indexpic =hg_unserialize_image_link($column->indexpic);
                    $v->column_subscribe = intval($column->subscribe);
                    $v->column_stage = count($column->content);
                }
                $v->content_id = $v->hashid;
                $v->create_time = $v->create_time?date('m-d',$v->create_time) : 0;
                $v->update_time = $v->update_time?date('m-d',$v->update_time) : 0;
                $v->up_time = $v->up_time?date('Y-m-d H:i:s',$v->up_time) : 0;
                $v->start_time && $v->start_time = date('Y-m-d H:i:s',$v->start_time);
                $v->end_time && $v->end_time = date('Y-m-d H:i:s',$v->end_time);
                $v->brief && $v->brief = htmlspecialchars_decode(str_ireplace('&nbsp;','',mb_substr(strip_tags($v->brief),0,100)));
                $v->column_id = $v->column_id?intval($v->column_id):0;
                $v->indexpic = hg_unserialize_image_link($v->indexpic);
                $v->view_count = $this->formatMultiple('view',$v->view_count);
                $v->is_test = intval($v->is_test) ? 1 : 0;
                $v->market_sign = MarketingActivity::where(['shop_id'=>$this->shop['id'],'content_id'=>$v->hashid,'content_type'=>$v->type])->value('marketing_type')?:'';
            }
        }
        return $data?:[];
    }

    private function formatColumn($data){
        if($data){
            foreach ($data as $k=>$v){
                $v->column_id = $v->hashid;
                $v->create_time = $v->create_time?date('Y-m-d',$v->create_time):0;
                $v->update_time = $v->update_time?date('Y-m-d',$v->update_time):0;
                if(request('source')=='wx_applet'){
                    $v->stage = count($v->content->where('payment_type',1));
                }else{
                    $v->stage = count($v->content);
                }
                $v->indexpic = hg_unserialize_image_link($v->indexpic);
                $v->subscribe = $this->formatMultiple('subscribe',$v->subscribe);
                $v->market_sign = MarketingActivity::where(['shop_id'=>$this->shop['id'],'content_id'=>$v->hashid,'content_type'=>'column'])->value('marketing_type')?:'';
                unset($v->content);
            }
        }
        return $data?:[];
    }

    private function formatMultiple($range,$count){
        return hg_caculate_multiple($count,$range,$this->shop['id']);
    }
}
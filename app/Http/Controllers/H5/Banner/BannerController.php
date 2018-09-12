<?php
/**
 * banner图管理
 */

namespace App\Http\Controllers\H5\Banner;


use App\Http\Controllers\H5\BaseController;
use App\Models\Banner;
use App\Models\Column;
use App\Models\Content;
use App\Models\Course;
use Illuminate\Support\Facades\Cache;

class BannerController extends BaseController
{

    public function lists(){
        $this->validateWithAttribute([
            'type'  => 'alpha_dash|in:new,column,navigation',
            'type_id'   => 'required_if:type,navigation|numeric'
        ],[
            'type'  => '轮播图类型',
            'type_id'   => '导航分类id',
        ]);
        $type = request('type') ? : 'home';
        $result = Cache::get('banner:'.$type.':'.$this->shop['id']);
        if($result){
            return $this->output(json_decode($result));
        }else{
            $banner = $this->get_banner_list();
            $response = $this->get_banner_response($banner);
            if($response['page']['total'] != 0 && $type == 'default') {
                Cache::forever('banner:'. $type . ':' . $this->shop['id'], json_encode($response));
            }
            return $this->output($response);
        }
    }

    /**
     * 获取banner列表(分页)
     * @return array
     */
    private function get_banner_list(){
        $count = request('page') ? intval(request('page')) : 10;
        $type = request('type') ? : 'home';
        $sql = Banner::where(['shop_id'=>$this->shop['id'],'state'=>1,'type'=>$type]);
        if(request('type_id')){
            $sql->where('type_id',request('type_id'));
        }
        $banner = $sql->orderBy('order_id')
            ->orderByDesc('top')
            ->orderByDesc('update_time')
            ->select('id','shop_id','title','indexpic','link','top')
            ->paginate($count);
        return $this->listToPage($banner);
    }

    /**
     * 获取列表返回值
     * @param $banner
     * @return mixed
     */
    private function get_banner_response($banner){
        if($banner && $banner['data']){
            foreach($banner['data'] as $item){
                $link = $item->link ? unserialize($item->link) : [];
                if(isset($link['id'],$link['type'])) {
                    $link['is_free'] = $this->format_free($link);
                    $link['type'] == 'course' && $link['course_type'] = Course::where('hashid',$link['id'])->value('course_type');
                }
                $item->link = $link;
                $item->makeVisible(['link']);
                $item->indexpic = hg_unserialize_image_link($item->indexpic);
            }
        }
        return $banner;
    }

    private function format_free($link){
        if($link['type']=='column'){
            $is_free = Column::where('hashid',$link['id'])->value('price')=='0.00'?1:0;
        }elseif($link['type']=='course'){
            $is_free = Course::where('hashid',$link['id'])->value('pay_type') ? 0 : 1;
        } else{
            $is_free = Content::where('hashid',$link['id'])->value('price')=='0.00'?1:0;
        }
        return $is_free;
    }

}
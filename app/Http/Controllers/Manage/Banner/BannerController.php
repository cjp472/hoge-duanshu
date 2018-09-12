<?php
/* 商铺banner */
namespace App\Http\Controllers\Manage\Banner;
use App\Events\AdminLogsEvent;
use App\Http\Controllers\Manage\BaseController;
use App\Models\Manage\Banner;

class BannerController extends BaseController
{
    /**
     * 商铺banner列表
     * @return \Illuminate\Http\JsonResponse
     */
    public function bannerList()
    {
        $this->validateWith([
           'shop_id'   =>   'required|alpha_dash',
            'count'    =>   'numeric'
        ]);
        $count = request('count') ? : 10;
        $banner = Banner::where('shop_id',request('shop_id'))
            ->select('id','title','indexpic','up_time','link','top','state','is_lock','create_time','create_user')
            ->orderBy('create_time','desc')
            ->paginate($count);
        if ($banner->items()) {
            foreach ($banner->items() as $item) {
                $item->up_time = $item->up_time ? hg_format_date($item->up_time) : '';
                $item->create_time = $item->create_time ? hg_format_date($item->create_time) : '';
                $item->link = $item->link ? unserialize($item->link) : '';
                $item->indexpic = $item->indexpic ? hg_unserialize_image_link($item->indexpic) : '';
            }
        }
        return $this->output($this->listToPage($banner));
    }

    /**
     * banner上下架
     * @return \Illuminate\Http\JsonResponse
     */
    public function bannerState()
    {
        $this->validateWith([
            'id'     => 'required|numeric',
            'state'  => 'required|numeric|in:0,1'
        ]);
        Banner::where('id',request('id'))->update(['state'=>request('state'),'up_time'=>time()]);
        return $this->output(['success'=>1]);


    }

    /**
     * banner锁定状态
     * @return \Illuminate\Http\JsonResponse
     */
    public function bannerLock()
    {
        $this->validateWith([
            'id'     => 'required|numeric',  //banner的id
            'lock_state'  => 'required|numeric|in:0,1'
        ]);
        Banner::where('id',request('id'))->update(['is_lock'=>request('lock_state'),'up_time'=>time()]);
        return $this->output(['success'=>1]);
    }
}
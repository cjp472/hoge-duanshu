<?php

/**
 * 用户设置
 */
namespace App\Http\Controllers\Admin\Setting;

use App\Http\Controllers\Admin\BaseController;
use App\Models\Share;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class ShareController extends BaseController
{
    /**
     * 查看分享信息
     * @return mixed
     */
    public function detail()
    {
        $share = Share::Where(['shop_id'=>$this->shop['id']])->firstOrCreate(['shop_id'=>$this->shop['id']]);
        $share->indexpic = hg_unserialize_image_link($share->indexpic) ? : (object)[];
        $share->qrcode = hg_unserialize_image_link($share->qrcode) ? : (object)[];
        return $this->output($share);
    }

    /**
     * 编辑分享信息
     * @return mixed
     */
    public function update()
    {

        $this->validateWithAttribute([
            'title'     => 'max:64',
            'brief'     => 'max:256',
        ],[
            'title'     => '标题',
            'brief'     => '描述',
        ]);
        $share = Share::where(['shop_id'=>$this->shop['id']])->firstOrCreate(['shop_id'=>$this->shop['id']]);
        $share->title = request('title')? : $share->title;
        $share->brief = addslashes(trim(request('brief'))) ? : $share->brief;
        $share->qrcode = hg_explore_image_link(request('qrcode')) ? : $share->qrcode;
        $share->indexpic = hg_explore_image_link(request('indexpic')) ? : $share->indexpic;
        $share->saveOrFail();
        Cache::forget('share:'.$this->shop['id']);  //清除redis里面店铺分享信息
        return $this->output($share);
    }
}
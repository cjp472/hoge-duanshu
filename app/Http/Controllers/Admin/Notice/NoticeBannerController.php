<?php
/**
 * Created by PhpStorm.
 * User: tanqiang
 * Date: 2018/7/18
 * Time: 下午6:33
 */

namespace App\Http\Controllers\Admin\Notice;


use App\Http\Controllers\Admin\BaseController;
use App\Models\ShopNotice;


class NoticeBannerController extends BaseController
{
    public function lists()
    {
        $shop_id = $this->shop['id'];
        $list = ShopNotice::getShopNoticeList($shop_id);
        return $this->output($list);
    }
}
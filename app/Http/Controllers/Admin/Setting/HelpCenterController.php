<?php
/**
 * 首页分类导航
 */
namespace App\Http\Controllers\Admin\Setting;

use App\Models\HelpCenter;
use App\Http\Controllers\Admin\BaseController;

class HelpCenterController extends BaseController
{
    const PAGINATE = 20;
    /**
     * 获取帮助中心列表
    */
    public function getList()
    {
        $count = request('count') ? intval(request('count')) : self::PAGINATE;
        $result = HelpCenter::where('is_display',1)->orderBy('sort_no','desc')->paginate($count);
        return $this->listToPage($result);
    }
}
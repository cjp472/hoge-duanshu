<?php
namespace App\Http\Controllers\Manage\Shop;
use App\Http\Controllers\Manage\BaseController;
use App\Models\Manage\Type;

class TypeController extends BaseController
{
    /**
     * 商铺导航分类
     * @return \Illuminate\Http\JsonResponse
     */
    public function shopType()
    {
        $this->validateWith([
           'shop_id'   => 'required|alpha_dash',
            'count'    => 'numeric'
        ]);
        $count = request('count') ? : 10;
        $type = Type::where('shop_id',request('shop_id'))
            ->select('id','title','indexpic','status')
            ->orderBy('order_id')
            ->paginate($count);
        if ($type->items()) {
            foreach ($type->items() as $item) {
                $item->indexpic =  $item->indexpic ? hg_unserialize_image_link($item->indexpic) : '';
            }
        }
        return $this->output($this->listToPage($type));
    }

    /**
     * 商铺导航状态改变
     * @return \Illuminate\Http\JsonResponse
     */
    public function changeState()
    {
        $this->validateWith([
            'id'        => 'required|numeric',
            'status'    => 'required|numeric|in:0,1'
        ]);
        $type = Type::where('id',request('id'))->update(['status'=>request('status')]);
        return $this->output(['success'=>1]);
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: Allen
 * Date: 17/12/28
 * Time: 上午9:03
 */
namespace App\Http\Controllers\Admin\Promotion;
use App\Http\Controllers\Admin\BaseController;
use App\Models\Manage\Shop;
use App\Models\Promotion;
use App\Models\PromotionRate;
use App\Models\PromotionShop;
use Illuminate\Support\Facades\Cache;

class SetController extends BaseController
{
    /**
     * 设置是否审核
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function setCheck()
    {
        $this->validateWith([
           'is_check' => 'required|numeric|in:0,1'
        ]);
        //$this->shop['id'] = 'AjvVMVNMY39p';
        $promotion = Promotion::where(['shop_id'=>$this->shop['id'],'state'=>2,'is_delete'=>0])->value('id');
        if (!request('is_check') && $promotion) {
            $this->errorWithText(1,'存在未审核的推广员');
        }
        PromotionShop::where('shop_id',$this->shop['id'])->update(['is_check'=>request('is_check')]);
        return $this->output(['success'=>1]);
    }

    /**
     * 设置比例
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function setPercent()
    {
        $this->validateWith([
            'money_percent' => 'required|numeric|max:80|min:0',
            'is_visit'      => 'required|numeric|in:0,1',
            'visit_percent' => 'numeric|max:80|min:0'
        ]);
        if(request('money_percent') + request('visit_percent') > 100){
            $this->error('max-percent-error');
        }
        $data = [
            'money_percent' => request('money_percent'),
            'is_visit'      => request('is_visit')
        ];
        if (request('is_visit') == 1) {
            $data['visit_percent'] = request('visit_percent');
        }
        PromotionShop::where('shop_id',$this->shop['id'])->update($data);
        return $this->output(['success'=>1]);
    }

    /**
     * 店铺比例详情
     *
     * @return \Illuminate\Database\Eloquent\Model|static
     */
    public function detailPercent()
    {
        $percent = PromotionShop::select('is_check','promoter_rate','is_visit',
            'invite_rate', 'open_bind_mobile', 'valid_time', 'auto_join_promotion', 'open_recruit')
            ->leftJoin('promotion_rate', 'promotion_rate.id', 'promotion_shop.promotion_rate_id')
            ->where('promotion_shop.shop_id',$this->shop['id'])->firstOrFail();
        $promotion = Promotion::where(['shop_id'=>$this->shop['id'],'state'=>2,'is_delete'=>0])->value('id');
        $percent->is_check = intval($percent->is_check);
        $percent->money_percent = intval($percent->promoter_rate);
        $percent->is_visit = intval($percent->is_visit);
        $percent->visit_percent = intval($percent->invite_rate);
        $percent->promotion_check = $promotion ? 1 : 0;
        $percent->not_check_num = Promotion::where(['shop_id'=>$this->shop['id'],'state'=>2,'is_delete'=>0])->count();
        return $this->output($percent);
    }

    /**
     * 招募计划创建或修改
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function planCreateOrUpdate()
    {
        $this->validateWith([
            'title'    => 'required|alpha_dash|max:32',
            'content'  => 'required'
        ]);
        $plan = PromotionShop::where('shop_id',$this->shop['id'])->first();
        $plan->re_title = trim(request('title'));
        $plan->re_plan = request('content');
        $plan->saveOrFail();
        return $this->output(['success'=>1]);
    }

    /**
     * 招募计划详情
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function planDetail()
    {
        $detail = PromotionShop::select('re_title','re_plan')->where('shop_id',$this->shop['id'])->firstOrFail();
        return $this->output($detail);
    }

    /**
     * 设置推广员开通状态
     */
    public function setPromotionStatus(){
        $this->validateWithAttribute([
            'status'    => 'required|numeric|in:0,1'
        ],[
            'status'    => '推广员开通状态'
        ]);

        $shop = Shop::where(['hashid'=>$this->shop['id']])->firstOrFail();
        $shop->is_promotion = request('status') ? 1 : 0;
        $shop->save();
        $id = PromotionShop::where('shop_id',$this->shop['id'])->value('id');
        if (!$id) {
            PromotionShop::insert(['shop_id'=>$this->shop['id']]);
        }
        return $this->output(['success'=>1]);
    }


    /**
     * 获取推广员开通状态
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkPromotionStatus(){
        $status = Shop::where(['hashid'=>$this->shop['id']])->value('is_promotion');
        return $this->output(['status'=>intval($status)]);
    }

    /**
     * 店铺推广设置
     */
    public function setting()
    {
        $keys = ['open_bind_mobile', 'valid_time', 'auto_join_promotion', 'open_recruit', 'is_check', 'is_visit'];
        $rate_keys = ['promoter_rate', 'invite_rate'];
        $this->validateWith([
            'open_bind_mobile' => 'numeric|in:0,1',
            'valid_time' => 'numeric|min:0|max:100',
            'auto_join_promotion' => 'numeric|in:0,1',
            'open_recruit' => 'numeric|in:0,1',
            'is_check' => 'numeric|in:0,1',
            'money_percent' => 'numeric|min:0|max:80',
            'visit_percent' => 'numeric|min:0|max:80',
            'is_visit' => 'numeric|in:0,1',
        ]);

        if(request('money_percent') + request('visit_percent') > 100){
            $this->error('max-percent-error');
        }

        $params = request()->all();
        $promotion_shop = PromotionShop::where('shop_id', $this->shop['id'])->firstOrFail();
        $promotion_rate = PromotionRate::where('id', $promotion_shop->promotion_rate_id)->firstOrFail();
        foreach ($params as $key => $val) {
            if (in_array($key, $keys)) {
                $promotion_shop->$key = $val;
            } else if(in_array($key, $rate_keys)){
                $promotion_rate->$key =  $val;
            }
        }
        $promotion_shop->save();
        $promotion_rate->save();
        return $this->output(['success' => 1]);
    }
}
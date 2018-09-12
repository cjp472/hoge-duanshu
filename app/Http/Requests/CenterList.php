<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CenterList extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'page' => 'required|numeric',
            'size' => 'required|numeric',
            'order_number' => 'string',
            'pay_channel'=>'string|in:none,wechat,other_pay,deposit_card,credit_card',
            'status'=>'string',
            'blocked'=>'string',
            'trade_time_start'=>'numeric',
            'trade_time_end'=>'numeric',
            'trade_total_start'=>'numeric',
            'trade_total_end'=>'numeric',
            'channel_slug' => 'string',
            'buyer_uid' => 'string',
            'buyer_platform' => 'string',
        ];
    }

    public function attributes()
    {
        return [
            'page' => '页码',
            'size' => '条数',
            'order_number' => '订单唯一标识',
            'platform'=>'平台来源',
            'pay_channel'=>'支付方式',
            'status'=>'订单状态',
            'blocked'=>'是否冻结',
            'trade_time_start'=>'交易最小时间',
            'trade_time_end'=>'交易最大时间',
            'trade_total_start'=>'订单最小金额',
            'trade_total_end'=>'订单最大金额',
            'seller_uid'=>'卖家用户标标识',
            'channel_slug' => '业务渠道标识',
            'buyer_uid' => '买家用户标识',
            'buyer_platform' => '买家平台标识',
        ];
    }

}

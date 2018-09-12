<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CenterStatus extends Request
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
            'order_no' => 'required|string',
            'status' => 'required|string|in:unpaid,undeliver,unreceipt,success,closed,refund',
            'exception_reason' => 'string',
            'buyer_message'=>'string',
            'pay_channel'=>'required_if:status,underliver|string',
            'transaction_no'=>'required_if:status,underliver|string',
            'receipt_amount'=>'required_if:status,underliver|numeric',
            'orderitems'=>'required_if:status,unreceipt',
            'id'=>'required_if:status,unreceipt|string',
            'delivery_type'=>'required_if:status,unreceipt|string|in:none,shunfeng,zhongtong',
            'delivery_no'=>'required_if:status,unreceipt|string',
        ];
    }

    public function attributes()
    {
        return [
            'order_no' => '订单号',
            'status' => '订单状态',
            'exception_reason' => '订单异常原因',
            'buyer_message'=>'买家留言',
            'pay_channel'=>'支付方式',
            'transaction_no'=>'支付流水号',
            'receipt_amount'=>'会员时间付费金额',
            'orderitems'=>'订单商品数据列表',
            'id'=>'商品唯一标识',
            'delivery_type'=>'快递公司',
            'delivery_no'=>'快递单号',
        ];
    }

}

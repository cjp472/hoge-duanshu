<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2017/3/2
 * Time: 15:30
 */

namespace App\Http\Requests;


class Goods extends Request
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
            'tags'        => 'string',
            'category' => 'string',
            'product_type' => 'string',
            'page' => 'numeric',
            'size' => 'numeric',
            'min_price' => 'numeric',
            'max_price' => 'numeric'
        ];
    }

    public function attributes()
    {
        return [
            'tags'     => '标签 多个标签以,隔开',
            'category' => '分类',
            'product_type' => '商品类型',
            'page' => '页数',
            'size' => '分页大小',
            'min_price' => '最小价格',
            'max_price' => '最大价格'
        ];
    }

}
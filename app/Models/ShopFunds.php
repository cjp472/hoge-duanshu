<?php

namespace App\Models;

class ShopFunds extends AppEnvModel
{
    protected $table = 'shop_funds';

    protected $fillable = ['shop_id', 'transaction_no', 'product_type', 'product_name', 'type', 'unit_price', 'quantity', 'total_price','amount', 'date', 'created_at', 'updated_at'];

    public function create($date = '', array $options = []){
        $shop_funds = ShopFunds::where(['shop_id' => $this->shop_id, 'status' => 0])
            ->orderBy('created_at', 'desc')->orderBy('id', 'desc')->first();
        if ($shop_funds) {
            $this->balance = $this->amount + $shop_funds->balance;
        } else {
            $this->balance = $this->amount;
        }
        $this->date = $date ? $date : date('Y-m-d');
        $this->save();
    }


    public static function createFunds($params, $now, $is_handler_settle=true) {
        if(!$now) $now = time();
        $date = date('Y-m-d', $now);
        $shop_funds = new ShopFunds($params);
        $shop_funds->create($date);

        if($is_handler_settle) {
            $shop_id =  $shop_funds->shop_id;
            $where = ['shop_id' => $shop_id, 'status' => 0];
            $query = ShopFunds::where($where)
                ->select('balance')
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc')
                ->first();
            if($query) {
                $amount = $query->balance;
                ShopFundsArrears::createOrUpdateFundsArrears($shop_id, $amount, $now);
            }
        }
    }

    /**
     * 余额
     * @param $shop_id
     * @return mixed
     */
    public static function getBalance($shop_id) {
        $where = ['shop_id' => $shop_id, 'status' => 0];
        return ShopFunds::where($where)->sum('amount');
    }
}

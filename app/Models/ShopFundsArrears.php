<?php

namespace App\Models;

use Exception;
use Illuminate\Support\Facades\DB;

class ShopFundsArrears extends AppEnvModel
{
    protected $table = 'shop_funds_arrears';

    protected $fillable = ['shop_id', 'status', 'start_time', 'max_amount'];

    public static function createOrUpdateFundsArrears($shop_id, $balance, $time) {
        $where = ['shop_id' => $shop_id, 'status' => '1'];
        $shop_funds_arrears = ShopFundsArrears::where($where)->first();
        if(!$time) $time = time();
        if ($balance >= 0) {
            // 存在欠费的情况, 余额大于0, 终止欠费状态
            if ($shop_funds_arrears) {
                $shop_funds_arrears->end_time = $time;
                $shop_funds_arrears->status = 0;
                $shop_funds_arrears->save();
                //如果店铺已关闭则重新开启
                ShopDisable::setShopEnable($shop_id, SHOP_DISABLE_FUNDS_ARREARS);
            }
        } else {
            if (!$shop_funds_arrears) {
                $shop_funds_arrears = new ShopFundsArrears();
                $shop_funds_arrears->shop_id = $shop_id;
                $shop_funds_arrears->status = 1;
                $shop_funds_arrears->start_time = $time;
                $shop_funds_arrears->max_amount = 0;
                //TODO 首次欠费要发送短书提示+通知
            }
            // 记录最大的欠费记录
            $shop_funds_arrears->max_amount = $shop_funds_arrears->max_amount > $balance ? $balance : $shop_funds_arrears->max_amount;
            $shop_funds_arrears->save();
        }
    }

    /**
     * 当前店铺是否欠费状态
     * @param $shop_id
     * @return int
     */
    public static function isFundsArrears($shop_id)
    {
        $where = ['shop_id' => $shop_id, 'status' => '1'];
        $shop_funds_arrears = ShopFundsArrears::where($where)->first();
        return $shop_funds_arrears ? 1 : 0;
    }


    //批量更新
    public static function updateBatch($multipleData = [])
    {
        if (empty($multipleData)) {
            throw new Exception("数据不能为空");
        }
        $tableName = DB::getTablePrefix() . 'shop_funds_arrears'; // 表名
        $firstRow  = current($multipleData);

        $updateColumn = array_keys($firstRow);
        // 默认以id为条件更新，如果没有ID则以第一个字段为条件
        $referenceColumn = isset($firstRow['id']) ? 'id' : current($updateColumn);
        unset($updateColumn[0]);
        // 拼接sql语句
        $updateSql = "UPDATE " . $tableName . " SET ";
        $sets      = [];
        $bindings  = [];
        foreach ($updateColumn as $uColumn) {
            $setSql = "`" . $uColumn . "` = CASE ";
            foreach ($multipleData as $data) {
                $setSql .= "WHEN `" . $referenceColumn . "` = ? THEN ? ";
                $bindings[] = $data[$referenceColumn];
                $bindings[] = $data[$uColumn];
            }
            $setSql .= "ELSE `" . $uColumn . "` END ";
            $sets[] = $setSql;
        }
        $updateSql .= implode(', ', $sets);
        $whereIn   = collect($multipleData)->pluck($referenceColumn)->values()->all();
        $bindings  = array_merge($bindings, $whereIn);
        $whereIn   = rtrim(str_repeat('?,', count($whereIn)), ',');
        $updateSql = rtrim($updateSql, ", ") . " WHERE `" . $referenceColumn . "` IN (" . $whereIn . ")";
        // 传入预处理sql语句和对应绑定数据
        return DB::update($updateSql, $bindings);
    }
}

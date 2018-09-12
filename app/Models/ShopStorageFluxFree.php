<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopStorageFluxFree extends Model
{
    protected $table = 'shop_storage_flux_free';

    public $timestamps = false;

    protected $fillable = ['shop_id', 'value', 'type', 'start_time', 'end_time'];

    public static function createStorageFluxFree($shop_id, $type, $value, $start_time, $end_time){
        if($value > 0) {
            $instance = new ShopStorageFluxFree();
            $instance->shop_id = $shop_id;
            $instance->value = $value;
            $instance->type = $type;
            $instance->start_time = $start_time;
            $instance->end_time = $end_time;
            $instance->save();
        }
    }

    /**
     * @param $shop_id
     * @param $time
     * @return int|mixed
     */
    public static function getStorageFree($shop_id, $time){
        return self::getStorageFluxFree($shop_id, $time, QCOUND_COS);
    }

    /**
     * @param $shop_id
     * @param $time
     * @return int|mixed
     */
    public static function getFluxFree($shop_id, $time){
        return self::getStorageFluxFree($shop_id, $time, QCOUND_CDN);
    }

    /**
     * @param $shop_id
     * @param $time
     * @param $type
     * @return int|mixed
     */
    private static function getStorageFluxFree($shop_id, $time, $type){
        $result = ShopStorageFluxFree::where(['shop_id'=>$shop_id, 'type'=>$type])
            ->where('start_time', '<=', $time)
            ->where('end_time', '>', $time)
            ->first();
        return $result ? $result->value : 0;
    }
}

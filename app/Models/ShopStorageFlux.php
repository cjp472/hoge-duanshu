<?php

namespace App\Models;

class ShopStorageFlux extends AppEnvModel
{
    protected $table = 'shop_storage_flux';

    protected $fillable = ['shop_id', 'value', 'type', 'date', 'source', 'created_at', 'updated_at'];

    public static function createStorageFlux($shop_id, $date, $type, $value){
        if($value > 0) {
            $instance = new ShopStorageFlux();
            $instance->shop_id = $shop_id;
            $instance->value = $value;
            $instance->type = $type;
            $instance->date = $date;
            $instance->save();
        }
    }
}

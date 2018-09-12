<?php

/**
 * Created by PhpStorm.
 * User: Allen
 * Date: 17/12/21
 * Time: 下午4:31
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShortLink extends Model
{
    protected $table = 'ds_admin_shortlink';
    protected $connection = 'djangodb';
    protected $keyType = 'string';
    public $timestamps = false;


    public function create(array $options = []) {
        try {
            $this->key = uuid4();
            self::save($options);
        }catch (QueryException $e){
            $errorInfo = $e->errorInfo;
            if($errorInfo[1] == 1062 && strstr($errorInfo[2], 'key')){
                $this->create($options);
            }
        }
    }
}

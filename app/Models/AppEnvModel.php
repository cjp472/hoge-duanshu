<?php
/**
 * Created by PhpStorm.
 * User: tanqiang
 * Date: 2018/7/6
 * Time: 上午11:10
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppEnvModel extends Model
{
    public function save(array $options = []) {
        $this->source =  getenv('APP_ENV');
        parent::save($options);
    }
}
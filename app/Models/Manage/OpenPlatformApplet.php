<?php
/**
 * Created by PhpStorm.
 * User: zhoujie
 * Date: 2017/8/8
 * Time: 上午9:27
 */

namespace App\Models\Manage;

use Illuminate\Database\Eloquent\Model;

class OpenPlatformApplet extends Model
{
    protected $table = 'open_platform_applet';

    public $timestamps = false;


    public function submitAudit(){
        return $this->hasMany('App\Models\Manage\AppletSubmitAudit','appid','appid');
    }

}

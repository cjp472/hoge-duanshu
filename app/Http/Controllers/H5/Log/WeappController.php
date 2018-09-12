<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2017/4/12
 * Time: 09:14
 */

namespace App\Http\Controllers\H5\Log;

use App\Http\Controllers\H5\BaseController;
use App\Models\WeappPayLog;

class WeappController extends BaseController
{
    public function payError()
    {
        $error_msg = request('error_msg');
        if ($error_msg) {
            $shop_id = $this->shop['id'];
            $weapp_pay_log = new WeappPayLog();
            $weapp_pay_log->error_msg = $error_msg;
            $weapp_pay_log->shop_id = $shop_id;
            $weapp_pay_log->save();
        }
        return $this->output(['success' => 1]);
    }
}
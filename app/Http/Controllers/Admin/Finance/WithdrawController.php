<?php
/**
 * 财务管理-提现管理
 */

namespace App\Http\Controllers\Admin\Finance;

use App\Http\Controllers\Admin\BaseController;

class WithdrawController extends BaseController
{
    public function lists()
    {
        $lists = [
            'page' => [
                'total'         => 65,
                'current_page'  => 1,
                'last_page'     => 6,
            ],
            'data' => [
                [
                    'id'            => 'w_58db6a63cb980',
                    'money'         => '300.00',
                    'account'       => 'Janice',
                    'draw_time'     => '2017-03-11 11:03:47',
                    'finish_time'   => '2017-03-12 16:03:47',
                    'apply_user'    => 'u_58db6a63cb980',
                    'apply_user_name'   => 'Janice',
                    'status'        => 1,
                ],
                [
                    'id'            => 'w_58db6a63cb980',
                    'money'         => '300.00',
                    'account'       => 'Janice',
                    'draw_time'     => '2017-03-29 16:03:47',
                    'finish_time'   => '2017-03-31 16:03:47',
                    'apply_user'    => 'u_58db6a63cb980',
                    'apply_user_name'   => 'Janice',
                    'status'        => 2,
                ],
                [
                    'id'            => 'w_58db6a63cb980',
                    'money'         => '300.00',
                    'account'       => 'Janice',
                    'draw_time'     => '2017-03-25 12:03:47',
                    'finish_time'   => '2017-03-31 16:03:47',
                    'apply_user'    => 'u_58db6a63cb980',
                    'apply_user_name'   => 'Janice',
                    'status'        => 3,
                ],
                [
                    'id'            => 'w_58db6a63cb980',
                    'money'         => '300.00',
                    'account'       => 'Janice',
                    'draw_time'     => '2017-03-25 12:03:47',
                    'finish_time'   => '2017-03-31 16:03:47',
                    'apply_user'    => 'u_58db6a63cb980',
                    'apply_user_name'   => 'Janice',
                    'status'        => 0,
                ]
            ],
        ];
        return $this->output($lists);
    }

    public function account()
    {
        $data = [
            'total' => '1990.00',
        ];
        return $this->output($data);
    }

    public function bingWechat()
    {
        return $this->output([
            'success' => true
        ]);
    }

}
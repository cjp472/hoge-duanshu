<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2017/7/19
 * Time: 15:12
 */

namespace App\Http\Controllers\Sms\Aliyun;

use Mews\Captcha\Captcha;
use Illuminate\Support\Facades\Redis;

class CaptchaController extends Captcha
{
    protected $fontColors = ['#000000'];
    protected $bgColor = '#ffffff';
    protected $length = 4;
    protected $lines = 2;
    protected $angle = 5;
    protected function generate()
    {
        $characters = str_split($this->characters);

        $bag = '';
        for($i = 0; $i < $this->length; $i++)
        {
            $bag .= $characters[rand(0, count($characters) - 1)];
        }

        Redis::rpush('captcha',strtolower($bag));

        return ['value'=>$bag];
    }
}
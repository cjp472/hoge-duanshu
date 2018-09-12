<?php

namespace App\Http\Controllers\Admin\Setting;

use App\Http\Controllers\Admin\BaseController;
use Doctrine\Common\Cache\PredisCache;
use EasyWeChat\Foundation\Application;

class AppletQrcodeController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    // 小程序码
    public function wxaCode()
    {
        $params = $this->validateCode();
        $stream = $this
            ->initProgram()
            ->getAppCode($params['path'], $params['width']);
        return $this->response_png($stream);
    }

    // 无限制小程序码
    public function wxaCodeUnlimit()
    {
        $params = $this->validateCode();
        $stream = $this
            ->initProgram()
            ->getAppCodeUnlimit($params['scene'], $params['width']);
        return $this->response_png($stream);
    }

    // 小程序二维码
    public function wxaQrcode()
    {
        $params = $this->validateCode();
        $stream = $this
            ->initProgram()
            ->createQRCode($params['path'], $params['width']);
        return $this->response_png($stream);
    }

    public function validateCode()
    {
        $this->validateWithAttribute([
            'scene' => 'max:32',
            'path' => 'max:128',
            'width' => 'numeric',
        ], [
            'scene' => '进入小程序的显示页',
            'path' => '进入小程序的显示页',
            'width' => '二维码宽度',
        ]);
        return [
            'scene' => trim(request('scene')) ?: '',
            'path' => trim(request('path')) ?: '',
            'width' => intval(request('width')) ?: '',
        ];
    }

    private function response_png($stream)
    {
        return response($stream, 200, ['Content-type' => 'image/png']);
    }

    private function initProgram()
    {
        $app = new Application(config('wechat'));
        $app->cache = new PredisCache(app('redis')->connection()->client());
        $miniProgram = $app->mini_program;
        return $miniProgram->qrcode;
    }
}

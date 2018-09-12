<?php

namespace App\Http\Controllers\Admin\OpenPlatform\Publics;

use App\Http\Controllers\Admin\BaseController;
use qcloudcos\Cosapi;
use GuzzleHttp\Client;

class QcloudController extends BaseController
{
    public function uploadImg($file_name, $stream, $headers = [])
    {
        $cos_path = config('qcloud.folder') . '/image/' . $file_name;
        $upload_path = resource_path('material/openfaltform/');
        if (!is_dir($upload_path)) {
            mkdir($upload_path, 0777, 1);
        }
        file_put_contents($upload_path . $file_name, $stream);
        Cosapi::setRegion(config('qcloud.region'));
        $data = Cosapi::upload(config('qcloud.cos.bucket'), $upload_path . $file_name, $cos_path, null, null, 0);
        // 更新文件属性
        $headers && Cosapi::update(config('qcloud.cos.bucket'), $cos_path, '', '', $headers);
        $data['code'] && $this->errorWithText($data['code'], $data['message']);
        if(file_exists($upload_path . $file_name)){
            unlink($upload_path . $file_name);
        }
        return $data['data']['source_url'];
    }

    public function uploadUrl($url)
    {
        $file_name = md5($url);
        $cos_path = config('qcloud.folder') . '/image/' . $file_name;
        $upload_path = resource_path('material/openfaltform/'). $file_name;
        try {
            $ret = (new Client(['verify' => false]))->get($url, ['save_to' => $upload_path]);
        } catch (\Exception $e) {
            return $url;
        }
        Cosapi::setRegion(config('qcloud.region'));
        $data = Cosapi::upload(config('qcloud.cos.bucket'), $upload_path, $cos_path, null, null, 0);
        // 更新文件属性
        Cosapi::update(config('qcloud.cos.bucket'), $cos_path, '', '', ['Content-Type' => $ret->getHeaders()['Content-Type'][0]]);
        $data['code'] && $this->errorWithText($data['code'], $data['message']);
        if(file_exists($upload_path)) {
            unlink($upload_path);
        }
        return $data['data']['source_url'];
    }
}

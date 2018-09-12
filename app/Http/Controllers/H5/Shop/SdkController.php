<?php

namespace App\Http\Controllers\H5\Shop;

use App\Models\Sdk;
use App\Models\Shop;
use Illuminate\Encryption\Encrypter;
use App\Http\Controllers\H5\BaseController;

class SdkController extends BaseController
{
    /**
     * 检测sdk状态
    */
    public function check()
    {
        $obj = Sdk::where(['app_id' => request('app_id'),'purpose' => request('purpose')])->first();
        $type = request('type');
        $platform = $obj->platform;
        if(!isset($platform[$type])){
           $this->error('sdk_error_info');
        }
        $param = '';
        switch($type){
            case 'ios':
                $param = 'bundle_id';
                break;
            case 'android':
                $param = 'package';
                break;
        }
        if(request('package') != $platform[$type][$param] || ('android' == $type && strtolower(str_replace(':','',request('sign'))) != strtolower(str_replace(':','',$platform[$type]['sign'])))){
            $this->error('sdk_error_info');
        }

        $version = Shop::where('hashid',$obj->shop_id)->value('version');
        if('partner' != $version){
            $this->error('sdk_error_info');
        }

        $en = new Encrypter($obj->app_secret);
        $data = [
            "response" => [
                'enable' => true,
                'time' => time()
            ]
        ];

        return $en->encrypt(json_encode($data),false);
    }
}
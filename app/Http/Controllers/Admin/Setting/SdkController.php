<?php
namespace App\Http\Controllers\Admin\Setting;

use App\Models\Sdk;
use Vinkla\Hashids\Facades\Hashids;
use App\Http\Controllers\Admin\BaseController;

class SdkController extends BaseController
{
    /**
     * 展示sdk
    */
    public function display()
    {
        $shopId = $this->shop['id'];
        $obj = Sdk::where('shop_id',$shopId)->first();
        return $this->output($obj ? : []);
    }

    /**
     * 申请sdk
    */
    public function apply()
    {
        $shopId = $this->shop['id'];
        $obj = Sdk::where('shop_id',$shopId)->first();
        if($obj){
            $this->error('only_one_sdk');
        }

        $this->validateWithAttribute([
            'name'      => 'required',
            'index_pic'   => 'required',
            'platform' => 'required'
        ],[
            'name'      => '应用名称',
            'index_pic'  => '应用图标',
            'platform' => '平台信息'
        ]);
        $platform = request('platform');
        if(empty($platform['ios']) && empty($platform['android'])){
            $this->error('at_least_one_choice');
        }

        if(isset($platform['ios']) && empty($platform['ios']['bundle_id']) && empty($platform['ios']['test_bundle_id'])){
            $this->error('no_param');
        }

        if(isset($platform['android']) && (empty($platform['android']['sign']) || empty($platform['android']['package']))){
            $this->error('no_param');
        }

        $appId = 'ds'.$shopId;
        $data = [
            'shop_id' => $shopId,
            'name' => request('name'),
            'index_pic' => serialize(request('index_pic')),
            'app_id' => $appId,
            'platform' => serialize(request('platform')),
            'purpose' => 'h5_sdk'
        ];

        $id = Sdk::insertGetId($data);
        $info = Sdk::find($id);
        $appSecret = Hashids::connection('sdk')->encode($id);
        $info->app_secret = $appSecret;
        $info->save();

        return $this->output(['app_id' => $appId,'app_secret' => $appSecret]);
    }

    /**
     * 重置secret
    */
    public function reset()
    {
        $shopId = $this->shop['id'];
        $obj = Sdk::where('shop_id',$shopId)->first();
        if(empty($obj)){
            $this->error('data-not-fond');
        }
        $appSecret = uuid();
        $appId = $obj->app_id;
        $obj->app_secret = $appSecret;
        $obj->save();

        return $this->output(['app_id' => $appId,'app_secret' => $appSecret]);
    }

    /**
     * 更新sdk
    */
    public function edit()
    {
        $shopId = $this->shop['id'];
        $obj = Sdk::where('shop_id',$shopId)->first();
        if(empty($obj)){
            $this->error('data-not-fond');
        }

        $this->validateWithAttribute([
            'name'      => 'required',
            'index_pic'   => 'required',
            'platform' => 'required'
        ],[
            'name'      => '应用名称',
            'index_pic'  => '应用图标',
            'platform' => '平台信息'
        ]);

        $platform = request('platform');
        if(empty($platform['ios']) && empty($platform['android'])){
            $this->error('at_least_one_choice');
        }

        if(isset($platform['ios']) && empty($platform['ios']['bundle_id']) && empty($platform['ios']['test_bundle_id'])){
            $this->error('no_param');
        }

        if(isset($platform['android']) && (empty($platform['android']['sign']) || empty($platform['android']['package']))){
            $this->error('no_param');
        }

        $obj->name = request('name');
        $obj->index_pic = serialize(request('index_pic'));
        $obj->platform = serialize(request('platform'));
        $obj->save();

        return $this->output(['success' => 1]);
    }

    /**
     * sdk信息删除
    */
    public function delete()
    {
        Sdk::where('shop_id',$this->shop['id'])->delete();
        return $this->output(['success'=>1]);
    }
}
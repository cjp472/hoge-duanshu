<?php
namespace App\Http\Controllers\Admin\Setting;

use App\Models\Shop;
use App\Models\Protocol;
use App\Jobs\SyncProtocol;
use App\Models\ShopProtocol;
use App\Http\Controllers\Admin\BaseController;

class OrderProtocolController extends BaseController
{
    const PAGINATE = 20;
    private $version = [
        VERSION_STANDARD => '基础版',
        VERSION_ADVANCED => '高级版',
        VERSION_PARTNER => '合伙人版'
    ];

    private $uppercase_money = [
        2800 => '贰仟捌佰元',
        5600 => '伍仟陆佰元',
        9800 => '玖仟捌佰元'
    ];

    /**
     * 订购协议下载
    */
    public function export()
    {
        $this->validateWithAttribute([
            'id' => 'required'
        ], [
            'id' => 'id'
        ]);

        $shopId = $this->shop['id'];
        $version = Shop::where('hashid',$shopId)->value('version');
        if(VERSION_BASIC == $version){
            $this->error('low_version',['attributes'=>'订购协议']);
        }
        $obj = ShopProtocol::find(request('id'));
        if(empty($obj) || $obj->shop_id != $shopId){
            $this->error('data-not-fond');
        }
        $date = $obj->create_time;
        $content = $obj->content;
        if(empty($content['name'])){
            $this->error('no-verity-info');
        }
        $data = [
            $content['name'],
            intval(substr($date,0,4)),
            intval(substr($date,5,2)),
            intval(substr($date,8,2)),
            $this->uppercase_money[$content['price']],
            $content['price'],
            $this->version[$version],
            $content['time']
        ];
        $string = Protocol::where('id',$obj->p_id)->value('content');
        $string = str_replace(['{{$name}}','{{$signyear}}','{{$signmonth}}','{{$signdate}}','{{$up_money}}','{{$money}}','{{$version}}','{{$year}}'],$data,$string);
        $pdf = new \TCPDF();
        $pdf->SetTitle('“短书”软件服务订购协议');
        $pdf->AddPage();
        $pdf->setFontSubsetting(true);
        //设置字体 stsongstdlight支持中文
        $pdf->SetFont('stsongstdlight', '', 14);
        $pdf->writeHTML($string);
        $pdf->Output('protocol.pdf', true);
    }

    public function protocolStatus()
    {
        $this->validateWithAttribute([
            'id' => 'required',
            'status' => 'required|in:1,2,3',
        ], [
            'id' => 'id',
            'status' => '状态'
        ]);
        $obj = ShopProtocol::find(request('id'));
        if(empty($obj)){
            $this->error('data-not-fond');
        }
        if(2 == $obj->status){
            return $this->output(['success'=>1]);
        }

        $obj->status = request('status');
        $obj->save();

        return $this->output(['success'=>1]);
    }

    public function protocolDetail()
    {
        $obj = ShopProtocol::find(request('id'));
        if(empty($obj)){
            $this->error('data-not-fond');
        }
        $content = $obj->content;
        if(empty($content['name'])){
            $verify = $this->verfityInfo($obj->shop_id);
            if('success' == $verify['status']){
                $content['name'] = $verify['name'];
                $obj->content = serialize($content);
                $obj->save();
            }
            $obj->verify_status = $verify['status'];
        }else{
            $obj->verify_status = 'success';
        }
        $obj->content = serialize($content);
//        $pro = Protocol::find($obj->p_id);
//        $obj->protocol = $pro->content;
        $obj->version = Shop::where('hashid',$obj->shop_id)->value('version');;
        return $this->output($obj);
    }

    /**
     * 协议列表
    */
    public function lists()
    {
        $shopId = $this->shop['id'];
        $count = request('count') ? intval(request('count')) : self::PAGINATE;
        $obj = ShopProtocol::where('shop_id',$shopId)->paginate($count);
        $info = $this->listToPage($obj);
        $data = $info['data'];
        if(!empty($data)){
            foreach($data as $obj){
                $content = $obj->content;
                if(empty($content['name']) || 1 == $obj->status){
                    $verify = $this->verfityInfo($obj->shop_id);
                    if('success' == $verify['status']){
                        if('personal' == $verify['verify_first_type']){
                            $content['name'] = $verify['name'];
                        }elseif(in_array($verify['verify_first_type'],['enterprise','commonweal'])){
                            $content['name'] = $verify['organization'];
                        }
                        $obj->content = serialize($content);
                        $obj->save();
                    }
                    $obj->verify_status = $verify['status'];
                }else{
                    $obj->verify_status = 'success';
                }
                $obj->content = serialize($content);
            }
        }
        return $this->output($data);
    }

    /**
     * 认证信息
    */
    private function verfityInfo($shopId)
    {
        $info = ['user'=>$shopId];
        $data = ['uid'=>$info['user']];
        $client = hg_verify_signature($data,'','','',$shopId); //初始化 client
        $url = config('define.order_center.api.verify_detail');
        $res = $client->request('GET',$url,['query'=>$data]);
        $data = json_decode($res->getBody()->getContents(),1);
        if($res->getStatusCode() !== 200){
            $this->error('error-sync-order');
        }
        if($res && $data['error_code'] && $data['error_code']!=6109){
            $this->errorWithText(
                'error-sync-order-'.$data['error_code'],
                $data['error_message']
            );
        }elseif($data['error_code']==6109){
            return ['status'=>'none'];
        }
        return $data['result'];
    }

    /**
     * 脚本
    */
    public function job()
    {
        $shopIds = Shop::select('hashid')->where(['status'=>0,'version'=>'advanced'])->get();
        if(!$shopIds->isEmpty()){
            foreach($shopIds as $shopId){
                dispatch(new SyncProtocol($shopId->hashid));
            }
        }
    }
}
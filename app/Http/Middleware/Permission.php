<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use App\Models\Shop;

class Permission
{
    protected $dontCheckRoute = [
        'todaySituation',
        'incomeTotal',
        'materialList',
        'materialCreate',
        'materialUpdate',
        'materialDetail',
        'materialDelete',
        'materialTop',
        'materialStatus',
        'signatureCos',
        'videoSignture',
        'bindWechat',
        'accountDetail',
        'accountInfo',
        'accountSet',
        'shareDetail',
        'shareUpdate',
        'goodsList',
        'aliveStatistics',
        'shopVersion',
        'partnerActive',
        'systemNoticeList',
        'problemStatus',
        'livePattern',
        'praiseStatus',
        'mobileBind',
        'verifyCode',
        'verifyDetail',
        'verifyStatus',
        'updatePassword',
        'videoNewSignature',
        'colorLists',
        'chooseAppletColor',
        'announceStatus',
        'systemNoticeList',
        'systemNoticeDetail',
        'systemNoticeUpdate',
        'systemNoticeNotRead',
        'setButtonClicks',
        'getShopPrivate',
        'helpList',
        'appletFastLogin',
        
        'uploadCertificate',
        'closeAppletRefund',
        'xzLogin',
        'syncPromotionRecord',
        'navigationContents',

        'noticeBannerLists',
        'shopBillSummaryList',
        'shopDateBillDetail',
        'shopDateBillList',
        'shopFundsBalance',
        'shopBillExport',
        'shopBillTest',
        'shopStorageExport',
        'shopFluxExport',
        'shopIndex',

        'loginUser',
    ];

    public function handle($request, Closure $next)
    {
        $shop = Auth::id() ? Session::get('shop:'.Auth::id()) : [];
        if(!$shop){
            return response([
                'error'     => 'no-login',
                'message'   => trans('validation.no-login'),
            ]);
        }
        $shop_data = Shop::where(['hashid' => $shop['id']])->first();
        $shop['version'] = $shop_data->version;
        if(!$this->dontCheckRoute()){
            $sign = explode('/',$request->getPathInfo());
            $mark = $sign[2];
            switch ($sign[2]){
                case 'user' :
                    $mark = 'member';
                    break;
                case 'code':
                    if($shop['version']==VERSION_BASIC && isset($sign[4]) && $sign[4]=='share'){
                        $mark = 'code_share';
                    }else{
                        $mark = 'invitation';
                    }
                    break;
                case 'shop':
                    if($sign[3] == 'index'){
                        $mark = 'interface';
                    }
                    if($sign[3] == 'update'){
                        $mark = 'shop_update';
                    }
                    if($sign[3] == 'analysis'){
                        if($sign[4] == 'user') {
                            $mark = 'member_analysis';
                        }
                        if($sign[4] == 'content') {
                            $mark = 'content_analysis';
                        }
                        if($sign[4] == 'order') {
                            $mark = 'order_analysis';
                        }
                        if($sign[4] == 'income' || $sign[4] == 'high'|| $sign[4] == 'shop') {
                            $mark = 'income_analysis';
                        }
                    }
                    if($sign[3] == 'navigation'){
                        $mark = 'navigation';
                    }
                    if($sign[3] == 'applet'){
                        $mark = 'applet';
                    }
                    if($sign[3] == 'color'){
                        $mark = 'color';
                    }
                    if($sign[3] == 'score') {
                        $mark = 'score';
                    }
                    if($sign[3] == 'message'){
                        $mark = 'message';
                    }
                    if($sign[3] == 'class') {
                        $mark = 'class';
                    }
                    if($sign[3] == 'sdk') {
                        $mark = 'sdk';
                    }
                    if($sign[3] == 'protocol') {
                        $mark = 'protocol';
                    }
                    if($sign[3] == 'info'){
                        $mark = 'info';
                    }
                    break;
                case 'center':
                    $mark = 'order';
                    break;
                case 'notice':
                case 'feedback':
                    $mark = 'message';
                    break;
                case 'role':
                    $mark = 'roleManage';
                    break;
                case 'content':
                    if($sign[3] == 'column'){
                        $mark = 'column';
                    } elseif($sign[3] == 'course'){
                        $mark = 'course';
                    }
                    if($sign[3] == 'remind'){
                        $mark = 'remind';
                    }
                    break;
                case 'openplatform':case 'wxApplet':
                    $mark = 'open_platform';
                    break;
                default:
                    $mark = $sign[2];
                    break;
            }
            if($mark && !in_array($mark,config('define.'.$shop['version']))){
                return response([
                    'error'     => 'low_version',
                    'message'   => trans('validation.low_version',[
                        'attributes'   => config('define.permission.'.$mark)
                    ]),
                ]);
            }
            //前端限制子账号的权限 这里暂时注释掉
//            if(!$shop['admin'] && $mark && is_array($shop['permission']) && !in_array($mark,$shop['permission'])) {
//                return response([
//                    'error'     => 'no-permission',
//                    'message'   => trans('validation.no-permission',[
//                        'attributes'   => config('define.permission.'.$mark)
//                    ]),
//                ]);
//            }
        }
        $response = $next($request);
        return $response;
    }

    private function dontCheckRoute()
    {
        return in_array(Route::currentRouteName(),$this->dontCheckRoute) ? 1 : 0;
    }
}
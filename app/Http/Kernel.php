<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array
     */
    protected $middleware = [
        \Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode::class,
        \Barryvdh\Cors\HandleCors::class,
        \App\Http\Middleware\OAuthExceptionMiddleware::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
        ],

        'api' => [
            'throttle:60,1',
        ],
    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array
     */
    protected $routeMiddleware = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'can' => \Illuminate\Foundation\Http\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'shop' => \App\Http\Middleware\Shop::class,
        'member.check' => \App\Http\Middleware\H5\MemberCheck::class,
        'shop.h5.check' => \App\Http\Middleware\H5\ShopCheck::class,
        'pay.check' => \App\Http\Middleware\H5\CheckPayment::class,
        'h5.log' => \App\Http\Middleware\H5\HandleLogs::class,
        'check.pay.signature' => \App\Http\Middleware\checkPaySignature::class,
        'log' => \App\Http\Middleware\OperationLogs::class,
        'check.user'        => \App\Http\Middleware\H5\UserCheck::class,
        'permission.check' => \App\Http\Middleware\Permission::class,
        'check.sms.signature'   => \App\Http\Middleware\SmsSignature::class,
        'oauth' => \LucaDegasperi\OAuth2Server\Middleware\OAuthMiddleware::class,
        'oauth-user' => \LucaDegasperi\OAuth2Server\Middleware\OAuthUserOwnerMiddleware::class,
        'oauth-client' => \LucaDegasperi\OAuth2Server\Middleware\OAuthClientOwnerMiddleware::class,
        'check-authorization-params' => \LucaDegasperi\OAuth2Server\Middleware\CheckAuthCodeRequestMiddleware::class,
        'check.service.signature'   => \App\Http\Middleware\CheckServiceSignature::class,
        'check.python.signature'   => \App\Http\Middleware\CheckPythonSignature::class,
        'admin.signature' => \App\Http\Middleware\AdminSignature::class,
        'applet' => \App\Http\Middleware\H5\WechatApplet::class,
        'admin.logs' => \App\Http\Middleware\AdminLogs::class,
        'course.check.pay' => \App\Http\Middleware\H5\CheckCoursePayment::class,
        'app.sign' => \App\Http\Middleware\App\CheckSignature::class,
        'permission.manage' => \App\Http\Middleware\ManagePermission::class,
        'm2oCloud' => \App\Http\Middleware\M2OCloudMiddleware::class,
        'check.wechat.callback' => \App\Http\Middleware\CheckWechatCallback::class,
        'check.applet.audit' => \App\Http\Middleware\CheckAppletAudit::class,
        'check.column.pay' => \App\Http\Middleware\H5\CheckColumnPayment::class,
        'promotion.shop' => \App\Http\Middleware\PromotionShop::class,
        'h5.promotion.shop' => \App\Http\Middleware\H5\PromotionCheck::class,
        'api.check'         => \App\Http\Middleware\ApiCheckMiddleware::class,
        'check.join.community' => \App\Http\Middleware\H5\CheckJoinCommunity::class,
        'check.community.manage'=> \App\Http\Middleware\H5\CheckCommunityManage::class,
        'sdk.valid' => \App\Http\Middleware\SdkSignature::class,
        'role' => \Zizaco\Entrust\Middleware\EntrustRole::class,
        'permission' => \Zizaco\Entrust\Middleware\EntrustPermission::class,
        'ability' => \Zizaco\Entrust\Middleware\EntrustAbility::class,
        'shop.verify'=> \App\Http\Middleware\ShopVerify::class,
        'client.api.sign'=> \App\Http\Middleware\ClientApiCheckMiddleware::class,
    ];
}

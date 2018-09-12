<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        'App\Events\CommentEvent' => [
            'App\Listeners\CommentListener',
        ],
        'App\Events\Registered' => [
            'App\Listeners\RegisterShop',
            'App\Listeners\TrackRegistSeo',
            'App\Listeners\Log\LogRegisteredUser',
        ],
        'Illuminate\Auth\Events\Login' => [
            'App\Listeners\Log\LogSuccessfulLogin',
        ],
        'Illuminate\Auth\Events\Logout' => [
            'App\Listeners\Log\LogSuccessfulLogout',
        ],
        'App\Events\ContentViewEvent' => [
            'App\Listeners\ContentView',
        ],
        'App\Events\NoticeEvent' => [
            'App\Listeners\SendNotice',
        ],
        'App\Events\SubscribeEvent' => [
            'App\Listeners\AddSubscribers',
        ],
        'App\Events\PayEvent' => [
            'App\Listeners\SumConsume',
            'App\Listeners\JoinLiveChatGroup'
        ],
        'App\Events\InteractNotifyEvent' => [
            'App\Listeners\InteractNotify',
        ],
        'App\Events\OperationEvent' => [
            'App\Listeners\Log\LogOperationed',
        ],
        'App\Events\ErrorHandle' => [
            'App\Listeners\Log\LogError',
        ],
        'App\Events\AudioTranscodeEvent' => [
            'App\Listeners\AudioTranscode',
        ],
        'App\Events\OrderMakeEvent' => [
            'App\Listeners\SyncOrderToOrderCenter',
        ],
        'App\Events\CurlLogsEvent' => [
            'App\Listeners\Log\LogCurl',
        ],
        'App\Events\OrderStatusEvent' => [
            'App\Listeners\OrderStatus',
        ],
        'App\Events\SystemEvent' => [
            'App\Listeners\SystemNotify',
        ],
        'App\Events\H5LogsEvent' => [
            'App\Listeners\Log\H5LogOperationed',
        ],
        'App\Events\AdminLogsEvent' => [
             'App\Listeners\Log\AdminLogs',
        ],
        'App\Events\AdmireOrderEvent' => [
            'App\Listeners\AdmireOrderToOrderCenter',
        ],
        'App\Events\AdmireEvent' => [
            'App\Listeners\SendLiveAdmireMessageToChatGroup',
        ],
        'App\Events\PromoterRecordEvent' => [
            'App\Listeners\SyncPromoterRecord',
        ],
        'App\Events\AppEvent\AppContentEvent' => [
             'App\Listeners\App\ContentCreateOrUpdate',
        ],
        'App\Events\AppEvent\AppColumnEvent' => [
             'App\Listeners\App\ColumnCreateOrUpdate',
        ],
        'App\Events\AppEvent\AppMemberEvent' => [
             'App\Listeners\App\MemberSync',
        ],
        'App\Events\AppEvent\AppCommentEvent'  => [
             'App\Listeners\App\CommentDelete'
        ],
        'App\Events\AppEvent\AppContentDeleteEvent'  => [
             'App\Listeners\App\ContentDelete'
        ],
        'App\Events\AppEvent\AppCommentAddEvent'  => [
            'App\Listeners\App\CommentAdd'
        ],
        'App\Events\AppEvent\AppSyncFailEvent'  => [
            'App\Listeners\App\SyncFail'
        ],
        'App\Events\AppEvent\AppTypeAddEvent'  => [
            'App\Listeners\App\TypeAdd'
        ],
        'App\Events\AppEvent\AppTypeDeleteEvent'  => [
            'App\Listeners\App\TypeDelete'
        ],
        'App\Events\AppEvent\AppBannerAddEvent'  => [
            'App\Listeners\App\BannerAdd'
        ],
        'App\Events\AppEvent\AppBannerDeleteEvent'  => [
            'App\Listeners\App\BannerDelete'
        ],
        'App\Events\Content\CreateEvent'  => [
            'App\Listeners\Content\CreateListener'
        ],
        'App\Events\Content\EditEvent'  => [
            'App\Listeners\Content\EditListener'
        ],
        'App\Events\Content\DeleteEvent'  => [
            'App\Listeners\Content\DeleteListener'
        ],
        'App\Events\CreateCardRecord'  => [
            'App\Listeners\AddCardRecord'
        ],
        'App\Events\CreateWechatArticleEvent'  => [
            'App\Listeners\CreateWechatArticle'
        ],
        'App\Events\AppletNoticeEvent' => [
            'App\Listeners\AppletNotify'
        ],
        'App\Events\PintuanGroupEvent' => [
            'App\Listeners\PintuanGroup'
        ],
        'App\Events\PintuanPaymentEvent' => [
            'App\Listeners\PintuanPayment'
        ],
        'App\Events\PintuanRefundsEvent' => [
            'App\Listeners\PintuanRefunds'
        ],
        'App\Events\JoinCommunityEvent'  => [
            'App\Listeners\JoinCommunity'
        ],
        'App\Events\Content\StaticsEvent' => [
            'App\Listeners\Content\StaticsListener'
        ],
        'App\Events\SendMessageEvent' => [
            'App\Listeners\SendMessage'
            ],
        'App\Events\PintuanRefundsRequestEvent' => [
            'App\Listeners\PintuanRefundsRequest'
        ],
        'App\Events\PintuanRefundsPassEvent' => [
            'App\Listeners\PintuanRefundsPass'
        ],
        'App\Events\ClearMaterial' => [
            'App\Listeners\ClearMaterial'
        ],
        'App\Events\SettlementEvent' => [
            'App\Listeners\Settlement'
            ],
        'App\Events\PushTemplateEvent' => [
            'App\Listeners\PushTemplate'
        ],
        'App\Events\SalesTotalEvent' => [
            'App\Listeners\SalesTotalListener',
        ],
        'App\Events\CourseMaterialViewEvent'  => [
            'App\Listeners\CourseMaterialView'
        ],
    ];

    /**
     * Register any other events for your application.
     *
     * @param  \Illuminate\Contracts\Events\Dispatcher  $events
     * @return void
     */
    public function boot()
    {
        parent::boot();
    }
}

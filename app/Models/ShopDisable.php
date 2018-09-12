<?php

namespace App\Models;

use App\Events\SystemEvent;

class ShopDisable extends AppEnvModel
{
    protected $table = 'shop_disable';

    private static $NOTICE_TYPE = [
        SHOP_DISABLE_FUNDS_ARREARS => [
            'title' => 'notice.title.shop_disable',
            'content' => 'notice.content.score.shop_disable_funds_arrears',
        ],
        SHOP_DISABLE_BASIC_EXPIRE => [
            'title' => 'notice.title.shop_disable',
            'content' => 'notice.content.verify.shop_disable_basic_expire',
        ],
        SHOP_DISABLE_BASIC_TEST_EXPIRE => [
            'title' => 'notice.title.shop_disable',
            'content' => 'notice.content.verify.shop_disable_basic_test_expire',
        ],
        SHOP_DISABLE_STANDARD_EXPIRE => [
            'title' => 'notice.title.shop_disable',
            'content' => 'notice.content.shop_disable_standard_expire',
        ],
        SHOP_DISABLE_ADVANCED_EXPIRE => [
            'title' => 'notice.title.shop_disable',
            'content' => 'notice.content.shop_disable_advanced_expire',
        ],
    ];

    protected $fillable = ['shop_id', 'disable', 'type', 'source', 'created_at', 'updated_at'];

    /**
     *
     * 店铺是否关闭
     *
     * @param $shop_id
     * @return int
     */
    public static function isShopDisable($shop_id)
    {
        $where = ['shop_id' => $shop_id, 'disable' => 1];
        $shop_disable = ShopDisable::where($where)->first();
        return $shop_disable ? 1 : 0;
    }

    /**
     * 关闭店铺
     * @param $shop_id
     * @param $type
     */
    public static function createOrDoNotExistShopDisable($shop_id, $type)
    {
        $where = ['shop_id' => $shop_id, 'type'=>$type, 'disable' => 1];
        $count = ShopDisable::where($where)->count();
        if ($count == 0) {
            $shop_disable = new ShopDisable();
            $shop_disable->shop_id = $shop_id;
            $shop_disable->disable = 1;
            $shop_disable->type = $type;
            $shop_disable->save();

            self::handlerSystemNotice($shop_id, $type);
        }
    }

    /**
     * 购买了高级版本 因基础版本到期、基础试用版到期、高级版到期关店重新开启
     * @param $shop_id
     */
    public static function shopUpgradeAdvanced($shop_id){
        self::setShopEnable($shop_id, SHOP_DISABLE_ADVANCED_EXPIRE);
        self::setShopEnable($shop_id, SHOP_DISABLE_BASIC_TEST_EXPIRE);
        self::setShopEnable($shop_id, SHOP_DISABLE_BASIC_EXPIRE);
        self::setShopEnable($shop_id, SHOP_DISABLE_STANDARD_EXPIRE);
        self::setShopEnable($shop_id, SHOP_DISABLE_PARTNER_EXPIRE);
        self::setShopEnable($shop_id, SHOP_DISABLE_FUNDS_ARREARS);
    }

    /**
     * 设置因到期店铺关闭重新开启
     * @param $shop_id
     */
    public static function setShopExpireEnable($shop_id){
        self::setShopEnable($shop_id, SHOP_DISABLE_ADVANCED_EXPIRE);
        self::setShopEnable($shop_id, SHOP_DISABLE_BASIC_TEST_EXPIRE);
        self::setShopEnable($shop_id, SHOP_DISABLE_BASIC_EXPIRE);
        self::setShopEnable($shop_id, SHOP_DISABLE_STANDARD_EXPIRE);
        self::setShopEnable($shop_id, SHOP_DISABLE_PARTNER_EXPIRE);
    }

    /**
     * 店铺购买认证服务 因基础版本到期、基础试用版到期重新开启
     * @param $shop_id
     */
    public static function shopVerifyPass($shop_id){
        self::setShopEnable($shop_id, SHOP_DISABLE_BASIC_TEST_EXPIRE);
        self::setShopEnable($shop_id, SHOP_DISABLE_BASIC_EXPIRE);
    }

    /**
     * 店铺重新开启
     * @param $shop_id
     * @param $type
     */
    public static function setShopEnable($shop_id, $type)
    {
        $where = ['shop_id' => $shop_id, 'type'=>$type, 'disable' => 1];
        $shop_disable = ShopDisable::where($where)->first();
        if ($shop_disable) {
            $shop_disable->disable = 0;
            $shop_disable->save();
        }

        ShopNotice::setShopNoticeCancel($shop_id, $type);
    }

    /**
     * 发送通知
     * @param $shop_id
     * @param $type
     */
    private static function handlerSystemNotice($shop_id, $type)
    {
        $notice_type = array_key_exists($type, self::$NOTICE_TYPE) ? self::$NOTICE_TYPE[$type] : null;
        if ($notice_type) {
            SystemNotice::sendShopSystemNotice($shop_id, $notice_type['title'], $notice_type['content']);
            ShopNotice::createShopNotice($shop_id, $type, trans($notice_type['content']));
        }
    }
}

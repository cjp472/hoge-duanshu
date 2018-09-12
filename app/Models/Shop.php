<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Model;
use App\Models\AppletRelease;
use App\Models\OpenPlatformApplet;
use App\Events\SystemEvent;
use Illuminate\Support\Facades\DB;

class Shop extends Model
{
    protected $table = 'shop';
    public $timestamps = false;

    public function info()
    {
        return $this->hasOne('App\Models\ShopInfo', 'shop_id', 'hashid');
    }

    public function indexpic()
    {
        $shopInfo = ShopInfo::where('shop_id',$this->hashid)->first();
        if ($shopInfo) {
            $indexpics = unserialize($shopInfo->indexpic);
            if (is_array($indexpics) && count($indexpics) > 0) {
                return unserialize($indexpics[0]);
            } else {
                return '';
            }
        } else {
            return '';
        }
    }

    public function promotion_setting(){
        return $this->hasOne('App\Models\PromotionShop','shop_id','hashid');
    }

    public function appletAuthInfo()
    {
        $open_platform_applet = OpenPlatformApplet::where('shop_id', $this->hashid)->first();
        return $open_platform_applet;
    }

    public function isAppletAuthed()
    {
        return boolVal($this->appletAuthInfo());
    }

    public function isAppletReleased()
    {
        $appletAuthInfo = $this->appletAuthInfo();
        $isAppletAuthed = $this->isAppletAuthed();
        if (!$isAppletAuthed) {
            return false;
        };
        $where = ['shop_id' => $this->hashid, 'appid' => $appletAuthInfo->appid];
        $release = AppletRelease::where($where)->orderBy(
            'release_time',
            'desc'
        )->first();
        return $release && $release->release_time;
    }
    //批量更新
    public static function updateBatch($multipleData = [])
    {
        try {
            if (empty($multipleData)) {
                throw new Exception("数据不能为空");
            }
            $tableName = DB::getTablePrefix() . 'shop'; // 表名
            $firstRow = current($multipleData);

            $updateColumn = array_keys($firstRow);
            // 默认以id为条件更新，如果没有ID则以第一个字段为条件
            $referenceColumn = isset($firstRow['id']) ? 'id' : current($updateColumn);
            unset($updateColumn[0]);
            // 拼接sql语句
            $updateSql = "UPDATE " . $tableName . " SET ";
            $sets = [];
            $bindings = [];
            foreach ($updateColumn as $uColumn) {
                $setSql = "`" . $uColumn . "` = CASE ";
                foreach ($multipleData as $data) {
                    $setSql .= "WHEN `" . $referenceColumn . "` = ? THEN ? ";
                    $bindings[] = $data[$referenceColumn];
                    $bindings[] = $data[$uColumn];
                }
                $setSql .= "ELSE `" . $uColumn . "` END ";
                $sets[] = $setSql;
            }
            $updateSql .= implode(', ', $sets);
            $whereIn = collect($multipleData)->pluck($referenceColumn)->values()->all();
            $bindings = array_merge($bindings, $whereIn);
            $whereIn = rtrim(str_repeat('?,', count($whereIn)), ',');
            $updateSql = rtrim($updateSql, ", ") . " WHERE `" . $referenceColumn . "` IN (" . $whereIn . ")";
            // 传入预处理sql语句和对应绑定数据
            return DB::update($updateSql, $bindings);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 店铺当前月份存储流量余额
     * @return array
     */
    public function getStorageFluxBalance($now)
    {
        //流量存储的时间往前推一天
        $now -= 24 * 60 * 60;
        $start_time = date('Y-m-01 00:00:00', $now);
        $time = date_add(date_create($start_time), date_interval_create_from_date_string('1 months'));
        $start_time = date('Y-m-01', $now);
        $end_time = date_format($time, 'Y-m-d');
        $last_time = date_format(date_add($time, date_interval_create_from_date_string('-1days')), 'Y-m-d');
        $shop_id = $this->hashid;
        $storage_free = ShopStorageFluxFree::getStorageFree($shop_id, $now);
        $flux_free = ShopStorageFluxFree::getFluxFree($shop_id, $now);
        //每月免费存储
//        $shop_storage_default = $this->storage;
        $shop_storage_default = $storage_free;
        //每月免费流量
//        $shop_flux_default = $this->flow;
        $shop_flux_default = $flux_free;
        //存储余额
        $storage_result = $this->getStorageFluxStatistic(
            $shop_id,
            $start_time,
            $end_time,
            $shop_storage_default,
            'qcloud_cos'
        );
        //流量余额
        $flux_result = $this->getStorageFluxStatistic(
            $shop_id,
            $start_time,
            $end_time,
            $shop_flux_default,
            'qcloud_cdn'
        );
        $result = [
            'start_time' => $start_time,
            'end_time' => $last_time,
            'storage' => $shop_storage_default,
            'flow' => $shop_flux_default,
            'storage_allow' => $storage_result['allow'],
            'storage_percent' => $storage_result['percent'],
            'flux_allow' => $flux_result['allow'],
            'flux_percent' => $flux_result['percent'],
        ];
        return $result;
    }

    /**
     * @param $shop_id
     * @param $start_time
     * @param $end_time
     * @param $default
     * @param $type
     * @return array
     */
    private function getStorageFluxStatistic($shop_id, $start_time, $end_time, $default, $type)
    {
        $where = ['shop_id' => $shop_id, 'type' => $type];
        $total = ShopStorageFlux::where($where)
            ->where('date', '>=', $start_time)
            ->where('date', '<', $end_time)
            ->sum('value');
        $result = ['percent' => 0, 'allow' => 0];
        if ($default && (($default - intval($total)) > 0)) {
            $result['percent'] = ($default - intval($total)) / $default;
            $result['allow'] = ($default - intval($total));
        }
        return $result;
    }

    /**
     * 店铺当前月份存储流量记录
     * @return array
     */
    public function getStorageFluxList($now, $type)
    {
        //统计时间减少1天
        $now = $now - 24 * 60 * 60;
        $start_time = date('Y-m-01 00:00:00', $now);
        $time = date_add(date_create($start_time), date_interval_create_from_date_string('1 months'));
        $start_time = date('Y-m-01', $now);
        $end_time = date_format($time, 'Y-m-d');
        $shop_id = $this->hashid;
        $where = ['shop_id' => $shop_id, 'type' => $type];
        $query_set = ShopStorageFlux::where($where)
            ->where('date', '>=', $start_time)
            ->where('date', '<', $end_time)
            ->get();
        return $query_set;
    }


    /**
     * 存储流量结算
     * @param $date
     * @param $storage
     * @param $flux
     */
    public function settlementStorageFlux($date, $storage, $flux, $now)
    {
        //余额
        $balance = $this->getStorageFluxBalance($now);
        //存储余额
        $storage_allow = $balance['storage_allow'];
        //流量余额
        $flux_allow = $balance['flux_allow'];
        $db = app('db');
        //开启事务，如果扣费的某一个环节出错，则整个流程需要重新执行
        $db->beginTransaction();
        $flag = $this->settlementStorageFluxItem(QCOUND_COS, $date, $storage_allow, $storage, $now);
        if (!$flag) {
            //扣费失败, 延迟重试
            $db->rollback();
            return;
        }
        $flag = $this->settlementStorageFluxItem(QCOUND_CDN, $date, $flux_allow, $flux, $now);
        if (!$flag) {
            //扣费失败, 延迟重试
            $db->rollback();
            return;
        }
        //提交数据
        $db->commit();
        //结算后处理
        if ($this->version == VERSION_BASIC) {
            //基础版才需要结算处理
            $this->handleAfterSettleStorageFlux($now);
        }

    }

    /**
     * @param $type
     * @param $date
     * @param $allow
     * @param $value
     * @return bool
     */
    private function settlementStorageFluxItem($type, $date, $allow, $value, $now)
    {
        if ($value > 0) {
            $shop_id = $this->hashid;
            //基础版才需要结算
            if ($value > $allow && $this->version == VERSION_BASIC) {
                //使用超出余额部分
                $exceed = $value - $allow;
                //KB转成GB
                $exceed = $exceed / 1048576;
                //不足0.0001GB不收取费用
                if ($exceed >= 0.0001) {
                    $unit_price = 0;
                    $product_type = '';
                    $product_name = '';
                    if ($type == QCOUND_COS) {
                        $unit_price = DEFAULT_QCLOUD_COS_UNIT_PRICE;
                        $product_type = QCOUND_COS;
                        $product_name = QCOUND_COS_NAME;
                    } else if ($type == QCOUND_CDN) {
                        $unit_price = DEFAULT_QCLOUD_CDN_UNIT_PRICE;
                        $product_type = QCOUND_CDN;
                        $product_name = QCOUND_CDN_NAME;
                    }
                    $price = ceil($unit_price * $exceed);
                    //服务商城接口单位是元
//                    $price_value = $price / 100;
                    $order_id = time() . mt_rand(111111, 999999);
//                    $data = ['serial_number' => $order_id, 'value' => '-' . $price_value, 'brief' => '代币消费'];
//                    $client = $this->initClient($this->shop, $data);//生成签名
//                    $url = config('define.service_store.api.score_manage');
                    //请求扣费
//                    try {
//                        $res = $client->request('post', $url, $data);
//                        event(new CurlLogsEvent($res, $client, $url));
//                        if($res->error_code == 0){
//                            $result = $res->result;
                    $param = [
                        'shop_id' => $shop_id,
                        'transaction_no' => $order_id,
                        'product_type' => $product_type,
                        'product_name' => $product_name,
                        'type' => FUNDS_EXPAND,
                        'unit_price' => -$unit_price,
                        'quantity' => $exceed,
                        'total_price' => -$price,
                        'amount' => -$price,
                    ];
                    var_dump($param);
                            //消费记录
                    ShopFunds::createFunds($param, $now, false);
//                        }else{
//                            //扣费失败, 延迟重试
//                            return false;
//                        }
//                    } catch (\Exception $exception) {
//                        //扣费失败, 延迟重试
//                        return false;
//                    }
                }
            }
            //使用记录
            ShopStorageFlux::createStorageFlux($shop_id, $date, $type, $value);
        }
        return true;
    }

    private function initClient($shop_id, $data)
    {
        $appId = config('define.service_store.app_id');
        $appSecret = config('define.service_store.app_secret');
        $timesTamp = time();
        $client = hg_verify_signature($data, $timesTamp, $appId, $appSecret, $shop_id);
        return $client;
    }

    /**
     * 结算后处理
     */
    private function handleAfterSettleStorageFlux($now)
    {
        $shop_id = $this->hashid;
        //结算后余额
        $balance = $this->getStorageFluxBalance($now);
        //免费存储空间
        $shop_storage_default = $balance['storage'];
        //免费流量
        $shop_flux_default = $balance['flow'];
        //存储余额
        $storage_allow = $balance['storage_allow'];
        //流量余额
        $flux_allow = $balance['flux_allow'];

        //免费存储空间或者流量小于10%发送通知提示
        if (($shop_storage_default > 0 && $storage_allow / $shop_storage_default <= 0.1)
            || ($shop_flux_default > 0 && $flux_allow / $shop_flux_default <= 0.1)) {
            event(new SystemEvent(
                $shop_id,
                trans('notice.title.score.not_enough_storage_flux'),
                trans('notice.content.score.not_enough_storage_flux'),
                0,
                -1,
                '系统管理员'
            ));
        }

        $where = ['shop_id' => $shop_id, 'status' => 0];
//        $today = date('Y-m-d', time());
//        $yesterday = date_format(date_add(date_create($today), date_interval_create_from_date_string('-1days')), 'Y-m-d');
        //短书币余额 最新记录的余额
        $query = ShopFunds::where($where)
            ->select('balance')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();
        $amount = 0;
        if ($query) {
            $amount = $query->balance;
        }
        if ($amount < 0) {
            // 短书币欠费了,记录或者更新欠费情况
            ShopFundsArrears::createOrUpdateFundsArrears($shop_id, $amount, $now);
        }

//        $shop_id = '5bed7j49g8j608312g';
        if ($amount >= 0 && $amount <= 500) {
            //可用免费用量=0，0≤短书币余额≤5
            event(new SystemEvent($shop_id, trans('notice.title.score.not_enough'), trans('notice.content.score.not_enough'), 0, -1, '系统管理员'));
        }
//        else if ($amount < 0) {
            //可用免费用量=0，短书币余额＜0，且持续时间≤3天
            // 通用通知
//            event(new SystemEvent($shop_id, trans('notice.title.score.no_money'), trans('notice.content.score.no_money'), 0, -1, '系统管理员'));
//            $expire_time = strtotime('+2 days');
            // 店铺通知 持续两天
//            ShopNotice::createOrDoNotExistCountDownNotice($shop_id, SHOP_DISABLE_FUNDS_ARREARS, '短书币欠费了', $expire_time);
            //两天后禁用店铺
//            ShopClose::createOrDoNotExistShopClose($shop_id, 'close', SHOP_DISABLE_FUNDS_ARREARS, $expire_time);
//        }
//        if (($storage_allow == 0 || $flux_allow == 0)) {
//
//        }
    }

    public function isAdvancedVersion()
    {
        return $this->version == VERSION_ADVANCED;
    }

    public function isBasicVersion()
    {
        return $this->version == VERSION_BASIC;
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: a123456
 * Date: 2017/6/1
 * Time: 上午10:37
 */

namespace App\Models;


use App\Events\SystemEvent;
use Illuminate\Database\Eloquent\Model;

class SystemNotice extends Model
{
    protected $table = 'system_notice';

    public $timestamps = false;

    protected $fillable = ['shop_id', 'title', 'content', 'send_type', 'user_id', 'user_name', 'send_time', 'top'];

    /**
     * 发送单个店铺系统消息
     *
     * @param $shop_id
     * @param $title_sign
     * @param $content_sign
     * @param $content_params
     */
    public static function sendShopSystemNotice($shop_id, $title_sign, $content_sign, $content_params = []){
        $title = trans($title_sign);
        $content = trans($content_sign);
        foreach ($content_params as $key => $val) {
            $content = str_replace('{' . $key . '}', $val, $content);
        }
        event(new SystemEvent($shop_id, $title, $content, 0, -1, '系统管理员'));
    }

    /**
     * 批量发送店铺系统消息
     * @param $shop_ids
     * @param $title_sign
     * @param $content_sign
     * @param array $content_params
     */
    public static function sendShopsSystemNotice($shop_ids, $title_sign, $content_sign, $content_params = []){
        $title = trans($title_sign);
        $content = trans($content_sign);
        foreach ($content_params as $key => $val) {
            $content = str_replace('{' . $key . '}', $val, $content);
        }
        foreach($shop_ids as $shop_id){
            $params[] = [
                'shop_id' => $shop_id,
                'title' => $title,
                'content' => $content,
                'send_type' => 0,
                'user_id' => -1,
                'user_name' => '系统管理员',
                'send_time' => time(),
                'top' => 0,
            ];
        }
        if (isset($params) && count($params) > 0) {
            self::insert($params);
        }
    }

    /**
     * @param $shop_ids
     * @param $title_sign
     * @param $content_sign
     * @param array $content_params
     */
    public static function sendShopsSystemNoticeMulti($shop_ids, $title_sign, $content_sign, $content_params = []){
        $title = trans($title_sign);
        $count = count($shop_ids);
        for($i = 0; $i < $count; $i++){
            $shop_id = $shop_ids[$i];
            $content = trans($content_sign);
            if(count($content_params)>$i){
                $param = $content_params[$i];
                foreach ($param as $key => $val) {
                    $content = str_replace('{' . $key . '}', $val, $content);
                }
            }
            $params[] = [
                'shop_id' => $shop_id,
                'title' => $title,
                'content' => $content,
                'send_type' => 0,
                'user_id' => -1,
                'user_name' => '系统管理员',
                'send_time' => time(),
                'top' => 0,
            ];
        }
        if (isset($params) && count($params) > 0) {
            self::insert($params);
        }
    }
}
<?php
/**
 * admin端的基类
 */
namespace App\Http\Controllers\Admin;

use App\Events\OperationEvent;
use App\Events\PushTemplateEvent;
use App\Http\Controllers\Admin\OpenPlatform\CoreTrait;
use App\Http\Controllers\Controller;
use App\Models\Column;
use App\Models\Content;
use App\Models\Course;
use App\Models\PromotionContent;
use App\Models\PromotionShop;
use App\Models\ShopContentRemind;
use App\Models\ShopRemindStatus;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;


class BaseController extends Controller
{

    use CoreTrait;
    protected $type = 'applet';
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->user = Auth::id() ? [
                'id'    => Auth::id(),
                'name'  => Auth::user()->name,
            ] : [];
            $this->shop = Auth::id() ? Session::get('shop:'.$this->user['id']) : [];
            return $next($request);
        });
    }

    /**
     * 课程或栏目推送
     */
    public function pushContent($shop_id,$content_id,$course_title,$content_type)
    {
        $types = ShopRemindStatus::where('shop_id',$shop_id)->value('types');
        if($types){
            $types = unserialize($types);
            if($types[$content_type]) {
                $obj = ShopContentRemind::select('id', 'shop_id', 'source', 'openid', 'content_id', 'content_type', 'form_id', 'scene')->where([
                    'shop_id' => $shop_id,
                    'content_id' => $content_id,
                    'content_type' => $content_type
                ])->get();

                if (!$obj->isEmpty()) {
                    foreach ($obj as $value) {
                        $accessToken = '';
                        if ('applet' == $value->source) {
                            $accessToken = $this->getAccessToken($shop_id);
                        }
                        $value->course_title = $course_title;
                        event(new PushTemplateEvent($value, $accessToken));
                    }
                }
            }
        }
    }

    private function getAccessToken($shop_id)
    {
        $authorizationData = $this->getAuthorizerAccessToken();
        $accessToken = $authorizationData['authorizer_access_token'];
        Cache::put('push:applet:'.$shop_id.':access_token',$accessToken,110);
        return $accessToken;
    }

    /**
     * 批量更新数据
     * @param $table
     * @param array $multipleData
     * @param array $where
     * @return mixed
     */
    protected function updateBatch($table, $multipleData = [], $where = [])
    {
        if (empty($multipleData)) {
            return;
        }
        $tableName = DB::getTablePrefix() . $table; // 表名
        $firstRow  = current($multipleData);

        $updateColumn = array_keys($firstRow);
        // 默认以id为条件更新，如果没有ID则以第一个字段为条件
        $referenceColumn = isset($firstRow['id']) ? 'id' : current($updateColumn);
        unset($updateColumn[0]);
        // 拼接sql语句
        $updateSql = "UPDATE " . $tableName . " SET ";
        $sets      = [];
        $bindings  = [];
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
        $whereIn   = collect($multipleData)->pluck($referenceColumn)->values()->all();
        $bindings  = array_merge($bindings, $whereIn);
        $whereIn   = rtrim(str_repeat('?,', count($whereIn)), ',');
        $updateSql = rtrim($updateSql, ", ") . " WHERE `" . $referenceColumn . "` IN (" . $whereIn . ")";
        foreach ($where as $key=>$value){
            $updateSql = $updateSql.' and '.$key."='".$value."'";
        }
        // 传入预处理sql语句和对应绑定数据
        return DB::update($updateSql, $bindings);
    }


    /**
     * 处理商品推广
     */
    protected function createPromotionContent($content_id, $content_type)
    {
        $shop_id = $this->shop['id'];
        $promotion_setting = PromotionShop::where(['shop_id' => $shop_id])->first();
        //存在店铺推广配置
        if ($promotion_setting) {
            if ($promotion_setting) {
                $params = [
                    'shop_id' => $shop_id,
                    'content_id' => $content_id,
                    'content_type' => $content_type,
                    'promotion_rate_id' => $promotion_setting->promotion_rate_id,
                    'is_participate' => $promotion_setting->auto_join_promotion,
                ];
                PromotionContent::insert($params);
            }
        }
    }
}
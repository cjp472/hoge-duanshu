<?php
/**

 */

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ModulesShopModule extends Model
{
    protected $table = 'modules_shopmodule';
    protected $connection = 'djangodb';

    const MODULE_SLUG_PROMOTION = 'promotion';
    const MODULE_SLUG_COMMUNITY = 'community';
    const MODULE_SLUG_OBS_LIVE = 'obs_live';
    const MODULE_SLUG_ONLINE_LIVE = 'online_live';

    public static function isModuleOpen($shop_id, $module_slug)
    {
        $module = ModulesShopModule::where(['shop_id' => $shop_id])
            ->join('modules_module', 'modules_module.id', 'modules_shopmodule.module_id')
            ->where(['modules_module.slug' => $module_slug])
            ->where(['modules_shopmodule.status' => MODULE_STATUS_OPEN])
            ->first();
        return $module ? 1 : 0;
    }
}
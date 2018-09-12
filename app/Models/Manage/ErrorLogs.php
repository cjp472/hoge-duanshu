<?php
/**
 * Created by PhpStorm.
 * User: zhoujie
 * Date: 2017/6/18
 * Time: 下午2:12
 */

namespace App\Models\Manage;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ErrorLogs extends Model
{
    protected $connection = 'log';
    protected $table = 'error_logs';
    public $timestamps = true;

    public function __construct()
    {
        $this->setTable(self::newTable());
        self::copy();
    }

    public static function copy()
    {
        $newtable = self::newTable();
        if(!Schema::connection('log')->hasTable($newtable)){
            Schema::connection('log')->create($newtable,function (Blueprint $table) {
                $table->increments('id');
                $table->integer('user_id')->unsigned()->comment('操作人名称');
                $table->string('user_name',32)->nullable()->comment('操作人名称');
                $table->string('type',16)->nullable()->comment('操作类型');
                $table->string('classtype',32)->nullable()->comment('操作类型');
                $table->string('route',200)->comment('操作路由');
                $table->text('input_data')->nullable()->comment('输入数据');
                $table->text('error')->nullable()->comment('错误内容');
                $table->string('ip',15)->nullable()->comment('操作人ip');
                $table->text('user_agent')->nullable()->comment('操作人浏览器信息');
                $table->integer('time')->comment('时间');
                $table->string('source',16)->default('back')->comment('来源 后端back 前端front');
                $table->tinyInteger('status')->default(0)->comment('处理状态');
                $table->timestampsTz();
            });
        }
    }

    private static function newTable()
    {
        $quarter = date('Y');      //季度
        return 'error_logs_'.$quarter;
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: Allen
 * Date: 17/6/30
 * Time: 下午4:03
 */
namespace App\Models\Manage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AdminLogs extends Model
{
    protected $connection = 'log';
    protected $table = 'admin_logs';
    public $timestamps = false;

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
                $table->string('type',30)->nullable()->comment('操作类型');
                $table->string('title',100)->nullable()->comment('操作内容标题');
                $table->string('route',200)->comment('操作路由');
                $table->text('input_data')->nullable()->comment('输入数据');
                $table->text('output_data')->nullable()->comment('输出数据');
                $table->string('ip',15)->nullable()->comment('操作人ip');
                $table->text('user_agent')->nullable()->comment('操作人浏览器信息');
                $table->integer('operate_time');
            });
        }
    }

    private static function newTable()
    {
//        $quarter = date('Y').sprintf("%02d",ceil((date('n'))/3));      //季度
        return 'admin_logs_'.date('Y');
    }
}
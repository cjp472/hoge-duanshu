<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2017/5/15
 * Time: 18:33
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class H5Logs extends Model
{
    protected $connection = 'log';
    protected $table = 'h5_logs';
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
                $table->char('user_id',32)->comment('操作人名称');
                $table->string('user_name',32)->nullable()->comment('操作人名称');
                $table->string('type',16)->nullable()->comment('操作类型');
                $table->string('title',256)->comment('操作标题');
                $table->string('route',200)->comment('操作路由');
                $table->text('input_data')->nullable()->comment('输入数据');
                $table->text('output_data')->nullable()->comment('输出数据');
                $table->string('ip',15)->nullable()->comment('操作人ip');
                $table->text('user_agent')->nullable()->comment('操作人浏览器信息');
                $table->integer('time')->comment('时间');
                $table->timestampsTz();
            });
        }
    }

    private static function newTable()
    {
        $quarter = date('Y').sprintf("%02d",ceil((date('n'))/3));      //季度
        return 'h5_logs_'.$quarter;
    }
}
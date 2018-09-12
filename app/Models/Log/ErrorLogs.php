<?php
namespace App\Models\Log;
use Illuminate\Database\Eloquent\Model;

class ErrorLogs extends Model
{
    protected $connection = 'log';
    protected $table = 'error_logs_temp';
    protected $fillable = [	'id','user_id','user_name','type','classtype','route','input_data','error','ip','user_agent','time','source','status','created_at','updated_at'];
}
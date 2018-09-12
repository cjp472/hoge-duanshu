<?php
namespace App\Models\Log;
use Illuminate\Database\Eloquent\Model;

class AdminLogs extends Model
{
    protected $connection = 'log';
    protected $table = 'admin_logs_temp';
    protected $fillable = [	'id','user_id','user_name','title','route','input_data','output_data','ip','user_agent','operate_time','created_at','updated_at'];
}
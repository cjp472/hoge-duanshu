<?php
namespace App\Models\Log;
use Illuminate\Database\Eloquent\Model;

class CurlLogs extends Model
{
    protected $connection = 'log';
    protected $table = 'curl_logs_temp';
    protected $fillable = [	'id','user_id','user_name','classtype','route','input_data','output_data','ip','user_agent','time','created_at','updated_at'];
}
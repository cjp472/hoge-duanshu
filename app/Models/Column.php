<?php
/**
 * Created by PhpStorm.
 * User: huang an
 * Date: 2017/3/29
 * Time: 15:01
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class Column extends Model
{

    protected $connection = 'mysql';

    protected $table = 'column';

    public $timestamps = false;

    protected $fillable = ['sales_total', 'unit_member', 'promoter_rate', 'invite_rate', 'is_participate_promotion'];

    public $hidden = ['create_user','update_user'];


    public function content(){
        return $this->hasMany('App\Models\Content','column_id','id')->where('type','!=','column');
    }

    public function column_type(){
        return $this->hasMany('App\Models\ContentType','content_id','hashid');
    }

    public function app_content()
    {
        return $this->belongsTo('App\Models\AppContent','hashid','content_id');
    }

    static public function getColumnByIds($columnIds)
    {
        return static::whereIn('hashid',$columnIds)->get();
    }

    /**
     * @param array $options
     * @return bool|void
     */
    public function create(array $options = []) {
        try {
            $this->hashid = uuid4();
            self::save($options);
        }catch (QueryException $e){
            $errorInfo = $e->errorInfo;
            if($errorInfo[1] == 1062 && strstr($errorInfo[2], 'course_hashid_unique')){
                $this->create($options);
            }
        }
    }
}
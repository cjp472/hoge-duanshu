<?php
/**
 * Created by PhpStorm.
 * User: huang an
 * Date: 2017/3/29
 * Time: 15:01
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class Content extends Model
{

    protected $connection = 'mysql';

    protected $table = 'content';

    public $timestamps = false;

    protected $fillable = ['sales_total', 'unit_member', 'promoter_rate', 'invite_rate', 'is_participate_promotion'];

    public $hidden = ['hashid','create_user','update_user','content_type','audio','video','article','alive','column','promotin_content'];

    public function article(){
        return $this->hasOne('App\Models\Article','content_id','hashid');
    }

    public function audio(){
        return $this->hasOne('App\Models\Audio','content_id','hashid');
    }

    public function video(){
        return $this->hasOne('App\Models\Video','content_id','hashid');
    }

    public function alive(){
        return $this->hasOne('App\Models\Alive','content_id','hashid');
    }

    public function column(){
        return $this->hasOne('App\Models\Column','id','column_id');
    }

    public function course(){
        return $this->hasOne('App\Models\Course','hashid','hashid');
    }

    public function content_type(){
        return $this->hasMany('App\Models\ContentType','content_id','hashid');
    }

    public function app_content()
    {
        return $this->hasOne('App\Models\AppContent','content_id','hashid');
    }

    public function shopMultiple()
    {
        return $this->hasOne('App\Models\Manage\ShopMultiple', 'shop_id', 'shop_id');

    }
    public function promotin_content()
    {
        return $this->hasOne('App\Models\PromotionContent','content_id','content_id');
    }

    public function unserializerIndexpic() {
        $this->indexpic = hg_unserialize_image_link($this->indexpic);
    }

}
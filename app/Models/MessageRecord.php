<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageRecord extends Model
{
    protected $table='message_record';

    public $timestamps = false;

    public function getUserAttribute()
    {
        $this->attributes['user'] = substr($this->attributes['user'],0,1) == 1 ? substr_replace($this->attributes['user'],'****',3,4) : $this->attributes['user'];
        return $this->attributes['user'];
    }

    public function getCreateTimeAttribute()
    {
        $this->attributes['create_time'] = date('Y-m-d H:i:s',$this->attributes['create_time']);
        return $this->attributes['create_time'];
    }

    public function getNumberAttribute()
    {
        return intval($this->attributes['number']);
    }

    public function getTypeAttribute()
    {
        return intval($this->attributes['type']);
    }
}

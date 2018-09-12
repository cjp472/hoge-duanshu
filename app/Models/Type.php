<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\ContentType;

class Type extends Model
{
    protected $table = 'type';
    public $timestamps = false;
    protected $fillable = ['order_id'];

    public function serialize() {
        $this->indexpic = $this->indexpic ? hg_unserialize_image_link($this->indexpic) : '';
        $this->child = ContentType::where('type_id', $this->id)->distinct()->count('content_id');
    }
}
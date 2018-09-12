<?php
namespace App\Models\Rbac;


use Illuminate\Database\Eloquent\Model;

class RoleUser extends Model
{
    protected $table = 'role_user';

    public $timestamps = false;

    protected $hidden = [];
}
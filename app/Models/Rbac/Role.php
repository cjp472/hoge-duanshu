<?php
namespace App\Models\Rbac;

use Zizaco\Entrust\EntrustRole;

class Role extends EntrustRole
{
    protected $table = 'roles';

    public $timestamps = false;

    protected $hidden = [];

    protected $fillable = ['name', 'id'];
}
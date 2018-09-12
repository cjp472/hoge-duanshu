<?php
 namespace App\Http\Controllers\Rbac\Role;
use App\Models\Rbac\Role;
use App\Models\User;
use Zizaco\Entrust\Traits\EntrustUserTrait;

class RoleController
{
    use EntrustUserTrait;
    public function createRole()
    {
        $owner = new Role();
        $owner->name = 'product';
        $owner->display_name = 'Product Specialist';
        $owner->description = 'User is the owner of a given project';
        $owner->save();
    }

    public function addRoleToUser()
    {
        $role = Role::where('name',request('role'));
        $user = User::where('username', '=', 'michele')->first();
        $user->attachRole($role); // 参数可以是Role对象，数组或id
        $user->roles()->attach($role->id); //只需传递id即可
    }

}
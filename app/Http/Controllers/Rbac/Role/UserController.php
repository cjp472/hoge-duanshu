<?php
namespace App\Http\Controllers\Rbac\Role;

use App\Events\Registered;
use App\Http\Controllers\Rbac\BaseController;
use App\Models\Rbac\Role;
use App\Models\Rbac\RoleUser;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends BaseController
{
    public function roleUser()
    {
        $role = request('role');
        $count = request('count') ?: 10;
        $list = RoleUser::where('roles.name', $role)
            ->leftJoin('roles','roles.id','role_user.role_id')
            ->leftJoin('users','users.id','role_user.user_id')
            ->select('users.id','users.name','role_user.role_id')
            ->orderBy('users.id','desc')
            ->paginate($count);
        return $this->output($this->listToPage($list));
    }

    public function deleteRoleUser()
    {
        RoleUser::where('user_id',request('id'))
            ->where('role_id',request('role_id'))
            ->delete();
        return $this->output(['success'=>1]);
    }

    public function addRoleUser(Request $request)
    {
        $user = request('email');
        $user = User::where('email',$user)->first();
        if(!$user){
            event(new Registered($user = $this->create($request->all())));
        }
        $role = Role::where('name',request('role'))->first();
        if(RoleUser::where('user_id',$user->id)
            ->where('role_id',$role->id)
            ->first()){
            $this->error('user_exist',['name'=>$user->name]);
        };
        $user->attachRole($role);
        return $this->output([
            'id'=>$user->id,
            'name'=>$user->name,
            'role_id'=>$role->id
        ]);
    }

    protected function create(array $data)
    {
        if(!isset($data['name']) && isset($data['email'])){
            $data['name'] = $data['email'];
        }
        if(!isset($data['password'])){
            $data['password'] = '888888';
        }
        return User::create([
            'name'      => $data['name'],
            'username'  => $data['email'],
            'email'     => $data['email'],
            'password'  => bcrypt($data['password']),
            'source'    => 'email',
            'active'    => USER_ACTIVE,
        ]);
    }
}
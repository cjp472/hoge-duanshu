<?php

namespace App\Http\Controllers\Admin\Role;

use App\Http\Controllers\Admin\BaseController;
use App\Models\User;
use App\Models\UserShop;


class RoleController extends BaseController{


    /**
     * @return mixed
     * 角色列表
     */
    public function roleLists(){
        $user = $this->selectUser();
        $response = $this->formatRoleLists($user);
        return $this->output($response);
    }

    /**
     * @return mixed
     * 角色详情
     */
    public function roleDetail(){
        $this->validateWithAttribute(['id'=>'required'],['id'=>'角色id']);
        $response = $this->getResponse(request('id'));
        return $this->output($response);
    }

    /**
     * @return mixed
     * 创建角色
     */
    public function roleCreate(){
        $this->validatorRoleData();
        $param = $this->formatRoleData();
        $id = $this->createRoleData($param);
        $response = $this->getResponse($id);
        return $this->output($response);
    }

    /**
     * @return mixed
     * 更新角色
     */
    public function roleUpdate(){
        $this->validateWithAttribute(['id'=>'required'],['id'=>'角色id']);
        $param = $this->formatRoleData();
        $this->updateRoleData($param);
        $response = $this->getResponse(request('id'));
        return $this->output($response);
    }

    /**
     * @return mixed
     * 角色生效/失效
     */
    public function roleEffect(){
        $this->validateWithAttribute(['id'=>'required','effect'=>'required'],['id'=>'角色id','effect'=>'生效状态']);
        UserShop::where(['shop_id'=>$this->shop['id'],'user_id'=>request('id')])->update(['effect'=>request('effect')]);
        return $this->output(['success'=>1]);
    }

    private function selectUser(){
        return UserShop::where(['shop_id'=>$this->shop['id'],'admin'=>0])->paginate(request('count')?:10);
    }

    private function formatRoleLists($data){
        if($data){
            foreach ($data as $v){
                $v->username = $v->user?$v->user->username:'';
                $v->name = $v->user?$v->user->name:'';
                $v->create_time = $v->user?$v->user->created_at->__tostring():'';
                $v->login_time = $v->user?($v->user->login_time ? hg_format_date($v->user->login_time) : ''):'';
                $v->makeHidden(['user']);
            }
            return $this->listToPage($data);
        }
    }

    private function getResponse($id){
        $user = User::findOrFail($id);
        $user->permission = $user->shop->permission?unserialize($user->shop->permission):[];
        $user->makeHidden(['shop']);
        return $user;
    }

    private function createRoleData($data){
        $roleNum = UserShop::where(['shop_id'=>$this->shop['id'],'admin'=>0])->count();
        $roleNum >= 5 && $this->error('exceed_limit');
        $id = User::create($data)->id;
        UserShop::insert([
            'user_id'=>$id,
            'shop_id'=>$this->shop['id'],
            'permission'=>serialize(request('permission')),
            'effect' => 1,
            'admin' => 0,
        ]);
        return $id;
    }

    private function updateRoleData($data){
        User::where('id',request('id'))->update($data);
        UserShop::where(['user_id'=>request('id'),'shop_id'=>$this->shop['id']])->update(['permission'=>serialize(request('permission'))]);
    }

    private function validatorRoleData(){
        $this->validateWithAttribute([
            'username' => 'required|regex:/^\w{3,20}$/|unique:users,username',
            'name' => 'required|max:255',
            'password' => 'required|min:6',
            'permission'=> 'required|array',
        ],[
            'username' => '登录账户',
            'name'     => '昵称',
            'password' => '密码',
            'permission' => '权限',
        ]);
    }

    private function formatRoleData(){
        $data = [
            'username' => request('username'),
            'name'  => request('name'),
            'create_user' => $this->user['id'],
        ];
        request('password') && $data['password'] = bcrypt(request('password'));
        return $data;
    }


}
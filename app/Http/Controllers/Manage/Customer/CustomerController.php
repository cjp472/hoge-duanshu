<?php
namespace App\Http\Controllers\Manage\Customer;

use App\Http\Controllers\Manage\BaseController;
use App\Models\Manage\Customer;
use App\Models\Manage\CustomerFollow;
use App\Models\Manage\Shop;
use Illuminate\Support\Facades\Auth;

class CustomerController extends BaseController
{
    //客户公有池
    public function publicPool()
    {
        $count = request('count') ?: 10;
        $sql = Customer::select('customer.*');
        if(!request('is_all') && !request('mobile') && !request('name'))
        {
            $sql->where('customer.user_id',0);
        }
        if($user_id = request('user_id')){
            $sql->where('customer.user_id',$user_id);
        }
        if($mobile = request('mobile')){
            $sql->where('customer.telephone','like' ,'%'.$mobile.'%');
        }
        if($name = request('name')){
            $sql->where('customer.user_name','like' ,'%'.$name.'%');
        }
        if($book = request('book')){
            $sql->where('customer.follow_date','>=' ,strtotime(date('Ymd')));
            $sql->where('customer.follow_date','<' ,strtotime('+1 day',strtotime(date('Ymd'))));
        }
        if(request('buy')){
            $sql->where('customer.cooperation',1);
        }else if(request('unbuy')){
            $sql->where('customer.cooperation',0);
        }
        if($intention = request('intention')){
            $sql->where('customer.intention',$intention);
        }
        if(request('is_add') > 0 ){
            $sql->where('customer.remove_at','>',0);
        }elseif( request('is_add') < 0  ) {
            $sql->where('customer.remove_at','=',0);
        }
        $sql->addSelect('users.mobile','users.login_time')
            ->leftJoin('users','customer.customer_id','users.id');
        $sql->addSelect('regist_track.search_word', 'regist_track.search_engine')
            ->leftJoin('regist_track', 'customer.customer_id', 'regist_track.user_id');
        if(request('column') == 'created_at' && request('sort')){
            $sql->orderBy('customer.created_at',request('sort'));
        }elseif(request('column') == 'login_time' && request('sort')){
            $sql->orderBy('users.login_time',request('sort'));
        }else{
            $sql->orderBy('customer.created_at','desc');
        }

        $sql1 = clone $sql;
        $sql2 = clone $sql;
        $list = $sql->paginate($count);
        if($list){
            foreach ($list->items() as $item){
                $item->login_time = $item->login_time ? date('Y-m-d H:i:s',$item->login_time) : '-';
                $item->user_name = $item->user_name ?: '';
                $item->contacts = $item->contacts ?: '';
                $item->cooperation = $item->cooperation ? 1 : 0;
                $item->is_add = $item->remove_at>0 ? 1 : 0;
            }
        }
        $data = $this->listToPage($list);
        $data['buyed'] = $sql1->where('cooperation',1)->count();
        $data['unbuyed'] =$sql2->where('cooperation',0)->count();
        return $this->output($data);
    }

    //客户私有池
    public function privatePool()
    {
        $count = request('count') ?: 10;
        $sql = Customer::where('customer.user_id',$this->user['id']);
        if($mobile = request('mobile')){
            $sql->where('customer.telephone','like' ,'%'.$mobile.'%');
        }
        if($name = request('name')){
            $sql->where('customer.user_name','like' ,'%'.$name.'%');
        }
        if($book = request('book')){
            $sql->where('customer.follow_date','>=' ,strtotime(date('Ymd')));
            $sql->where('customer.follow_date','<' ,strtotime('+1 day',strtotime(date('Ymd'))));
        }
        if($date = request('date')){
            $sql->where('customer.follow_date','>=' ,$date);
            $sql->where('customer.follow_date','<' ,strtotime('+1 day',$date));
        }
        if($intention = request('intention')){
            $sql->where('customer.intention',$intention);
        }
        if($wechat = request('wechat')){
            if($wechat == -1) { $wechat = 0 ;}
            $sql->where('customer.is_wechat',$wechat);
        }
        if(request('buy')){
            $sql->where('customer.cooperation',1);
        }else if(request('unbuy')){
            $sql->where('customer.cooperation',0);
        }
        $sql->select('customer.*','shop.version')
            ->addSelect('users.mobile','users.login_time')
            ->leftJoin('users','customer.customer_id','users.id')
            ->leftJoin('user_shop','user_shop.user_id','users.id')
            ->leftJoin('shop','shop.hashid','user_shop.shop_id');

        $sql->addSelect('regist_track.search_word', 'regist_track.search_engine')
            ->leftJoin('regist_track', 'customer.customer_id', 'regist_track.user_id');
        $sql1 = clone $sql;
        $sql2 = clone $sql;
        if(request('column') == 'created_at' && request('sort')){
            $sql->orderBy('customer.created_at',request('sort'));
        }elseif(request('column') == 'login_time' && request('sort')){
            $sql->orderBy('users.login_time',request('sort'));
        }else{
            $sql->orderBy('customer.followd_at','desc');
        }
        $list = $sql->paginate($count);
        if($list){
            foreach ($list->items() as $item){
                $item->login_time = $item->login_time ? date('Y-m-d H:i:s',$item->login_time) : '-';
                $item->telephone = $item->telephone ?: $item->mobile;
                $item->user_name = $item->user_name ?: '';
                $item->cooperation = $item->cooperation ? 1 : 0;
                $item->contacts = $item->contacts ?: '';
                $item->follow_date = $item->follow_date ? date('Y-m-d H:i:s',$item->follow_date) : '';
                $item->followd_at = $item->followd_at ? date('Y-m-d H:i:s',$item->followd_at) : '-';
            }
        }
        $data = $this->listToPage($list);
        $data['buyed'] = $sql1->where('cooperation',1)->count();
        $data['unbuyed'] = $sql2->where('cooperation',0)->count();
        return $this->output($data);
    }

    //添加公有池客户到私有池
    public function addPublicToPrivate()
    {
        $shop_id = request('shop_id');
        $cus = Customer::where('shop_id',$shop_id)->first();
        if($cus && $cus->user_id){
            $this->error('customer-is-exist');
        }
        if(!$cus->cooperation)
        {
            $count = Customer::where(['user_id'=>$this->user['id'],'cooperation'=>0])->count();
            if($count >= 200){
                $this->error('over_num',['num'=>200,'title'=>'客户']);
            }
        }
        Customer::where('shop_id',$shop_id)
            ->where('user_id',0)
            ->update(['user_id'=>$this->user['id'],
                'followd_at' => time()
            ]);

        CustomerFollow::where('shop_id',$cus->shop_id)
            ->update(['user_id'=>$this->user['id']]);
        return $this->output(['success'=>1]);
    }

    //将客户从私有池移除
    public function removeFormPrivate()
    {
        $id = request('id');
        $cus = Customer::where('id',$id)->firstOrFail();
        Customer::where('id',$id)
            ->where('user_id',$this->user['id'])
            ->update(['user_id'=>0,'remove_at'=>time()]);
        CustomerFollow::where('shop_id',$cus->shop_id)
            ->update(['user_id'=>0]);
        return $this->output(['success'=>1]);
    }

    //更改客户信息
    public function updateCustomer()
    {
        $key = request('key');
        $value = request('value');
        $id = request('id');
        Customer::where('id',$id)
            ->update([$key => $value]);
        return $this->output(['success'=>1]);
    }

    //添加意向状态标签
    public function addIntention()
    {
        $tag = request('tag');
        $shop_id = request('shop_id');
        Customer::where('shop_id',$shop_id)
            ->update('intention',$tag);
        return $this->output(['success'=>1]);
    }

    public function deleteIntention()
    {
        $shop_id = request('shop_id');
        Customer::where('shop_id',$shop_id)
            ->update('intention','');
        return $this->output(['success'=>1]);
    }

    public function oneCustomer()
    {
        $shop_id = request('shop_id');
        $sql= Customer::where('customer.shop_id',$shop_id);
        if( !Auth::user()->hasRole('master')){
            $sql->where('customer.user_id',$this->user['id']);
        }
        $customer = $sql->select('customer.*')
            ->addSelect('users.mobile','users.login_time')
            ->addSelect('shop.title','shop.create_time','shop.verify_status','shop.verify_first_type','shop.version','shop.hashid')
            ->leftJoin('shop','customer.shop_id','shop.hashid')
            ->leftJoin('users','customer.customer_id','users.id')
            ->firstOrFail();
        if($customer)
        {
            return $this->output($customer);
        }
        return [];
    }

    public function sysncCustomer()
    {
        $data = Shop::select('hashid','title','create_time','version','user_id','users.mobile')
            ->leftJoin('user_shop','shop.hashid','user_shop.shop_id')
            ->leftJoin('users','users.id','user_shop.user_id')
            ->where('user_shop.admin',1)->get();
        if($data){
            foreach ($data as $value){
                Customer::insert([
                    'shop_id' => $value->hashid,
                    'user_name' => $value->title,
                    'created_at'    => date('Y-m-d H:i:s',$value->create_time),
                    'cooperation'   => $value->version =='advanced' ? 1 : 0,
                    'customer_id'   => $value->user_id,
                    'telephone' => $value->mobile,
                ]);
            }
        }
        return $this->output(['success'=>1]);
    }

    public function changeWechat()
    {
        $id = request('id');
        $cus = Customer::findOrFail($id);
        $cus->is_wechat = request('wechat') ? intval(request('wechat')) : 0;
        $cus->save();
        return $this->output(['success'=>1]);
    }
}
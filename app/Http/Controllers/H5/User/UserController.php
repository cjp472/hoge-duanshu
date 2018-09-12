<?php
/**
 * Class UserController
 * 用户设置
 */
namespace App\Http\Controllers\H5\User;

use App\Http\Controllers\H5\BaseController;
use App\Events\ErrorHandle;
use App\Models\CardRecord;
use App\Models\Member;
use App\Models\MemberCard;
use App\Models\Order;
use App\Models\PrivateUser;
use App\Models\Code;
use App\Models\InviteCode;
use App\Models\Notice;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class UserController extends BaseController
{

    /**
     * 会员信息详情
     * @return mixed
     */
    public function detail(){
        $member = Member::where(['uid'=>$this->member['id'],'shop_id'=>$this->shop['id']])->firstOrFail();
        $member->birthday = $member->birthday ? date('Y-m-d',$member->birthday) : '';
        $member->makeVisible(['sex','birthday']);
        $member->setKeyType('string');
        $member->id = $member->uid;
        return $this->output($member);
    }

    /**
     * 编辑会员信息
     * @return mixed
     */
    public function update(){
        $this->validateWithAttribute([
            'nick_name' => 'required|max:32',
            'true_name' => 'alpha_dash|max:32',
            'email'     => 'email|max:32',
            'address'   => 'alpha_dash|max:256',
            'company'   => 'alpha_dash|max:64',
            'birthday'  => 'date',
        ],[
            'avatar'    => '头像',
            'nick_name' => '昵称',
            'true_name' => '真名',
            'email'     => '邮箱',
            'address'   => '地址',
            'company'   => '公司',
            'birthday'  => '生日'
        ]);
        $member = Member::where(['uid'=>$this->member['id'],'shop_id'=>$this->shop['id']])->firstOrFail();
        $member->avatar = (is_array(request('avatar')) && isset(request('avatar')['host']) && isset(request('avatar')['file'])) ? request('avatar')['host'].request('avatar')['file'] : (request('avatar')? : '');
        $member->nick_name = request('nick_name') ? : '';
        $member->sex = intval(request('sex'));
        $member->true_name = request('true_name') ? : '';
        $member->email = request('email') ? : '';
        $member->address = request('address') ? : '';
        $member->company = trim(request('company'));
        $member->position = trim(request('position'));
        $member->birthday = request('birthday') ? strtotime(request('birthday')) : '';
        $member->save();
        $member->setKeyType('string');
        $member->id = $member->uid;
        return $this->output($member);
    }

    /**
     * 手机号绑定
     */
    public function mobileBind(){
       $this->shopInstance = $this->getShop();
       $this->validateData();
       $source = Member::where(['uid'=>$this->member['id'],'shop_id'=>$this->shop['id']])->value('source');

       //对该手机号是否绑定过，或者一个会员对手机号重复绑定的判断
       if(Redis::scard('mobileBind:'.$this->shop['id'].':'.$source.':'.request('mobile'))>0||Redis::sismember('mobileBind:'.$this->shop['id'].':'.request('mobile'),$this->member['id'])){
           $this->error('mobile_already_bind');
       }
       // fallback to database
       $existed = Member::where([ ['mobile','=', request('mobile')], ['source','=', $source], ['uid','!=', $this->member['id']], ['shop_id','=', $this->shop['id']] ])->count();
       if($existed){
            $this->error('mobile_already_bind');
       }
       $mobile = Member::where(['uid'=>$this->member['id'],'shop_id'=>$this->shop['id']])->value('mobile');
       Member::where(['uid'=>$this->member['id'],'shop_id'=>$this->shop['id']])->update(['mobile'=>request('mobile')]);
       if($mobile){ //如果是换绑手机，先清除之前的绑定缓存
           Redis::srem('mobileBind:'.$this->shop['id'].':'.$mobile,$this->member['id']);
           Redis::srem('mobileBind:'.$this->shop['id'].':'.$source.':'.$mobile,$this->member['id']);
       }
       Redis::sadd('mobileBind:'.$this->shop['id'].':'.request('mobile'),$this->member['id']); //做h5和小程序的兼容
       Redis::sadd('mobileBind:'.$this->shop['id'].':'.$source.':'.request('mobile'),$this->member['id']);  //做判断
       $r = ['success' => 1];
        try {
            $d = $this->postBindMobile(request('mobile'));
       } catch (Exception $exception) {
            event(new ErrorHandle($exception));
            $d = [];
       }

       $r = array_merge($r,$d);
       return $this->output($r);
    }

    private function postBindMobile($mobile) {
        $data = [];
        $member = Member::where(['uid' => $this->member['id'], 'shop_id' => $this->shop['id'], 'mobile'=>$mobile])->first();
        $data['qfzs_rights'] = $this->checkoutQunfaGift($member);
        return $data;

    }

    private function checkoutQunfaGift($member) {
        $filters = ['shop_id' => $member->shop_id, 'mobile' => $member->mobile, 'status' => 0, 'user_id'=>''];
        $inviteCodesId = Code::where($filters)->get()->pluck('code_id')->unique('code_id')->toArray();
        $codesSql = Code::where($filters);
        $affectedA = $codesSql->update(['user_id' => $member->uid, 'user_name' => $member->nick_name, 'user_avatar' => $member->avatar]);//更新通过手机号方式导入的
        $affectedB = Code::where(['user_id' => $member->uid, 'status' => 0,'mobile'=>''])->update(['mobile' => $member->mobile]);//更新通过指定用户方式导入的，但当时并没有绑定手机号的
        $InviteCodes = InviteCode::whereIn('id', $inviteCodesId)->get();
        $noticeRows = [];
        foreach ($InviteCodes as $item) {
            $noticeRows[] = Notice::formatQunFaGiftNotice($item, $member, $this->shopInstance);
        }
        DB::table('notify')->insert($noticeRows);
        return $affectedA + $affectedB;

    }

    private function validateData(){
        $this->validateWithAttribute(['mobile'=>'required|regex:/^(1)[3,4,5,6,7,8]\d{9}$/','code'=>'required'],['mobile'=>'手机号','code'=>'验证码']);
        $code = Cache::get('mobile:code:' . request('mobile'));
        if ($code != request('code')) {
            return $this->error('mobile_code_error');
        }
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * 我的会员卡
     */
    public function memberCard(){
        $this->validateWithAttribute(['status' => 'required'], ['status' => '会员卡状态']);
        $member_ids = hg_is_same_member($this->member['id'],$this->shop['id']);
        if($member_ids){
            $sql = CardRecord::whereIn('member_id',$member_ids)->where(['shop_id'=>$this->shop['id']]);
        }else{
            $sql = CardRecord::where(['shop_id'=>$this->shop['id'],'member_id'=>$this->member['id']]);
        }
        request('status')==1 ? $sql->where('end_time','>',time()) : $sql->where('end_time','<',time());
        $data = $sql->paginate(request('count')?:10);
        $lists = $this->listToPage($data);
        if($lists && $lists['data']){
            foreach($lists['data'] as $item){
                $mc = $item->memberCard;
                $item->style = $mc?intval($mc->style):0;
                $item->verbose_title = $mc ? $mc->verbose_title:'';
                $item->state = $item->start_time < time() && $item->end_time > time() ? 1 : 0;
                $item->optionAndExpire();
                $item->start_time = $item->start_time?hg_format_date($item->start_time):0;
                $item->end_time = $item->end_time?hg_format_date($item->end_time):0;
                $item->makeHidden(['memberCard','order']);
            }
        }
        return $this->output($lists);
    }


    /**
     * 修改密码
     */
    public function updatePassword(){

        $this->validateWithAttribute([
            'old_password'  => 'required|alpha_num|min:6',
            'password'      => 'required|alpha_num|min:6',
        ],[
            'old_password'  => '原密码',
            'password'      => '新密码',
        ]);

        $member = Member::where(['uid'=>$this->member['id'],'shop_id'=>$this->shop['id']])->first();
        if (!$member || !Hash::check(request('old_password'),$member->password)) {
            $this->error('old-password-error');
        }
        $password = request('password');
        $member = Member::where(['uid'=>$this->member['id'],'shop_id'=>$this->shop['id']])->firstOrFail();
        if($member->source != 'inner'){
            $this->error('canot-update-password');
        }
        $member->password = bcrypt($password);
        $member->save();
        return $this->output(['success'=>1]);
    }

    public function myPresentList(Request $request) {
        $member = Member::where(['uid' => $this->member['id'], 'shop_id' => $this->shop['id']])->firstOrFail();
        $presents = $member->myPresents();
        $data = $presents->paginate(request('count') ? : 10);
        $lists = $this->listToPage($data);
        foreach ($lists['data'] as $item) {
            $item->id = $item->invite_code_id;
            $item->content_indexpic = hg_unserialize_image_link($item->content_indexpic);
            $item->makeHidden(['buy_time', 'start_time', 'end_time', 'total_num', 'use_num', 'invite_code_id','updated_at','order_id','buy_time','csv_import','mobile','copy','extra_data','user_id','user_name','avatar']);
        }

        try{
            
            $this->postGetMyPresentList($lists['data']);

        }catch(Exception $exception){
            event(new ErrorHandle($exception));
        }

        return $this->output($lists);
    }

    public function postGetMyPresentList($presents){
        Notice::readQunfaZengSongNotify($this->member['id']);
    }


}
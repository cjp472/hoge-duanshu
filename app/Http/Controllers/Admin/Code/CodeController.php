<?php
/**
 * 意见反馈
 */
namespace App\Http\Controllers\Admin\Code;

use App\Http\Controllers\Admin\BaseController;
use App\Models\Code;
use App\Models\Column;
use App\Models\Content;
use App\Models\Course;
use App\Models\InviteCode;
use App\Models\MemberCard;
use App\Models\Member;
use App\Models\Shop;
use App\Models\Notice;
use App\Models\MemberGroup;
use App\Models\MemberGroupMembers;
use App\Models\CodeImportReport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use PHPExcel_Cell;
use PHPExcel_Cell_DataType;
use PHPExcel_Cell_IValueBinder;
use PHPExcel_Cell_DefaultValueBinder;
use Illuminate\Support\Collection;


class MyValueBinder extends PHPExcel_Cell_DefaultValueBinder implements PHPExcel_Cell_IValueBinder
{
    public function bindValue(PHPExcel_Cell $cell, $value = null)
    {
        if (is_numeric($value)) {
            $cell->setValueExplicit($value, PHPExcel_Cell_DataType::TYPE_STRING);

            return true;
        }
        
        // else return default behavior
        return parent::bindValue($cell, $value);
    }
}


class CodeController extends BaseController
{

    /**
     * 自建邀请码列表
     * @return mixed
     */
    public function lists(){
        $this->validateWithAttribute([
            'title' => 'alpha_dash',
            'start_time' => 'date',
            'end_time' => 'date',
            'status' => 'numeric',
            'count' => 'numeric',
            'type' => ['alpha_dash', Rule::in(['qunfazengsong','self'])],
            'page' => 'numeric'],
            ['title' => '邀请码标题','start_time'=>'创建开始时间','end_time'=>'创建结束时间','status'=>'邀请码状态','count'=>'每页条数']
        );
        $invite = InviteCode::where(['shop_id' => $this->shop['id']]);
        request('type') && $invite->where('type',request('type','self'));
        request('title') && $invite->where('title','like','%'.request('title').'%');
        if(request('start_time') && !request('end_time')) $invite->whereBetween('created_at',[request('start_time'),time()]);//创建时间搜索
        if(!request('start_time') && request('end_time')) $invite->whereBetween('created_at',[0,request('end_time')]);
        if(request('start_time') && request('end_time'))  $invite->whereBetween('created_at',[request('start_time'),request('end_time')]);

        if(request('status') == 1) $invite->where('start_time','>',time());  //状态搜索     未开始
        if(request('status') == 3) $invite->where('end_time','<',time());    //已结束
        if(request('status') == 2)  $invite->where('start_time','<',time())->where('end_time','>',time());  //进行中
        $count = request('count') ? : 10;
        $result = $invite
            ->orderBy('created_at','desc')
            ->select('id','shop_id','title','total_num','use_num','created_at','start_time','end_time','content_id','content_type','content_title','user_name','content_indexpic', 'extra_data', 'instruction')
            ->paginate($count);
        $data = $this->listToPage($result);
        foreach ($data['data'] as $item){
            $item->start_time > time() && $item->status = 1;
            $item->end_time < time() && $item->status = 3;
            ($item->start_time < time() && $item->end_time > time()) ? $item->status = 2 : '';
            $item->start_time = $item->start_time ? date('Y-m-d H:i:s',$item->start_time) : '';
            $item->end_time = $item->end_time ? date('Y-m-d H:i:s',$item->end_time) : '';
            $item->content_indexpic = hg_unserialize_image_link($item->content_indexpic);
            if ($item->content_type == 'member_card') {
                $extra_data = $item->getExtraData();
                if($extra_data && isset($extra_data['membercard_option'])){
                    $item->option =  $extra_data['membercard_option'] ? $extra_data['membercard_option'] : (object)[];
                }
            }
            $item->makeHidden(['extra_data']);
        }
        return $this->output($data);
    }

    /**
     * 新增邀请码
     * @return InviteCode
     */
    public function createInviteCode()
    {
        $this->validateWithAttribute([
            'title' => 'required|string|max:30',
            'number' => 'required|numeric|max:1000',
            'start_time' => 'required|date|before:end_time',
            'end_time'  => 'required|date|after:start_time',
            'instruction' => 'required',
            'content_id' => 'required|alpha_dash|max:64',
            'content_type' => 'required|alpha_dash',
            'membercard_option' => 'numeric'
            ],
            ['title'=>'邀请码标题','number'=>'邀请码数量','start_time'=>'开始时间','end_time'=>'结束时间','instruction'=>'使用须知','content_id'=>'内容id','content_type'=>'内容类型']
        );

        $content = $this->getContent(request('content_type'),request('content_id'));
        if(request('content_type') == 'member_card') {
            $membercard_option = request('membercard_option') ? request('membercard_option') : 0;
            if(!$content->validOption($membercard_option)) {
                return $this->error('error_membercard_option');
            }
        }
        $inviteCode = new InviteCode();
        $inviteCode->shop_id = $this->shop['id'];
        $inviteCode->title = request('title');
        $inviteCode->total_num = request('number');
        $inviteCode->start_time = strtotime(request('start_time'));
        $inviteCode->end_time = strtotime(request('end_time'));
        $inviteCode->instruction = request('instruction');
        $inviteCode->content_id = request('content_id');
        $inviteCode->content_type = request('content_type');
        $inviteCode->content_title = $content->title;
        $inviteCode->content_indexpic = $content->indexpic?:'';
        $inviteCode->price = request('content_type') == 'member_card' ? $content->optionPrice(request('membercard_option')) : $content->price;
        $inviteCode->user_name = request('name') ? : '';
        $inviteCode->type = 'self';
        request('content_type') == 'member_card' && $inviteCode->presentMemberCard($content, request('membercard_option'));
        $inviteCode->save();
        $this->saveCode($inviteCode->id,$inviteCode->total_num);
        $inviteCode->use_num = $inviteCode->use_num ? : 0;
        $inviteCode->start_time = $inviteCode->start_time ? date('Y-m-d H:i:s', $inviteCode->start_time) : '';
        $inviteCode->end_time = $inviteCode->end_time ? date('Y-m-d H:i:s', $inviteCode->end_time) : '';
        $inviteCode->makeHidden(['updated_at','type', 'extra_data']);
        return $inviteCode;
    }

    private function getContent($type,$id)
    {
        $where = ['shop_id' => $this->shop['id'], 'hashid' => $id];
        switch($type){
            case 'column':
                $content = Column::where($where)->firstOrFail();
                break;
            case 'course':
                $content = Course::where($where)->firstOrFail();
                break;
            case 'member_card':
                $content = MemberCard::where($where)->firstOrFail();
                break;
            default:
                $content = Content::where($where)->firstOrFail();
            break;
        }
        return $content;
    }


    /**
     * 生成验证码
     * @param $invite_id
     * @param $total
     * @return mixed
     */
    private function saveCode($invite_id,$total){
        for ($i=0;$i<$total;$i++){
            $param[]= ['code_id'=> $invite_id,'code'=> $this->randCode(),'shop_id' => $this->shop['id']];
        }
        $result = Code::insert($param);
        return $result;
    }

    /**
     * 生成16位随机数
     */
    private function randCode(){
        $code = rand(100000,999999);
        return 'gc-'.time().$code;
    }

    /**
     * 邀请码使用记录列表
     * @return mixed
     */
    public function codeLists(){
        $this->validateWithAttribute([
            'id' => 'required|numeric',
            'code' => 'numeric',
            'name' => 'alpha_dash',
            'start_time' => 'date',
            'end_time' => 'date',
            'status' => 'numeric|in:0,2',
        ], [
            'id' => '邀请码主表id',
            'code' => '邀请码',
            'name' => '会员昵称',
            'start_time' => '使用开始时间',
            'end_time' => '使用结束时间',
            'status' => '使用状态'
            ]
        );
        $code = Code::where(['code.shop_id' => $this->shop['id'],'code.code_id' => request('id')]);
        array_key_exists('code.status',request()->input()) && $code->where('code.status','=',request('status'));
        request('code') && $code->where('code.code','like','%'.request('code').'%');
        request('name') && $code->where('code.user_name','like','%'.request('name').'%');
        if(request('start_time') && !request('end_time')) $code->whereBetween('code.use_time',[strtotime(request('start_time')),time()]);//创建时间搜索
        if(!request('start_time') && request('end_time')) $code->whereBetween('code.use_time',[0,strtotime(request('end_time'))]);
        if(request('start_time') && request('end_time'))  $code->whereBetween('code.use_time',[strtotime(request('start_time')),strtotime(request('end_time'))]);

        $count = request('count') ? : 10;
        $result = $code
            ->orderBy('code.use_time','desc')
            ->select('code.id','code.code','code.user_id','code.user_name', 'member.mobile', 'code.user_avatar','code.use_time','code.status','code.shop_id','code.copy')
            ->leftJoin('member','code.user_id','member.uid')
            ->paginate($count);
        $data = $this->listToPage($result);
        foreach ($data['data'] as $item){
            $item->status = ($item->status == 2) ? 1 : 0;
            $item->use_time = $item->use_time ? date('Y-m-d H:i:s',$item->use_time) : '';
            $item->copy = intval($item->copy);
        }
        return $this->output($data);
    }

    /**
     * 赠送记录列表
     * @return mixed
     */
    public function shareCodeLists()
    {
        $this->validateWithAttribute([
            'code'       => 'regex:/^[-\d]+$/',
            'title'      => 'alpha_dash',
            'order'      => 'alpha_dash',
            'status'     => 'numeric',
            'start_time' => 'date',
            'end_time'   => 'date',
            'page'       => 'numeric',
            'count'      => 'numeric'],
            ['code'=>'邀请码','title'=>'邀请码标题','order'=>'订单号','status'=>'使用状态','start_time'=>'购买开始时间','end_time'=>'购买结束时间','count'=>'每页条数','page'=>'当前页']
        );
        $code = InviteCode::where(['invite_code.shop_id' => $this->shop['id'],'invite_code.type' => 'share']);
        request('code') && $code->where('code.code','like','%'.request('code').'%');
        request('title') && $code->where('invite_code.content_title','like','%'.request('title').'%');
        request('order') && $code->where('invite_code.order_id','like','%'.request('order').'%');
        if(request('status') == 1){
            $code->where('code.status',2);
        }elseif (request('status') !==null && request('status') === '0'){
            $code->where('code.status','<>',2);
        }
        if(request('start_time') && !request('end_time')) $code->whereBetween('buy_time',[strtotime(request('start_time')),time()]);//创建时间搜索
        if(!request('start_time') && request('end_time')) $code->whereBetween('buy_time',[0,strtotime(request('end_time'))]);
        if(request('start_time') && request('end_time'))  $code->whereBetween('buy_time',[strtotime(request('start_time')),strtotime(request('end_time'))]);

        $count = request('count') ? : 10;
        $result = $code
            ->join('code','invite_code.id','code.code_id')
            ->orderBy('invite_code.buy_time','desc')
            ->select('code.code','invite_code.buy_time','invite_code.shop_id','invite_code.order_id','invite_code.content_title','code.status','code.copy','code.use_time','code.code_id','code.id')
            ->paginate($count);
        $data = $this->listToPage($result);
        foreach ($data['data'] as $item){
            $item->status = ($item->status == 2) ? 1 : 0;
            $item->buy_time = $item->buy_time ? date('Y-m-d H:i:s',$item->buy_time) : '';
            $item->use_time = $item->use_time ? date('Y-m-d H:i:s',$item->use_time) : '';
            $item->makeHidden(['code_id']);
            $item->copy = intval($item->copy);
        }
        return $this->output($data);
    }

    /**
     * 下载邀请码
     * @param $id
     */
    public function downloadCode($id){
        $code = Code::where([
            'code.shop_id' => $this->shop['id'],
            'code.code_id' => $id
        ])
            ->leftJoin('invite_code','invite_code.id','code.code_id')
            ->leftJoin('member','code.user_id','member.uid')
            ->select(
                'code.*','title','type','instruction','invite_code.user_name as apply_name','member.mobile', 'start_time','end_time','content_type','content_title','created_at')->limit(1000)->get();
        $data[] = ['赠送码标题','赠送码','赠送码链接','是否使用','使用人昵称','使用人手机号', '资源类型','资源名称','使用须知','生效时间','失效时间','创建时间'];
        if($code){
            foreach ($code as $item){
                $data[]=$this->getDownloadFormat($item);
            }
            Excel::create(date('Y-m-d',time()).'赠送码', function($excel) use($data) {
                $excel->sheet('code', function($sheet) use($data) {
                    $sheet->fromArray($data,null,'A1',false,false);
                });
            })->export('xls');
        }

    }

    private function getDownloadFormat($item){
        return [
            'title'        => $item->title,
            'code'         => $item->code,
            'url'          => str_replace(['{shop_id}','{code}'],[$item->shop_id,$item->code],config('define.h5url')),
            'is_use'       => ($item->status == 2) ? '已使用' : '未使用',
            'nick_name'    => $item->user_name,
            'mobile'       => $item->mobile,
            'content_type' => config('define.content_type.'.$item->content_type),
            'content_title'=> $item->content_title,
            'instruction'  => strip_tags($item->instruction),
            'start_time'   => $item->start_time ? date('Y-m-d H:i:s',$item->start_time) : '',
            'end_time'     => $item->end_time ? date('Y-m-d H:i:s',$item->end_time) : '',
            'create_time'   => $item->created_at
        ];
    }

    /**
     * 邀请码复制状态
     * @return \Illuminate\Http\JsonResponse
     */
    public function copyState()
    {
        $this->validateWith([
            'id'  => 'required|numeric',
        ]);
        Code::where('id',request('id'))->update(['copy'=>1]);
        return $this->output(['success'=>1]);
    }

    /**
     * 测试自建群发赠送
     * @return \Illuminate\Http\JsonResponse
     */
    public function createQunFazengSong(Request $request) {
        
        
        $this->shopInstance = Shop::where('hashid', $this->shop['id'])->firstOrFail();
        $this->validateQunFazengSong($request);
        $method = $this->getImportMethod($request);
        DB::beginTransaction();
        try {
            $inviteCode = $this->createQunFaZengSongCode($request);
            switch ($method) {
                case 'member':
                    $m = explode(',', $request->input('members'));
                    $members = $this->getMembersById($m);
                    $totalNum = $this->createCodeFromMember($inviteCode,$members);
                    $this->sendQunfaGiftNotice($members, $inviteCode);
                    break;

                case 'group':
                    $g = explode(',',$request->input('groups'));
                    $members = $this->getMembersByGroupsId($g);
                    $totalNum = $this->createCodeFromMember($inviteCode, $members);
                    $this->sendQunfaGiftNotice($members, $inviteCode);
                    break;
                case 'csv':
                    $mobiles = $this->parseCsvFile($file = $request->file('csv'), $inviteCode);
                    $mobiles = array_unique($mobiles);
                    $members = $this->getMembersByMobile($mobiles);
                    $this->sendQunfaGiftNotice($members, $inviteCode);
                    $membersMobiles = $members->pluck('mobile')->unique()->toArray();
                    $numA = $this->createCodeFromMember($inviteCode, $members);
                    $unbindMobiles = array_diff($mobiles, $membersMobiles);
                    $numB = $this->createCodeFromMobile($inviteCode, $unbindMobiles);
                    InviteCode::where('id', $inviteCode->id)->update(['csv_import' => 1]);
                    $totalNum = $numA + $numB;
                default:
                    break;
                }
            DB::commit();
            $inviteCode->total_num = $totalNum;
            InviteCode::where('id', $inviteCode->id)->update(['total_num' => $totalNum]);
            } catch(Exception $exception){
                DB::rollBack();
                throw $exception;
            }
        return $this->output(['success'=>1]);
    }

    public function sendQunfaGiftNotice($members, $inviteCode) {
        $notices = [];
        foreach ($members as $item) {
            $notices[] = Notice::formatQunFaGiftNotice($inviteCode,$item,$this->shopInstance);
        }
        DB::table('notify')->insert($notices);
    }

    public function qunFaZengSongDetail(Request $request, $id) {
        $i = InviteCode::where(['shop_id'=>$this->shop['id'],'id'=>$id])->firstOrFail();
        $output = [
            "id" => $i->id,
            "title"=> $i->title,
            "content_type"=> $i->content_type,
            "content_title"=> $i->content_title,
            "content_indexpic"=> unserialize($i->content_indexpic),
            "created_at"=> date_format($i->created_at, "Y-m-d H:i:s"),
            
            "csv_import"=> $i->csv_import?true:false
        ];
        if($i->csv_import) {
            $succes = CodeImportReport::where('invite_code_id',$i->id)->where('successed',1)
                ->select(DB::raw('count(*) as num'))->get()->toArray();

            $fail = CodeImportReport::where('invite_code_id', $i->id)->where('successed', 0)
                ->select(DB::raw('count(*) as num'))->get()->toArray();
            $failReport = CodeImportReport::where('invite_code_id', $i->id)->where('successed',0)->groupBy('detail')
                ->select(DB::raw("concat(detail,'（',count(*),'条)') as sum_detail"))->orderBy('detail', 'asc')->get()->pluck('sum_detail')->toArray();
            $output['import_success'] = $succes[0]['num'];
            $output['import_fail'] = $fail[0]['num'];
            $output['import_fail_detail'] = $failReport;
        }
        return $this->output($output); 
    }

    /*
    群发赠送会员领取列表
     */
    public function qunFaZengSongCodeList(Request $request, $id) {
        $i = InviteCode::where(['shop_id' => $this->shop['id'], 'id' => $id])->firstOrFail();
        $codeList = Code::where('code_id',$i->id)->select(['id','user_id','user_name','user_avatar','use_time','status','mobile','gift_word']);
        if($request->input('q')) {
            $q = $request->input('q');
            $codeList = $codeList->where(function ($query) use ($q) {
                $query->where('user_name', 'like', '%' . $q . '%')->orWhere([['mobile','LIKE','%'.$q.'%']]);
            });
        }
        if(!is_null($request->input('status'))) {
            $codeList = $codeList->where('status', $request->input('status'));
        }
        if(!is_null($request->input('bind_mobile'))) {
            $bind_mobile = boolVal($request->input('bind_mobile'));
            if($bind_mobile) {
                $codeList = $codeList->where('mobile', '!=', '')->where('user_id','!=','');

            } else {
                $codeList = $codeList->where(function ($query) use ($bind_mobile) {
                    $query->where('user_id', '=', '')->orWhere('mobile','=','');
                });
            }
        }
        $default_count = 10;
        $count = $request->input('count') ? : $default_count;
        $paginator = $codeList->paginate($count);
        $data = $this->listToPage($paginator);
        return $this->output($data);
    }

    public function downLoadCodeImportReport(Request $request, $id) {
        $i = InviteCode::where(['shop_id' => $this->shop['id'], 'id' => $id])->firstOrFail();
        $fail = CodeImportReport::where('invite_code_id', $i->id)->where('successed', 0)->orderBy('detail')->get();

        $fields = [];
        $fields[] = ['序号', '手机号', '失败原因'];
        foreach ($fail as $key => $value) {
            $fields[] = [
                "id"=> strVal($key + 1),
                "mobile"=>$value->mobile,
                "detail"=>$value->detail
            ];
        }
        Excel::create('失败报表_' . date('Y-m-d', time()), function ($excel) use ($fields) {
            $excel->sheet('报表', function ($sheet) use ($fields) {
                $sheet->fromArray($fields, null, 'A2', false, false);
            });
        })->export('xls');

    }

    public function exportQunfaCodesDetail(Request $request, $id) {
        $code = Code::where([
            'code.shop_id' => $this->shop['id'],
            'code.code_id' => $id
        ])
            ->leftJoin('invite_code', 'invite_code.id', 'code.code_id')
            ->select(
                'code.*',
                'title',
                'type',
                'instruction',
                'content_type',
                'content_title',
                'created_at'
            )->get();
        $data[] = ['领取人昵称', '领取人手机号', '是否已领取', '领取时间', '资源类型', '资源名称', '使用须知', '创建时间'];
        if ($code) {
            foreach ($code as $item) {
                $data[] = [
                    $item->user_name,
                    $item->mobile,
                    $item->status == 2 ? '已使用' : '未使用',
                    $item->use_time ? date('Y-m-d H:i:s', $item->use_time) : '',
                    config('define.content_type.' . $item->content_type),
                    $item->content_title,
                    strip_tags($item->instruction),
                    $item->created_at
                ];
            }
            Excel::create(date('Y-m-d', time()) . '群发赠送', function ($excel) use ($data) {
                $excel->sheet('群发赠送', function ($sheet) use ($data) {
                    $sheet->fromArray($data, null, 'A1', false, false);
                });
            })->export('xls');
        }
    }


    private function parseCsvFile($file, $inviteCode){ 
        $myValueBinder = new MyValueBinder;
        $validatorA = function($value, $context=[]) {
            return trim($value) != '';
        };
        $validatorB = function ($value, $context=[]) {
            return boolVal(preg_match("/^1\d{10}$/", $value));
        };
        $validatorC = function ($value, $context) {
            return !in_array($value, $context['mobiles']);
        };
        $validators = [
            ["validator"=>$validatorA, "message"=>"手机号码为空"],
            ["validator" =>$validatorB, "message" => "手机号码格式错误"],
            ["validator" => $validatorC, "message" => "手机号码重复"]
        ];

        $validate = function($validators, $value, $context) {
            foreach ($validators as $item) {
                if(!$item['validator']($value, $context)) {
                    return [false, $item['message']];
                }
            }
            return [true,''];
        };

        $rows = Excel::setValueBinder($myValueBinder)->load($file->path())->getsheet(0)->toArray();
        array_shift($rows);
        $mobiles = [];
        $insertRows = [];
        foreach ($rows as $item) {
            $mobile = $item[0];
            $row = [
                "mobile"=>$mobile,
                "invite_code_id"=> $inviteCode->id,
                "detail"=>''
            ];
            $v = $validate($validators, $mobile, ['mobiles'=> $mobiles]);
            if($v[0]){
                $mobiles[] = $mobile;
                $row['successed'] = 1;
            } else {
                $row['successed'] = 0;
                $row['detail'] = $v[1];
            }
            $insertRows[] = $row;
            
        }
        DB::table('code_import_report')->insert($insertRows);
        return $mobiles;
    }

    private function createCodeFromMobile($inviteCode,$mobiles) {
        $codes = [];
        foreach ($mobiles as $item) {
            $code = [];
            $code['shop_id'] = $this->shopInstance->hashid;
            $code['code_id'] = $inviteCode->id;
            $code['user_id'] = '';
            $code['user_name'] = '';
            $code['user_avatar'] = '';
            $code['status'] = 0;
            $code['gift_word'] = '';
            $code['mobile'] = $item;
            $codes[] = $code;
        }
        DB::table('code')->insert($codes);
        return count($mobiles);
    }

    

    private function createCodeFromMember($inviteCode, $members) {
        $codes = [];
        foreach ($members as $member) {
            $code = [];
            $code['shop_id'] = $this->shopInstance->hashid;
            $code['code_id'] = $inviteCode->id;
            $code['user_id'] = $member->uid;
            $code['user_name'] = $member->nick_name;
            $code['user_avatar'] = $member->avatar;
            $code['status'] = 0;
            $code['gift_word'] = '';
            $code['mobile'] = $member->mobile ? $member->mobile:'';
            $codes[] = $code;
        }
        DB::table('code')->insert($codes);
        return count($codes);
    }

    private function getContentTitlebyContentType($contentType, $request) {
        if($contentType == 'member_card') {
            $optionValue = $this->content->getOption($request->input('membercard_option'))['value'];
            return $this->content->title.'-'.$optionValue;
        } else {
            return $this->content->title;
        }
    }

    private function getContentIndexPicbyContentType($contentType, $request)
    {
        if ($contentType == 'member_card') {
            $this->content->setIndexPic();
            return serialize($this->content->indexpic);
        } else {
            return $this->content->indexpic;
        }
    }

    private function getContentPricebyContentType($contentType, $request)
    {
        if ($contentType == 'member_card') {
            $price = $this->content->optionPrice($request->input('membercard_option'));
            return $price;
        } else {
            return $this->content->price;
        }
    }

    public function createQunFaZengSongCode($request) {
        $inviteCode = new InviteCode();
        $inviteCode->shop_id = $this->shopInstance->hashid;
        $inviteCode->title = $request->input('title');
        $inviteCode->total_num = $request->input('number',0);
        $inviteCode->start_time = 0;
        $inviteCode->end_time = 0;
        $inviteCode->instruction = $request->input('instruction');
        $inviteCode->content_id = $request->input('content_id');
        $inviteCode->content_type = $request->input('content_type');
        $inviteCode->content_title = $this->getContentTitlebyContentType($inviteCode->content_type,$request);
        $inviteCode->content_indexpic = $this->getContentIndexPicbyContentType($inviteCode->content_type, $request);
        $inviteCode->price = $this->getContentPricebyContentType($inviteCode->content_type, $request);
        $inviteCode->user_name = $request->input('name') ? : '';
        $inviteCode->type = 'qunfazengsong';
        $request->input('content_type') == 'member_card' && $inviteCode->presentMemberCard($this->content, $request->input('membercard_option'));
        $inviteCode->save();
        $inviteCode->use_num = $inviteCode->use_num ? : 0;
        $inviteCode->start_time = $inviteCode->start_time ? date('Y-m-d H:i:s', $inviteCode->start_time) : '';
        $inviteCode->end_time = $inviteCode->end_time ? date('Y-m-d H:i:s', $inviteCode->end_time) : '';
        $inviteCode->makeHidden(['updated_at', 'type', 'extra_data']);
        return $inviteCode;
    }

    public function getMembersById($membersId) {
        return Member::where('shop_id', $this->shop['id'])->whereIn('id',$membersId)->get();
    }

    public function getMembersByGroupsId($groupsId) {
        $groupsMembersId = MemberGroupMembers::whereIn('group_id', $groupsId)->get()->pluck('member_id')->unique()->toArray();
        return Member::where('shop_id', $this->shop['id'])->whereIn('id', $groupsMembersId)->get();
    }

    public function getMembersByMobile($mobiles)
    {
        $members = Member::where('shop_id', $this->shop['id'])->whereIn('mobile', $mobiles)->orderby('login_time','desc')->orderby('create_time','desc')->get();
        $membersMobileBindGroup = $members->groupBy(function($item,$key){
            return $item->mobile.':'.$item->source;
        });

        $membersMobileBindGroup = $membersMobileBindGroup->sortByDesc('login_time');

        $sourceKeys = ['applet', 'wechat','inner', 'app', 'dingdone', 'smartcity'];//按优先级排序

        $primaryMembers = []; // 处理同一个手机号绑定了多个来源
        foreach($mobiles as $mobile){
            foreach ($sourceKeys as $key) {
                $bindKey = $mobile . ':' . $key;
                if($membersMobileBindGroup->has($bindKey)){
                    $primaryMembers[] = $membersMobileBindGroup[$bindKey]->first();
                    break;
                }
            }
        }
        return new Collection($primaryMembers);
    }

    private function getImportMethod($request) {
        $members = $request->input('members');
        $groups = $request->input('groups');
        $has_csv = $request->hasFile('csv');
        if ($members) {
            return 'member';
        }
        if ($groups) {
            return 'group';
        }

        if($has_csv) {
            return 'csv';
        }

        return $this->error('param-error');
    }

    private function validateQunFazengSong($request) {
        $maxFileSize = strVal(1024 * 1024 * 2);
        $this->validateWithAttribute(
            [
                'title' => 'required|string|max:30',
                'instruction' => 'required',
                'content_id' => 'required|alpha_dash|max:64',
                'content_type' => ['required','alpha_dash', Rule::in(['article','video','audio','live','column','course','member_card'])],
                'members' => '',
                'groups' => '',
                'csv' => 'file|max:'. $maxFileSize,
                'membercard_option' => 'numeric'
            ],
            ['title' => '邀请码标题',
             'instruction' => '使用须知', 
             'content_id' => '内容id',
             'content_type' => '内容类型',
             'members' => '会员',
             'groups' => '会员标签',
             'csv'  => 'csv文件',
             'membercard_option' => '会员卡选项'
            ]
        );

        switch ($request->input('content_type')) {
            case 'course':
                $this->content = Course::where(['shop_id' => $this->shop['id'], 'hashid' => $request->input('content_id')])->firstOrFail();
                break;
            case 'column':
                $this->content = Column::where(['shop_id' => $this->shop['id'], 'hashid' => $request->input('content_id')])->firstOrFail();
                break;
            case 'member_card':
                $this->content = MemberCard::where(['shop_id' => $this->shop['id'], 'hashid' => $request->input('content_id')])->firstOrFail();
                $membercard_option = $request->input('membercard_option', 0);
                if (!$this->content->validOption($membercard_option)) {
                        return $this->error('error_membercard_option');
                }
                break;
            default:
                $this->content = Content::where(['shop_id' => $this->shop['id'], 'hashid' => $request->input('content_id')])->firstOrFail();
                break;
        }
    }
    


}
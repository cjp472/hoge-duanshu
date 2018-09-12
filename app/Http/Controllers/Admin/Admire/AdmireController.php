<?php
/**
 * Created by PhpStorm.
 * User: Allen
 * Date: 17/8/31
 * Time: 下午2:36
 */
namespace App\Http\Controllers\Admin\Admire;
use App\Http\Controllers\Admin\BaseController;
use App\Models\Admire;
use Illuminate\Support\Facades\DB;

class AdmireController extends BaseController
{
    /**
     * 赞赏列表
     * @return \Illuminate\Http\JsonResponse
     */
    public function listAdmire()
    {
        $this->validateWithAttribute([
            'count'  => 'numeric'
        ],[
            'count'  => '个数'
        ]);
        $count = request('count') ? : '15';
        //笔数和钱数
        $admire = Admire::where(['shop_id'=>$this->shop['id'], 'invalid'=>0])
            ->select(DB::raw('count(member_id) as number,sum(money) as money'),'content_id')
            ->groupBy('content_id')
            ->orderBy('admire_time','desc')
            ->paginate($count);
        //人数
        $member = Admire::where('shop_id',$this->shop['id'])->select(DB::raw('count(distinct member_id) as member'),'content_id')->groupBy('content_id')->pluck('member','content_id')->toArray();
        if ($admire) {
            foreach ($admire->items() as $k=>$item){
                $item->member = $member[$item->content_id];
                $item->money = round($item->money,2);
                $item->content_name = $item->belongLive ? $item->belongLive->title : '';
            }
        }
        return $this->output($this->listToPage($admire));
    }

    /**
     * 赞赏总的统计
     * @return \Illuminate\Http\JsonResponse
     */
    public function totalAdmire()
    {
        $endTime = time();
        $startTime = strtotime(date('Y-m-d,00:00:00'));
        $allNumber = Admire::where(['shop_id'=>$this->shop['id'], 'invalid'=>0])->count('member_id');
        $allMoney = Admire::where(['shop_id'=>$this->shop['id'], 'invalid'=>0])->sum('money');
        $allMember = Admire::where(['shop_id'=>$this->shop['id'], 'invalid'=>0])->distinct()->count('member_id');
        $todayNumber = Admire::where(['shop_id'=>$this->shop['id'], 'invalid'=>0])->whereBetween('admire_time',[$startTime,$endTime])->count('member_id');
        $todayMoney = Admire::where(['shop_id'=>$this->shop['id'], 'invalid'=>0])->whereBetween('admire_time',[$startTime,$endTime])->sum('money');
        $todayMember = Admire::where(['shop_id'=>$this->shop['id'], 'invalid'=>0])->whereBetween('admire_time',[$startTime,$endTime])->distinct()->count('member_id');
        $data = [
            'allNumber'   => $allNumber ? : 0,
            'allMoney'    => round($allMoney,2) ? : 0,
            'allMember'   => $allMember ? : 0,
            'todayNumber' => $todayNumber ? : 0,
            'todayMoney'  => round($todayMoney,2) ? : 0,
            'todayMember' => $todayMember ? : 0
        ];
        return $this->output($data);
    }

    /**
     * 赞赏详情
     * @return \Illuminate\Http\JsonResponse
     */
    public function detail()
    {
        $this->validateWithAttribute([
            'content_id' => 'required|alpha_dash',
            'count'      => 'numeric'
        ],['content_id'=>'直播内容id','count'=>'个数']);
        $count = request('count') ? : '15';
        $admire = Admire::where(['shop_id'=>$this->shop['id'], 'invalid'=>0, 'content_id'=>request('content_id')])
            ->select('id','member_id','lecturer','money','admire_time')
            ->orderBy('admire_time','desc')
            ->paginate($count);
        if ($admire) {
            foreach ($admire->items() as $item) {
                $item->member_name = $item->belongMember ? $item->belongMember->nick_name : '';
                $item->lecturer_name = $item->belongLecturer ? $item->belongLecturer->nick_name : '';
                $item->admire_time = $item->admire_time ? hg_format_date($item->admire_time) : '';
            }
        }
        return $this->output($this->listToPage($admire));
    }
}
<?php
/**
 * Created by Guhao.
 * User: wzs
 * Date: 17/4/27
 * Time: 上午10:25
 */
namespace App\Http\Controllers\Manage\Dashboard;

use App\Http\Controllers\Manage\BaseController;
use App\Models\Manage\Comment;
use App\Models\Manage\Feedback;
use App\Models\Manage\Member;
use App\Models\Manage\Order;
use App\Models\WebsiteSituation;

class DashboardController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 今日概况
     */
    public function todaySituation(){
        $start_time = strtotime(date('Y-m-d 00:00:00',time()));
        $todayComment = Comment::whereBetween('comment_time',[$start_time,time()])->count();
        $todayFeedback = Feedback::whereBetween('feedback_time',[$start_time,time()])->count();
        $todayMember = Member::whereBetween('create_time',[$start_time,time()])->count();
        $allMember = Member::count();
        $result = [
            'todayComment'    => $todayComment,
            'todayFeedback'   => $todayFeedback,
            'todayMember'     => $todayMember,
            'allMember'       => $allMember
        ];
        return $this->output(['data' => $result]);
    }

    /**
     * 今日新增收入
     */
    public function incomeTotal(){
        $todayIncome = Order::where('pay_status',1)->whereBetween('pay_time',[strtotime(date('Y-m-d 00:00:00',time())),time()])->sum('price');
        $yesterdayIncome = Order::where('pay_status',1)->whereBetween('pay_time',[mktime(0,0,0,date('m'),date('d')-1,date('Y')),mktime(0,0,0,date('m'),date('d'),date('Y'))-1])->sum('price');
        $totalIncome = Order::where('pay_status',1)->sum('price');
        return $this->output([
            'todayIncome'       => $todayIncome,
            'yesterdayIncome'   => $yesterdayIncome,
            'totalIncome'       => $totalIncome,
        ]);
    }

    /**
     * 官网概况列表
     * @return array
     */
    public function websiteSituationLists(){
        $count = request('count') ? : 15;
        $website = WebsiteSituation::select('*');
        request('type') == 'upv' && $website->whereIn('type', ['home', VERSION_STANDARD, VERSION_ADVANCED, VERSION_PARTNER]);
        request('type') == 'click' && $website->whereNotIn('type', ['home', VERSION_STANDARD, VERSION_ADVANCED, VERSION_PARTNER]);
        $result = $website->paginate($count);
        if(request('type') == 'upv'){
            $result->makeHidden('quantity');
        }elseif(request('type') == 'click'){
            $result->makeHidden(['uv','pv']);
        }
        return $this->listToPage($result);
    }
}

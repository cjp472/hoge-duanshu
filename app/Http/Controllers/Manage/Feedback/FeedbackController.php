<?php
namespace App\Http\Controllers\Manage\Feedback;

use App\Http\Controllers\Manage\BaseController;
use App\Models\Manage\Feedback;

class FeedbackController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 不传member_id查询所有反馈信息，否则查询单个用户反馈信息
     * @return \Illuminate\Http\JsonResponse
     */
    public function feedback()
    {
        $this->validateWith([
            'shop_id'   => 'alpha_dash',
            'member_id' => 'alpha_dash|size:32',
            'count'     => 'numeric'
        ]);
        $count= request('count') ? : 10;
        $sql = Feedback::select('id','shop_id','content','feedback_time');
        request('member_id') && $sql->where('member_id',request('member_id'));
        request('shop_id') && $sql->where('shop_id',request('shop_id'));
        $feedback = $sql->paginate($count);
        foreach ($feedback as $item){
            $item->feedback_time = $item->feedback_time ? hg_format_date($item->feedback_time) : '';
        }
        return $this->output($this->listToPage($feedback));
    }
}

<?php
/**
 * 意见反馈
 */
namespace App\Http\Controllers\H5\Feedback;

use App\Http\Controllers\H5\BaseController;
use App\Models\FeedBack;

class FeedbackController extends BaseController
{
    /**
     * 用户进行反馈
     * @return mixed
     */
    public function addFeedback()
    {
        $this->validateWithAttribute(
            [
                'content'     => 'required|string',
                'way'         => 'required|string',
            ],[
                'content' =>'反馈内容',
                'way'  => '联系方式'
             ]
        );
        $data = $this->feedbackData();
        $result = FeedBack::insert($data);
        if($result){
            return $this->output(['success' => 1]);
        }else{
            $this->error('feedback-fail');
        }


    }

    /**
     * 反馈参数整理
     */
    private function feedbackData()
    {
        return [
            'shop_id'      => $this->shop['id'],
            'member_id'    => $this->member['id'],
            'content'      => request('content'),
            'contact_way'  => request('way'),
            'feedback_time'=> time(),
        ];
    }

}
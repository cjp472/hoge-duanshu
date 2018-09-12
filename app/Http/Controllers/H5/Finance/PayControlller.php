<?php
/**
 * 我的赠送记录和赠送给我的
 */
namespace App\Http\Controllers\H5\Finance;

use App\Http\Controllers\H5\BaseController;
use App\Models\Alive;
use App\Models\CardRecord;
use App\Models\Code;
use App\Models\Column;
use App\Models\Content;
use App\Models\Course;
use App\Models\InviteCode;
use App\Models\Member;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;

class PayControlller extends BaseController
{
    public function isPay($type,$id)
    {
        $this->validateContent($id,$type);
        //设置的有权限的运营人员可直接查看
        if(Redis::sismember('auth:member',$this->member['openid'])){
            return $this->output(['pay' => 1,'type'=>'admin']);
        }

        $content = Content::where(['hashid'=>$id, 'type'=>$type, 'shop_id'=>$this->shop['id']])->firstOrFail();
            //试看内容
        if($content->is_test){
            return $this->output(['pay'=>1,'type'=>'free']);
        }
        switch (intval($content->payment_type)){
            //专栏调整，兼容老数据专栏外单卖和专栏相同判断处理，
            case 4: //专栏外单卖
                if ($content->column_id){
                    $m = $this->checkColumnPayment($content->column_id);
                    return $this->output(['pay' => $m[0],'type'=>$m[1]]);
                }
                break;
            case 2: //收费
                if($this->checkProductPayment($type,$id)){
                    return $this->output(['pay' => 1,'type'=>'subscribe']);
                }
                if(!$content->join_membercard){
                    break;
                }
                $hasFreeMemberCard = Member::hasFreeMemberCard($this->member['id'], $this->shop['id']);;
                if ($hasFreeMemberCard) {
                    return $this->output(['pay' => 1,'type'=>'membercard']);
                }
                break;
            case 3: //免费
                return $this->output(['pay' => 1,'type'=>'free']);
                break;
            default :
                if($this->checkProductPayment($type,$id)){
                    return $this->output(['pay' => 1,'type'=>'subscribe']);
                }

                if(!$content->join_membercard){
                    break;
                }
                $hasFreeMemberCard = Member::hasFreeMemberCard($this->member['id'], $this->shop['id']);;
                if ($hasFreeMemberCard) {
                    return $this->output(['pay' => 1,'type'=>'membercard']);
                }
                break;
        }
        return $this->output(['pay' => 0,'type'=>null]);
    }

    private function checkColumnPayment($column_id)
    {
        $column = Column::find($column_id);
        $paied = $this->checkProductPayment('column',$column->hashid);
        if($paied){
            return [1,'subscribe'];
        }
        if(!$column->join_membercard){
            return [0,null];
        }
        $hasFreeMemberCard = Member::hasFreeMemberCard($this->member['id'],$this->shop['id']);
        if ($hasFreeMemberCard) {
           return [1,'membercard']; 
        }
        return [0,null];
    }

    private function checkLecturer($id){
        $alive = Alive::where('content_id',$id)->firstOrFail();
        $person_id = array_pluck(json_decode($alive->live_person, true),'id');
        $lecturer = in_array($this->member['id'],$person_id) ? 1 : 0;
        return $lecturer;
    }


    private function validateContent($id,$type)
    {
        Validator::make([
            'id'    => $id,
            'type'  => $type,
        ], [
            'id'    => 'required|alpha_num|max:64',
            'type'  => 'required|alpha|max:12|min:3|in:article,video,live,audio',
        ])->validate();
    }


    /**
     * 会员申请退款
     */
    public function refunds(){

        $this->validateWithAttribute([
            'quantity'      => 'required|numeric',
            'refund_type'   => 'required|alpha_dash',
            'refund_reason' => 'required|string|max:100',
        ],[
            'quantity'      => '退款的订单商品数量',
            'refund_type'   => '退款类型',
            'refund_reason' => '退款原因',
        ]);

        $param = [
            'buyer_id'  => $this->member['id'],
            'order_item'    => '',  //字段待确认
            'quantity'      => intval(request('quantity')),
            'refund_type'   => request('refund_type'),
            'refund_reason' => request('refund_reason'),
        ];

        $response = hg_member_refunds($param);
        $response = json_decode($response);
        if($response && $response->error_code){
            $this->errorWithText($response->error_code,$response->error_message);
        }

        //申请完成冗余存储部分申请记录信息



    }
}
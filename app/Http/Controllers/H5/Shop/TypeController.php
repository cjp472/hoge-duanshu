<?php
/**
 * 意见反馈
 */
namespace App\Http\Controllers\H5\Shop;

use App\Http\Controllers\H5\BaseController;
use App\Models\Course;
use App\Models\Type;
use App\Models\ContentType;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use App\Models\Navigation;

class TypeController extends BaseController
{
    /**
     * 店铺导航分类列表接口
     */
    public function lists(){
        $count = request('count') ? : 10;
        $data = Type::where('shop_id',$this->shop['id'])
            ->where('status',1)
            ->orderBy('order_id','asc')
            ->orderBy('create_time','desc')
            ->select('id','title','indexpic', 'brief')
            ->paginate($count);
        foreach ($data->items() as $item){
            $item->serialize();
        }
        return $this->output(['data' => $this->listToPage($data)]);
    }

    public function typeDetail($id) {
        $typeInstance = Type::where(['shop_id'=>$this->shop['id'], 'id'=>$id])->select('id', 'title', 'indexpic', 'brief')->firstOrFail();
        $typeInstance->serialize();
        return $this->output($typeInstance);
    }

    /**
     * 导航列表
    */
    public function getList()
    {
        $data = Navigation::where(['shop_id'=>$this->shop['id'],'status'=>1])
            ->orderBy('order_id')
            ->orderBy('create_time','desc')
            ->select('id','title','index_pic','link')
            ->get();
        if(!$data->isEmpty()){
            foreach($data as $value){
                $link = $value->link ? unserialize($value->link) : [];
                if($link['type'] == 'course'){
                    $link['course_type'] = Course::where('hashid',$link['id'])->value('course_type');
                }
                $value->link = serialize($link);
            }
        }

        return $this->output(['data' => $data]);
    }

}
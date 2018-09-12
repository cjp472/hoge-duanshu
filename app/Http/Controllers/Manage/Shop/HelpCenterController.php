<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2017/9/25
 * Time: 10:02
 */

namespace App\Http\Controllers\Manage\Shop;

use App\Models\HelpCenter;
use App\Http\Controllers\Manage\BaseController;

class HelpCenterController extends BaseController
{
    const PAGINATE = 20;
    /**
     * 创建
    */
    public function create()
    {
        $this->check();
        $data = [
            'title' => request('title'),
            'url' => request('url'),
            'is_display' => request('is_display'),
            'sort_no' => time()
        ];
        HelpCenter::insert($data);

        return $this->output(['success'=>1]);
    }

    /**
     * 更新
    */
    public function update($id)
    {
        $this->check();
        $result = HelpCenter::find($id);
        if(empty($result)){
            $this->error('data-not-fond');
        }
        $result->title = request('title');
        $result->url = request('url');
        $result->is_display = request('is_display');
        $result->save();

        return $this->output(['success'=>1]);
    }

    /**
     * 删除
    */
    public function delete($id)
    {
        $result = HelpCenter::find($id);
        if(empty($result)){
            $this->error('data-not-fond');
        }
        $result->delete();

        return $this->output(['success'=>1]);
    }

    /**
     * 列表
    */
    public function getList()
    {
        $count = request('count') ? intval(request('count')) : self::PAGINATE;
        $result = HelpCenter::orderBy('sort_no','desc')->paginate($count);

        return $this->listToPage($result);
    }

    /**
     * 详情
    */
    public function detail($id)
    {
        $result = HelpCenter::find($id);
        if(empty($result)){
            $this->error('data-not-fond');
        }

        return $this->output($result);
    }

    /**
     * 是否隐藏
    */
    public function isDisplay($id)
    {
        $result = HelpCenter::find($id);
        if(empty($result)){
            $this->error('data-not-fond');
        }
        $result->is_display = request('is_display');
        $result->save();

        return $this->output(['success'=>1]);
    }

    /**
     * 排序
    */
    public function sort()
    {
        $id = request('id');
        if(empty($id)){
            $this->error('data-not-fond');
        }
        $ids = explode(',',$id);
        $result = HelpCenter::whereIn('id',$ids)->orderBy('sort_no','desc')->get();
        $count = count($ids);

        for($i=0;$i<$count;$i++){
            $data = HelpCenter::find($ids[$i]);
            if(empty($data)){
                $this->error('data-not-fond');
            }
            $data->sort_no = $result[$i]->sort_no;
            $data->save();
        }

        return $this->output(['success'=>1]);
    }

    /**
     * 验证码
    */
    protected function check()
    {
        $this->validateWithAttribute([
            'title' => 'required',
            'url' => 'required',
            'is_display' => 'required'
        ],[
            'title' => '标题',
            'url' => '链接地址',
            'is_display' => '是否隐藏'
        ]);
    }
}
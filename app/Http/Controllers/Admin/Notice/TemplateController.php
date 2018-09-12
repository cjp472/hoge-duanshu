<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2017/4/5
 * Time: 18:53
 */

namespace App\Http\Controllers\Admin\Notice;


use App\Http\Controllers\Admin\BaseController;
use App\Models\TemplateNotify;


class TemplateController extends BaseController
{

    /**
     * 保存消息模板
     */
    public function templateCreate(){
        $this->validateTemplateParam();
        $template = new TemplateNotify();
        $template->shop_id = $this->shop['id'];
        $template->title = request('title');
        $template->send_name = request('send_name');
        $template->content = request('content');
        $template->saveOrFail();
        return $this->output($template);
    }

    /**
     * 验证消息模板请求数据
     */
    private function validateTemplateParam(){
        $this->validateWithAttribute([
            'title'     => 'required|alpha_dash|max:32',
            'send_name' => 'required|alpha_dash|max:32',
            'content'   => 'required|alpha_dash|max:256',
        ],[
            'title'     => '标题',
            'send_name' => '发送人昵称',
            'content'   => '内容',
        ]);
    }

    /**
     * 修改消息模板
     * @param $id
     * @return mixed
     */
    public function templateUpdate($id){
        $this->validateTemplateParam();
        $template = TemplateNotify::where('shop_id',$this->shop['id'])->findOrFail($id);
        $template->title = request('title');
        $template->send_name = request('send_name');
        $template->content = request('content');
        $template->saveOrFail();
        return $this->output($template);
    }

    /**
     * 消息模板列表
     */
    public function templateList(){
        $template = TemplateNotify::where('shop_id',$this->shop['id'])->get();
        return $this->output($template);
    }


}
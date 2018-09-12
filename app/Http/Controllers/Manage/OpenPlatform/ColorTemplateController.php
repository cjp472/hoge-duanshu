<?php
/**
 * Created by PhpStorm.
 * User: zhoujie
 * Date: 2017/9/28
 * Time: 下午5:11
 */

namespace App\Http\Controllers\Manage\OpenPlatform;


use App\Http\Controllers\Manage\BaseController;
use App\Models\Manage\ColorTemplate;

class ColorTemplateController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function lists()
    {
        $table = new ColorTemplate();
        if (request('type')) {
            $table->where('type', request('type'));
        }
        $colorTemplate = $table->get();
        if ($colorTemplate && is_array($colorTemplate)) {
            foreach ($colorTemplate as $item) {
                $item->create_time = $item->create_time ? date('Y-m-d H:i:s', $item->create_time) : '';
            }
        }
        return $this->output($colorTemplate);
    }

    public function create()
    {
        $this->validateWith([
            'title' => 'required',
            'color' => 'required',
            'type'  => 'required',
            'class' => 'required',
        ]);
        $colorTemplate = new ColorTemplate();
        $colorTemplate->title = request('title');
        $colorTemplate->color = request('color');
        $colorTemplate->indexpic = request('indexpic');
        $colorTemplate->type = request('type');
        $colorTemplate->class = request('class');
        $colorTemplate->create_time = time();
        $colorTemplate->save();
        $colorTemplate->order_id = $colorTemplate->id;
        $colorTemplate->save();
        return $this->output($colorTemplate);
    }

    public function update()
    {
        $this->validateWith([
            'id'    => 'required|numeric',
            'title' => 'required',
            'color' => 'required',
            'type'  => 'required',
            'class' => 'required',
        ]);
        $params = [
            'title' => request('title'),
            'color' => request('color'),
            'type'  => request('type'),
            'class' => request('class'),
        ];
        request('indexpic') && $params['indexpic'] = request('indexpic');
        ColorTemplate::where('id', request('id'))->update($params);
        return $this->output(['success' => 1]);
    }

    public function delete()
    {
        $this->validateWith(['id' => 'required|numeric']);
        ColorTemplate::where('id', request('id'))->delete();
        return $this->output(['success' => 1]);
    }

    public function sort()
    {

    }
}

<?php

namespace App\Http\Controllers\Manage\Logs;

use App\Http\Controllers\Manage\BaseController;
use App\Models\Log\H5Logs;

class H5LogsController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * h5日志列表
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function H5Lists()
    {
        $this->validateWith([
            'page'       => 'numeric',
            'count'      => 'numeric|max:10000',
            'type'       => 'alpha',
            'title'      => 'string',
            'start_time' => 'date',
            'end_time'   => 'date',
        ]);

        $data = $this->getH5LogsLists();
        if ($data['data']) {
            foreach ($data['data'] as $item) {
                $item->time && $item->time = hg_format_date($item->time);
            }
        }
        return $this->output($data);
    }


    private function getH5LogsLists()
    {
        $logs = H5Logs::select('*');
        $start_time = request('start_time') ?: 0;
        $end_time = request('end_time') ?: hg_format_date();
        $count = request('count') ?: 15;
        // 类型
        request('type') && $logs->where('type', 'like', '%'. request('type') . '%');
        // 用户名
        request('title') && $logs->where('route', 'like', '%' . request('title') . '%')->orWhere('title', 'like', '%' . request('title') . '%');
        $page = $logs->whereBetween('created_at', [$start_time, $end_time])->orderBy('created_at', 'desc')->paginate($count);
        return $this->listToPage($page);
    }

    /**
     * h5日志详情
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function H5Detail()
    {
        $this->validateWith(['id' => 'required|numeric']);
        $data = $this->getH5LogsDetail();
        $data && $data->time && $data->time = hg_format_date($data->time);
        return $this->output($data);
    }

    private function getH5LogsDetail()
    {
        $data = H5Logs::where('id', request('id'))->first();
        return $data ?: [];
    }
}

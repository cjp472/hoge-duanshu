<?php
namespace App\Http\Controllers\Manage\Logs;

use App\Http\Controllers\Manage\BaseController;
use App\Models\Log\CurlLogs;

class CurlLogsController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 日志列表
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function curlLists()
    {
        $this->validateWith([
            'page'       => 'numeric',
            'count'      => 'numeric|max:10000',
            'type'       => 'alpha',
            'title'      => 'string',
            'start_time' => 'date',
            'end_time'   => 'date',
        ]);
        $data = $this->getCurlLogsLists();
        if ($data['data']) {
            foreach ($data['data'] as $item) {
                $item->time && $item->time = hg_format_date($item->time);
            }
        }
        return $this->output($data);
    }
    private function getCurlLogsLists()
    {
        $logs = CurlLogs::select('*');
        $start_time = request('start_time') ?: 0;
        $end_time = request('end_time') ?: hg_format_date();
        $count = request('count') ?: 15;
        // 类型
        request('type') && $logs->where('type', '=', request('type'));
        // 用户名
        request('title') && $logs->where('route', 'like', '%' . request('title') . '%');
        if(request('start_time') || request('end_time'))
        {
            $logs->whereBetween('created_at', [$start_time, $end_time]);
        }
        $page = $logs->orderBy('id', 'desc')->paginate($count);
        return $this->listToPage($page);
    }

    /**
     * 日志详情
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function curlDetail()
    {
        $this->validateWith(['id' => 'required|numeric']);
        $data = $this->getCurlLogsDetail();
        $data && $data->time && $data->time = hg_format_date($data->time);
        return $this->output($data);
    }

    private function getCurlLogsDetail()
    {
        $data = CurlLogs::where('id', request('id'))->first();
        return $data ?: [];
    }
}

<?php
/**
 * 后台日志
 * Gh 2017-4-24
 */
namespace App\Http\Controllers\Manage\Logs;

use App\Http\Controllers\Manage\BaseController;
use App\Models\Log\Logs;
use App\Models\Log\AdminLogs;

class LogsController extends BaseController
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
    public function lists()
    {
        $this->validateWith([
            'page'       => 'numeric',
            'count'      => 'numeric|max:10000',
            'type'       => 'alpha',
            'title'      => 'string',
            'start_time' => 'date',
            'end_time'   => 'date',
        ]);
        $data = $this->getLogsLists();
        if ($data['data']) {
            foreach ($data['data'] as $item) {
                $item->time && $item->time = hg_format_date($item->time);
            }
        }
        return $this->output($data);
    }

    /**
     * 日志详情
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function detail()
    {
        $this->validateWith(['id' => 'required|numeric']);
        $data = $this->getLogsDetail();
        $data && $data->time && $data->time = hg_format_date($data->time);
        return $this->output($data);
    }


    private function getLogsLists()
    {
        $logs = Logs::select('id', 'user_id', 'user_name', 'type', 'title', 'route',  'time', 'created_at');
        request('title') && $logs->where('title','like','%'.request('title').'%');
        $start_time = request('start_time') ?: 0;
        $end_time = request('end_time') ?: hg_format_date();
        $count = request('count') ?: 15;
        $page = $logs
            ->whereBetween('created_at', [$start_time, $end_time])
            ->orderBy('created_at', 'desc')
            ->paginate($count);
        return $this->listToPage($page);
    }

    private function getLogsDetail()
    {
        $data = Logs::where('id', request('id'))->first();
        return $data ?: [];
    }


    /**
     * 运营后台操作日志列表
     * @return \Illuminate\Http\JsonResponse
     */
    public function adminLogsList()
    {
        $this->validateWith([
            'title'       => 'string',
            'start_time'  => 'date',
            'end_time'    => 'date',
            'count'       => 'numeric',
            'type'        => 'string'
        ]);
        $count = request('count') ? : 15;
        $sql = AdminLogs::select('id', 'user_id', 'user_name', 'type', 'title', 'route','operate_time');
        $start_time = request('start_time') ? strtotime(request('start_time')) : 0;
        $end_time = request('end_time') ? strtotime(request('end_time')) : time();
        $sql->whereBetween('operate_time',[$start_time,$end_time]);
        request('title') && $sql->where('title','like','%'.request('title').'%');
        request('type') && $sql->where('type','like','%'.request('type').'%');
        $logs = $sql->orderBy('operate_time','desc')->paginate($count);
        if ($logs->items()) {
            foreach ($logs->items() as $item) {
                $item->operate_time = $item->operate_time ? hg_format_date($item->operate_time) : '';
            }
        }
        return $this->output($this->listToPage($logs));
    }


    /**
     * 运营后台操作日志详情
     * @return \Illuminate\Http\JsonResponse
     */
    public function adminLogsDetail()
    {
        $this->validateWith([
           'id'   => 'required|numeric'
        ]);
        $detail = AdminLogs::where('id',request('id'))->first();
        $detail->operate_time = $detail->operate_time ? hg_format_date($detail->operate_time) : '';
        return $this->output($detail);
    }

    
}

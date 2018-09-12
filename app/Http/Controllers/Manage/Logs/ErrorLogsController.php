<?php
/**
 * Created by PhpStorm.
 * User: zhoujie
 * Date: 2017/6/18
 * Time: 上午11:02
 */

namespace App\Http\Controllers\Manage\Logs;


use App\Http\Controllers\Manage\BaseController;
use App\Models\Log\ErrorLogs;
use Illuminate\Support\Facades\Auth;

class ErrorLogsController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 错误日志列表
     *
     * @return mixed
     */
    public function errorLists()
    {
        $this->validateWith([
            'page'       => 'numeric',
            'count'      => 'numeric|max:10000',
            'classtype'  => 'alpha_dash',
            'start_time' => 'date',
            'end_time'   => 'date',
            'title'      => 'string',
            'type'       => 'alpha_dash',
            'source'     => 'alpha_dash|in:back,front'
        ]);
        $data = $this->getErrorLogsLists();
        if ($data['data']) {
            foreach ($data['data'] as $item) {
                $item->time && $item->time = hg_format_date($item->time);
                $item->status = $item->status ? true : false;
            }
        }
        return $this->output($data);
    }

    private function getErrorLogsLists()
    {
        $logs = ErrorLogs::select('id', 'user_id', 'user_name', 'classtype', 'route', 'input_data', 'error', 'time', 'ip', 'created_at','type','source','status');
        $count = request('count') ?: 15;
        request('classtype') && $logs->where('classtype', 'like', '%'.request('classtype').'%');
        request('title') && $logs->where('route', 'like', '%'.request('title').'%');
        request('type') && $logs->where('type','like','%'.request('type').'%');
        request('source') && $logs->where('source',request('source'));
        $status = request('status') ? 1 : 0;
        $logs->where('status',$status);

        if(request('start_time') || request('end_time')){
            $start_time = request('start_time') ? strtotime(request('start_time')) : 0;
            $end_time = request('end_time') ? strtotime(request('end_time')) : time();
            $logs->whereBetween('time', [$start_time, $end_time]);
        }else{
            $start_time = strtotime(date('Ymd',time()-86400));
            $end_time = time();
            $logs->whereBetween('time', [$start_time, $end_time]);
        }
        $page = $logs->orderBy('time', 'desc')->paginate($count);
        return $this->listToPage($page);
    }

    /**
     * 错误日志详情
     *
     * @return mixed
     */
    public function errorDetail()
    {
        $this->validateWith(['id' => 'required|numeric']);
        $data = $this->getErrorLogsDetail();
        $data && $data->time && $data->time = hg_format_date($data->time);
        return $this->output($data);
    }

    private function getErrorLogsDetail()
    {
        $data = ErrorLogs::where('id', request('id'))->first();
        return $data ?: [];
    }

    /**
     * 新增错误日志接口
     * @return mixed
     */
    public function createErrorLog(){
        $this->validateWithAttribute(
            ['route' => 'required|string','error' => 'required|string' ,'type' => 'required|string|in:client,h5,mini_programs'],
            ['route'=>'错误路由','error'=>'错误信息','type' => '请求类型']
        );
        $error = new ErrorLogs();
        $data = $this->formData();
        $error->setRawAttributes($data);
        $error->saveOrFail();
        return $this->output(['success' => 1]);

    }

    private function formData(){
        $data = [
            'route'          => request('route'),
            'type'           => request('type') ,
            'input_data'     => request('input_data'),
            'user_id'        => request('user_id') ? : (Auth::id() ? : ''),
            'user_name'      => request('user_name') ? : (Auth::id() ? Auth::user()->name : ''),
            'error'          => request('error'),
            'time'           => request('time') ? substr(request('time'),0,10) : time(),
            'ip'             => request('ip') ? : hg_getip(),
            'source'         => 'front',
            'classtype'      => request('classtype') ? : 'ErrorException',
         ];
        return $data;

    }

    public function fixed()
    {
        if(request('id')){
            $status = request('status') == 'true' ? 1 : 0;
            $ids = explode(',',request('id'));
            ErrorLogs::whereIn('id',$ids)->update(['status'=>$status]);
            return $this->output(['success'=>1]);
        }
        return $this->output(['success'=>0]);
    }
}

<?php
namespace App\Http\Middleware\H5;

use App\Events\H5LogsEvent;
use Closure;
use Illuminate\Support\Facades\Route;

class HandleLogs
{
    public function handle($request, Closure $next, $title = 'title')
    {
        $response = $next($request);
        $member = $request->header('x-member');
        $member && $sign = json_decode(urldecode($member),1);
        event(new H5LogsEvent($response,$this->setLogTitle($title,$request),isset($sign['id']) ? $sign['id']:'',isset($sign['nick_name']) ? $sign['nick_name']:''));
        return $response;
    }

    private function setLogTitle($title,$request)
    {
        $prefix = $title == 'id' ? 'id:' : '';
        if(request($title)){
            return $prefix.request($title);
        }
        if($request->route()->parameters && isset($request->route()->parameters[$title])){
            return $prefix.$request->route()->parameters[$title];
        }
        if(request('id') && is_string(request('id'))){
            return request('id');
        }
        return '';
    }
}
<?php

namespace App\Http\Middleware;

use App\Events\AdminLogsEvent;
use Closure;

class AdminLogs
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next,$title = '')
    {

        $response = $next($request);

        event(new AdminLogsEvent($response,$this->setLogTitle($title,$request)));

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

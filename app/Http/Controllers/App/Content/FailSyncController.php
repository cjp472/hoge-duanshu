<?php
/**
 * Created by PhpStorm.
 * User: Allen
 * Date: 17/8/30
 * Time: 上午9:26
 */

namespace App\Http\Controllers\App\Content;
use App\Http\Controllers\App\InitController;
use App\Models\FailContentSyn;
use GuzzleHttp\Client;

/**
 * 同步失败内容重新同步
 *
 * Class FailSyncController
 * @package App\Http\Controllers\App\Content
 */
class FailSyncController extends InitController
{
    public function reSync()
    {
        $this->validateWithAttribute([
           'id'   => 'required|numeric'
        ],['id'=>'同步失败表id']);
        $content = FailContentSyn::select('route','input_data','shop_id')->where(['id'=>request('id'),'is_sync'=>0])->first();
        if ($content) {
            $data = json_decode($content->input_data);
            $client = new Client([
                'headers'  => $data['header'],
                'body'    => $data['param'] ? : ''
            ]);
            try {
                $result = $client->request('post',$content->route);
                $content->is_sync = 1;
                $content->save();
            } catch (\Exception $e) {
                event(new ErrorHandle($e,'app'));
                return false;
            }
            $response = json_decode($result->getBody()->getContents(), 1);
            event(new CurlLogsEvent(json_encode($response), $client, $content->route));
        }
    }
}

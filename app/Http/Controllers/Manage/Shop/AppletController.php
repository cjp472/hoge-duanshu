<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2017/7/24
 * Time: 14:26
 */

namespace App\Http\Controllers\Manage\Shop;


use App\Events\CurlLogsEvent;
use App\Http\Controllers\Manage\BaseController;
use App\Models\Manage\MiniProgram;
use Doctrine\Common\Cache\PredisCache;
use EasyWeChat\Foundation\Application;
use EasyWeChat\OpenPlatform\Guard;
use GuzzleHttp\Client;


class AppletController extends BaseController
{

    /**
     * 微信服务器推送事件
     */
    public function openPlatformEvent()
    {
        $app = new Application(config('wechat'));
        $app->cache = new PredisCache(app('redis')->connection()->client());
        $openPlatform = $app->open_platform;
        $server = $openPlatform->server;
        $server->setMessageHandler(function ($event) use ($openPlatform) {
            switch ($event->InfoType) {
                case Guard::EVENT_AUTHORIZED: // 授权成功
                case Guard::EVENT_UPDATE_AUTHORIZED: // 更新授权
                case Guard::EVENT_UNAUTHORIZED: // 授权取消
            }
        });
        $response = $server->serve();
        return $response;
    }

    public function getToken()
    {
        $applet = MiniProgram::where(['shop_id' => request('shop_id')])->first();
        $app = new Application(config('wechat'));
        $app->cache = new PredisCache(app('redis')->connection()->client());
        $openPlatform = $app->open_platform;
        print_r($openPlatform->getAuthorizationInfo());
        die;
        try {
            $param = $miniProgram->sns->getSessionKey(request('code'));
        } catch (Exception $e) {
            throw new HttpResponseException($this->errorWithText('wechat_error_' . $e->getCode(), $e->getMessage()));
        }
        print_r($applet);
        die();
    }

    public function appletUpload()
    {
        $path = base_path('resources/applet/');
        try {
            $fileName = $this->unzip($_FILES['file']['tmp_name'], $_FILES['file']['name'], $path);
        } catch (\Exception $e) {
            $this->error('UPLOAD_FILE_FAIL');
        }
        return $this->output(['fileName' => $fileName]);
    }

    private function unzip($zip_file, $name, $path)
    {
        if (!$zip_file) {
            return false;
        }
        $resource = zip_open($zip_file);
        //如果能打开则继续
        while ($dir_resource = zip_read($resource)) {
            if (zip_entry_open($resource, $dir_resource)) {
                $file_name = $path . zip_entry_name($dir_resource);
                $file_path = substr($file_name, 0, strrpos($file_name, "/"));
                if (!is_dir($file_path)) {
                    mkdir($file_path, 0777, true);
                }
                if (!is_dir($file_name)) {
                    $file_size = zip_entry_filesize($dir_resource);
                    $file_content = zip_entry_read($dir_resource, $file_size);
                    file_put_contents($file_name, $file_content);
                }
                zip_entry_close($dir_resource);
            }
        }
        zip_close($resource);
        return explode('.', $name)[0];
    }

    public function appletDownload()
    {
        $this->validateWith([
            'title'    => 'alpha_num',
            'brief'    => 'alpha_num',
            'shopId'   => 'alpha_num',
            'fileName' => 'required'
        ]);
        $shopping_path = base_path('resources/applet/') . request('fileName') . '/' . 'config/shopping.js';
        $shopping_js = file_get_contents($shopping_path);
        $shopping_data = explode('=', $shopping_js);
        $data = json_decode($shopping_data[1]);
        if ($data) {
            foreach ($data as $key => $vo) {
                $data->$key = request($key) ?: '';
                ($key == 'h5QRcode') && $data->$key = $this->getH5QRcode();
            }
        }
        $shopping_data[1] = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $shopping_js = implode('= ', $shopping_data);
        @file_put_contents($shopping_path, $shopping_js);
        return $this->zip();
    }

    private function getH5QRcode()
    {
        $timestamp = time();
        $signature = hg_hash_sha256([
            'timestamp'     => $timestamp,
            'access_key'    => config('sms.sign_param.key'),
            'access_secret' => config('sms.sign_param.secret'),
        ]);

        $client = new Client([
            'headers' => [
                'Content-Type'    => 'application/json',
                'x-api-timestamp' => $timestamp,
                'x-api-key'       => config('sms.sign_param.key'),
                'x-api-signature' => $signature,
            ],
            'body'    => json_encode(['url' => H5_DOMAIN . '/' . request('shop_id') . '/#/']),
        ]);
        $url = config('define.inner_config.api.getH5QRcode');
        $response = $client->request('GET', $url);
        $response = json_decode($response->getBody()->getContents());
        event(new CurlLogsEvent(json_encode($response), $client, $url));
        if ($response && isset($response->error)) {
            $this->errorWithText($response->error, $response->message);
        }
        return $response->response->qrcode;
    }

    private function zip()
    {
        $filedir = base_path('resources/applet/');
        $filename = request('fileName') . ".zip"; // 最终生成的文件名（含路径）
        $zip = new \ZipArchive(); // 使用本类，linux需开启zlib，windows需取消php_zip.dll前的注释
        $zip->open($filedir . $filename, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $this->addFileToZip($filedir . request('fileName'), request('fileName'), $zip);
        $zip->close(); // 关闭
        header("Cache-Control: max-age=0");
        header("Content-Description: File Transfer");
        header('Content-disposition: attachment; filename=' . basename($filename)); // 文件名
        header("Content-Type: application/zip"); // zip格式的
        header("Content-Transfer-Encoding: binary"); // 告诉浏览器，这是二进制文件
        header('Content-Length: ' . filesize($filedir . $filename)); // 告诉浏览器，文件大小
        @readfile($filedir . $filename);//输出文件;
    }

    private function addFileToZip($path, $name, $zip)
    {
        $handler = opendir($path); //打开当前文件夹由$path指定。
        while (($filename = readdir($handler)) !== false) {
            if (!in_array($filename, ['.', '..'])) { //文件夹文件名字为'.'和‘..'，不要对他们进行操作
                if (is_dir($path . "/" . $filename)) {   // 如果读取的某个对象是文件夹，则递归
                    $this->addFileToZip($path . "/" . $filename, $name . "/" . $filename, $zip);
                } else { //将文件加入zip对象
                    $zip->addFile($path . "/" . $filename, $name . "/" . $filename);
                }
            }
        }
        @closedir($path);
    }
}

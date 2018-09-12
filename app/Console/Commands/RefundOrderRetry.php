<?php

namespace App\Console\Commands;

use App\Events\CurlLogsEvent;
use App\Events\ErrorHandle;
use App\Events\PintuanRefundsEvent;
use App\Models\AppletUpgrade;
use App\Models\RefundOrder;
use GuzzleHttp\Client;
use Illuminate\Console\Command;

class RefundOrderRetry extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'refund:order:retry';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'retry refund when fail';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $key = config('define.inner_config.sign.secret');
        $timestamp = time();
        $param = [
            'access_key'   => $key,
            'access_secret'=> config('define.inner_config.sign.secret'),
            'timestamp'    => $timestamp,
        ];
        $string = '';
        foreach ($param as $k=>$v){
            $string .= $k.'='.$v.'&';
        }
        $string = trim($string,'&');
        $signature = strtoupper(md5($string));
        $client = new Client([
            'headers' => [
                'Content-Type' => 'application/json',
                'x-API-SIGNATURE' => $signature,
                'x-API-KEY' => $key,
                'x-API-TIMESTAMP' => $timestamp,
            ],
            'body'  => '',
        ]);
        $url = config('define.inner_config.api.refund_order_retry');
        try {
            $response = $client->request('POST', $url);
            event(new CurlLogsEvent($response,$client,$url));
        }catch (\Exception $exception){
            event(new ErrorHandle($exception));
        }



    }
}

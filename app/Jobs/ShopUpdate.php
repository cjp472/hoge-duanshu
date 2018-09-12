<?php
/**
 * Created by PhpStorm.
 * User: an
 * Date: 2017/6/23
 * Time: 上午10:13
 */


namespace App\Jobs;


use App\Models\Shop;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ShopUpdate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $shop_id;
    protected $mch_id;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($shop_id,$mch_id)
    {
        $this->shop_id = $shop_id;
        $this->mch_id = $mch_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(){
        $shop = Shop::where('hashid',$this->shop_id)->first();
        if(env('APP_ENV') == 'production'){
            $shop->mch_id = $this->mch_id;
        }else{
            $shop->test_mch_id = $this->mch_id;
        }
        $shop->save();
    }
}

<?php

namespace App\Console\Commands;

use App\Events\ClearMaterial;
use App\Events\SystemEvent;
use App\Models\Shop;
use App\Models\ShopClose;
use App\Models\ShopDisable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class ShopH5HostCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shop:h5:host {--shop_id=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '店铺生成随机域名';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->host_use = [];
        $this->host_exist = config('define.host_exist');
        $page = 1;
        $count = 1000;
        while (1) {
            echo $page."\n";
            $offset = ($page - 1) * $count;
            $query_set = Shop::whereNotNull('h5_host')
                ->orderBy('shop.create_time', 'desc')
                ->orderBy('shop.id', 'desc')
                ->offset($offset)
                ->limit($count)
                ->get();
            $shop_params = [];
            if ($query_set && ($len = count($query_set)) > 0) {
                foreach ($query_set as $item) {
                    $shop_params[] = [
                        'id' => $item->id,
                        'h5_host' => $this->get_host(),
                    ];
                }
                Shop::updateBatch($shop_params);
            }
            if (count($query_set) < $count)
                break;
            $page++;
        }
    }

    private function get_host()
    {
        while (1) {
            $string = get_random_string(5);
            if (!in_array($string, $this->host_use) && !in_array($string, $this->host_use)) {
                $this->host_use[] = $string;
                return $string . '.duanshu.com';
            }
        }
    }




}

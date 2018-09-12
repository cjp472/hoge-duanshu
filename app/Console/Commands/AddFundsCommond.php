<?php

namespace App\Console\Commands;

use App\Models\ShopFunds;
use Illuminate\Console\Command;

class AddFundsCommond extends Command
{

    /**
     * 2018-06-01
     * php artisan funds:add 20 --now=1527847200
     */


    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'funds:add {num} {--now=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '添加短书币';

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
        $now = $this->option('now')? :time();
        $num = $this->argument('num');
        $product_num_str = $num.'个';
        $num = $num * 100;
        $quantity = 1;
        $product_num = $num * $quantity;
        $product_name = '短书币 '.$product_num_str;
        $total_price = 0.01 * 100;
        $unit_price = intval($total_price/$quantity);
        $order_id = time() . mt_rand(111111, 999999);
        $param = [
            'shop_id' => 'j54g72862j3630ed1b',
            'transaction_no' => $order_id,
            'product_type' => 'token',
            'product_name' => $product_name,
            'type' => 'income',
            'unit_price' => $unit_price,
            'quantity' => $quantity,
            'total_price' => $total_price,
            'amount' => $product_num
        ];
        ShopFunds::createFunds($param, $now);

    }
}

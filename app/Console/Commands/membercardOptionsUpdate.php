<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MemberCard;

class membercardOptionsUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'membercard2:memberCardOptionsUpdate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        $validExpire = array_values(MemberCard::SPECOPTIONS);
        $valueSpecMap = [];
        foreach (MemberCard::SPECOPTIONS as $key => $value) {
            $valueSpecMap[$value] = $key;
        };
        $count = MemberCard::count();
        echo "共有".$count."条数据\n";
        $sql = MemberCard::where('is_del',0)->whereIn('expire', $validExpire)->get();
        // $sql = $sql->where('shop_id','j54g72862j3630ed1b');
        $waitToUupdate = [];
        $noNeed = [];
        foreach ($sql as $item) {
            $options = $item->getOptions();
            $options = $options ? $options:[];
            $c = count($options);
            $jsonOp = json_encode($options);
            if($c==1 && $options[0]['price'] == $item->price && MemberCard::SPECOPTIONS[$options[0]['value']]==$item->expire) {
                $optionValue = $options[0]['value'];
                $optionsPrice = $options[0]['price'];
                
                echo "$item->id  ", "$item->title  ", "options数据:$optionValue $optionsPrice  ", "有效期:$item->expire   ", "价格:$item->price",  "  no need\n";
            } else {
                $waitToUupdate[] = $item;
            }   
        }

        $waitToUupdateCount = count($waitToUupdate);
        echo "waitToUupdate $waitToUupdateCount";
        foreach ($waitToUupdate as $item) {
            $options = $item->getOptions();
            $options = $options ? $options:[];
            $c = count($options);
            if($c == 1) {
                $optionValue = $options[0]['value'];
                $optionsPrice = $options[0]['price'];
                echo "$item->title ", "id:$item->id ", "计数:$c ", "原update数据:$optionValue $optionsPrice  ", "有效期:$item->expire   ", "价格:$item->price   update\n";
            } else {
                echo "$item->title ", "id:$item->id ", "计数:$c ",  "有效期:$item->expire  ", "价格:$item->price   new\n";
            }
        }
        foreach ($waitToUupdate as $item) {
            $options = [];
            $option = [
                "id"=> 0,
                "value"=> $valueSpecMap[$item->expire],
                "price" => strVal($item->price)
            ];
            $options[] = $option;
            $item->options = serialize($options);
            $item->save();
        };
    }
}

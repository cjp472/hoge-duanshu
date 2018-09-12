<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Code;
use App\Models\InviteCode;
use App\Models\MemberCard;

class membercardGiftMigrate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'membercard2:inviteCodeMigrate';

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
        $inviteCode = InviteCode
            ::where('content_type','member_card')
            ->where('extra_data','');
        $count1 = $inviteCode->count();
        echo '共有'.$count1."条会员卡赠送数据\n";

        $inviteCodes = $inviteCode->get();

        foreach ($inviteCodes as $item) {
            $membercard = MemberCard::where('hashid', $item->content_id)->first();
            if(!$membercard) {
                echo '店铺 '.$item->shop_id.' 会员卡 '.$item->content_id.' 已删除';
                echo "\n";
                continue;
            }
            
            $item->extra_data = serialize([
                "membercard_option"=> $membercard->getOptions()[0]
            ]);
            echo $item->extra_data;
            $item->save();
        }
    }
}

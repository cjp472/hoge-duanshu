<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2017/7/4
 * Time: 17:13
 */

namespace App\Jobs;

use App\Models\Member;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class WechatMemberUpdate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $id;
    protected $avatar;
    protected $nickname;

    /**
     * WechatMemberUpdate constructor.
     * @param $id
     * @param $avatar
     * @param $nickname
     */
    public function __construct($id,$avatar,$nickname)
    {
        $this->id = $id;
        $this->avatar = $avatar;
        $this->nickname = $nickname;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(){
        $member = Member::where('uid',$this->id)->first();
        $member->nick_name = ctype_space($this->nickname)? DEFAULT_NICK_NAME : $this->nickname; // 对应微信的 nickname
        $member->avatar = $this->avatar ?: ''; // 对应微信的 nickname
        $member->login_time = time();
        $member->save();
    }
}

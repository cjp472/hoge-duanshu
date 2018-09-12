<?php

namespace App\Jobs;

use App\Events\PushTemplateEvent;
use App\Http\Controllers\Admin\OpenPlatform\CoreTrait;
use App\Models\ShopContentRemind;
use App\Models\ShopRemindStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;

class PushContentRemind implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    use CoreTrait;
    protected $type = 'applet';
    protected $shop_id;
    protected $content_id;
    protected $content_type;
    protected $course_title;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($shop_id,$content_id,$course_title,$content_type)
    {
        $this->shop_id = $shop_id;
        $this->content_id = $content_id;
        $this->course_title = $course_title;
        $this->content_type = $content_type;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $types = ShopRemindStatus::where('shop_id',$this->shop_id)->value('types');
        if($types){
            $types = unserialize($types);
            if($types[$this->content_type]) {
                $obj = ShopContentRemind::where([
                    'shop_id' => $this->shop_id,
                    'content_id' => $this->content_id,
                    'content_type' => $this->content_type
                ])->get();

                if (!$obj->isEmpty()) {
                    foreach ($obj as $value) {
                        $accessToken = '';
                        if ('applet' == $value->source) {
                            $accessToken = $this->getAccessToken($this->shop_id);
                        }
                        $value->course_title = $this->course_title;
                        event(new PushTemplateEvent($value, $accessToken));
                    }
                }
            }
        }
    }

    private function getAccessToken($shop_id)
    {
        $this->shop['id'] = $shop_id;
        $authorizationData = $this->getAuthorizerAccessToken();
        $accessToken = $authorizationData['authorizer_access_token'];
        Cache::put('push:applet:'.$shop_id.':access_token',$accessToken,110);
        return $accessToken;
    }
}

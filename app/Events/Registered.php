<?php
/**
 * Created by PhpStorm.
 * User: Janice
 * Date: 2018/5/16
 * Time: 10:32
 */

namespace App\Events;

use Illuminate\Auth\Events\Registered as RegisteredEvent;
use Jenssegers\Agent\Facades\Agent;

class Registered extends RegisteredEvent
{
    public function __construct($user, $seo=['serach_word' => '' ,'search_engine' => ''])
    {
        parent::__construct($user);
        $this->seo = $seo;
        $this->channel = $this->getChannel();
        $this->agent = Agent::browser();
    }

    protected function getChannel()
    {
        if(Agent::isDesktop()){
            return 'desktop';
        }elseif( Agent::isMobile() ){
            return 'mobile';
        }elseif( Agent::isTablet() ){
            return 'tablet';
        }else{
            return 'unknow';
        }
    }
}
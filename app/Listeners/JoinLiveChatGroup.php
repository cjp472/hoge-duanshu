<?php

namespace App\Listeners;

use App\Events\PayEvent;
use App\Events\CurlLogsEvent;
use App\Events\ErrorHandle;
use App\Models\Member;

class JoinLiveChatGroup
{

  /**
   * Handle the event.
   *
   * @param  PayEvent  $event
   * @return void
   */
  public function handle(PayEvent $event)
  { 
    $order = $event->order;
    if($order->content_type == 'live'){
      $this->joinChatGroup($order);
    }
  }

  public function joinChatGroup($order){
    $appId = config('define.inner_config.sign.key');
    $appSecret = config('define.inner_config.sign.secret');
    $url = config('define.python_duanshu.api.add_member_to_chat_group');
    $url = sprintf($url, $order->content_id);
    $membersUid = hg_is_same_member($order->user_id, $order->shop_id);
    $membersid = Member::whereIn('uid', $membersUid)->get()->pluck('id')->toArray();
    $payload = ['members'=> $membersid];
    $client = hg_verify_signature($payload, '', $appId, $appSecret, $order->shop_id);
    try {
      $response = $client->post($url, ['http_errors' => true]);
      $return = $response->getBody()->getContents();
      event(new CurlLogsEvent($return, $client, $url));
    } catch (\Exception $exception) {
      event(new ErrorHandle($exception));
    }
  }
}

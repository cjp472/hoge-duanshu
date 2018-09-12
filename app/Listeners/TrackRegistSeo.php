<?php

namespace App\Listeners;

use App\Models\RegistTrack;
use App\Events\Registered;

class TrackRegistSeo
{
  public function handle(Registered $event)
  {
    $user = $event->user;
    $seo = $event->seo;
    $t = new RegistTrack;
    $t->search_word = $seo['search_word'];
    $t->search_engine = $seo['search_engine'];
    $t->user_id = $user->id;
    $t->device = $event->channel;
    $t->user_agent = '';
    $t->save();
  }
}
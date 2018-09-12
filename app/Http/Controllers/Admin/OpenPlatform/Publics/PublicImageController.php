<?php

namespace App\Http\Controllers\Admin\OpenPlatform\Publics;

use App\Http\Controllers\Admin\OpenPlatform\Publics\PublicBaseController;

class PublicImageController extends PublicBaseController
{
    public function handleImage($event)
    {
        // 只处理客服消息 其他不处理
        return (new CustomerServiceEventController())->handleEvent($event);
    }
}

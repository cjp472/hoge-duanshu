<?php

namespace App\Http\Controllers\Admin\OpenPlatform\Publics;

use App\Http\Controllers\Admin\OpenPlatform\Publics\PublicBaseController;

class PublicTextController extends PublicBaseController
{
    public function handleText($event)
    {
        // 只处理客服消息 其他不处理
        return (new CustomerServiceEventController())->handleEvent($event);
    }
}

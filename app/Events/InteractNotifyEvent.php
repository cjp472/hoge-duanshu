<?php

namespace App\Events;

use App\Models\CommunityNote;
use App\Models\Content;

class InteractNotifyEvent
{

    /**
     * InteractNotifyEvent constructor.
     * @param $data
     * @param $member
     * @param $member_id
     * @param $type
     * @param $message
     */
    public function __construct($data ,$member,$member_id,$type,$message='')
    {
        switch ($data['content_type']){
            case 'note':
                $content = CommunityNote::where(['hashid'=>$data['content_id'],'shop_id'=>$data['shop_id']])
                    ->select('hashid','title','indexpic')
                    ->first();
                $content->indexpic = $content->indexpic ? (serialize(unserialize($content->indexpic)[0])) : '';
                $content->type = 'note';
                break;
            default:
                $content = Content::where(['hashid'=>$data['content_id'],'shop_id'=>$data['shop_id'],'type'=>$data['content_type']])
                    ->select('hashid','type','title','indexpic')
                    ->first();
                break;
        }
        $this->member_id = $member_id;
        $this->type = $type;
        $this->interact_id = $member['id'];
        $this->interact_name = trim($member['nick_name']);
        $this->interact_avatar = trim($member['avatar']);
        $this->content_id = $content ? $content->hashid : '';
        $this->content_type = $content ? $content->type : '';
        $this->content_title = $content ? trim($content->title) : '';
        $this->content_indexpic = $content ? trim($content->indexpic) : '';
        $this->message = trim($message);
        $this->interact_time = time();
    }

}

<?php
/**
 * Created by PhpStorm.
 * User: an
 * Date: 2018/3/23
 * Time: 上午11:39
 */

namespace App\Http\Controllers\OauthService\Oauth2;

use App\Http\Controllers\Admin\BaseController;
use App\Models\Shop;
use App\Models\Content;
use LucaDegasperi\OAuth2Server\Facades\Authorizer;

class ContentController extends BaseController{

    public function getContent(){
        $this->validateWithAttribute([
            'openid'    => 'required|alpha_dash',
            'type'      => 'alpha_dash',
        ]);
        $scopes = Authorizer::getScopes();
        if($scopes['baseinfo']->getId() != 'baseinfo'){
            $this->errorOutput('INVALID_SCOPE');
        }
        $shop = Shop::where('hashid',request('openid'))->first();
        if($shop){
            $types = ['live','column','course'];
            $sql = Content::where(['shop_id'=>$shop->hashid,'type'=>request('type')?:'article','display'=>1])
            ->where(function ($query) {
                $query->where('state',1)->orWhere('state',0);
            })
            ->where('up_time','<', time());
            request('title') && $sql->where('title','like','%'.request('title').'%');
            if(in_array(request('type')?:'article',$types)){
                $sql->orderBy('order_id');
            }
            $data = $sql->orderBy('top','desc')->orderBy('update_time','desc')->paginate(request('count')?:10);
            foreach ($data->items() as $item){
                $item->content_id = $item->hashid;
                $item->up_time = $item->up_time?hg_format_date($item->up_time):0;
                $item->create_time = $item->create_time?hg_format_date($item->create_time):0;
                $item->update_time = $item->update_time?hg_format_date($item->update_time):0;
                if($item->type =='course'){
                    $item->course_type = $item->course?$item->course->course_type:'';
                }
            }
            return $this->output($this->listToPage($data));
        }
        return $this->error('NO_SHOP');
    }





}
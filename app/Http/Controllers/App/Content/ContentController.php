<?php

namespace app\Http\Controllers\App\Content;

use App\Events\CurlLogsEvent;
use App\Events\ErrorHandle;
use App\Http\Controllers\App\InitController;
use App\Jobs\SyncBanner;
use App\Jobs\SyncBatchContents;
use App\Jobs\SyncContents;
use App\Jobs\SyncMemberContents;
use App\Jobs\SyncType;
use App\Models\AppContent;
use App\Models\Banner;
use App\Models\Column;
use App\Models\Comment;
use App\Models\Content;
use App\Models\FailContentSyn;
use App\Models\Member;
use App\Models\Order;
use App\Models\ShopApp;
use App\Models\SyncComment;
use App\Models\SyncMember;
use App\Models\SyncOrder;
use App\Models\Type;

class ContentController extends InitController
{
    /**
     * 同步数据到app
     */
    public function appContentSync()
    {
        $this->validateWithAttribute(
            ['shop_id' => 'required|alpha_dash'],['shop_id'=>'店铺id']
        );
        $shop_app = ShopApp::where(['shop_id'=>request('shop_id')])->first();
        if ($shop_app) {
            $this->syncType();   //同步导航

            $this->syncColumn();  //同步专栏
            $this->syncArticle();  //同步图文
            $this->syncAudio();    //同步音频
            $this->syncVideo();    //同步视频
            $this->syncLive();  //同步直播
            $this->syncMember();       //同步会员
            $this->syncOrderToApp();      //同步购买关系
            $this->syncCreateComment();    //同步评论
            $this->syncBanner();   //同步导航
        }
        return $this->output(['success'=>1]);
    }

    private function syncColumn()
    {
        $url = config('define.batch_sync_content.column').'?shop_id='.request('shop_id');
        $data = $this->dispatch(new SyncBatchContents($url));
        return $data;
    }

    private function syncArticle()
    {
        $url = config('define.batch_sync_content.article').'?shop_id='.request('shop_id');
        $data = $this->dispatch(new SyncBatchContents($url));
        return $data;
    }

    private function syncAudio()
    {
        $url = config('define.batch_sync_content.audio').'?shop_id='.request('shop_id');
        $data = $this->dispatch(new SyncBatchContents($url));
        return $data;
    }

    private function syncVideo()
    {
        $url = config('define.batch_sync_content.video').'?shop_id='.request('shop_id');
        $data = $this->dispatch(new SyncBatchContents($url));
        return $data;
    }

    private function syncLive()
    {
        $url = config('define.batch_sync_content.live').'?shop_id='.request('shop_id');
        $data = $this->dispatch(new SyncBatchContents($url));
        return $data;
    }

    private function syncMember()
    {
        $url = config('define.batch_sync_content.member').'?shop_id='.request('shop_id');
        $data = $this->dispatch(new SyncBatchContents($url));
        return $data;
    }

    private function syncOrderToApp()
    {
        $url = config('define.batch_sync_content.order').'?shop_id='.request('shop_id');
        $data = $this->dispatch(new SyncBatchContents($url));
        return $data;
    }

    private function syncCreateComment()
    {
        $url = config('define.batch_sync_content.comment').'?shop_id='.request('shop_id');
        $data = $this->dispatch(new SyncBatchContents($url));
        return $data;
    }

    private function syncType()
    {
        $url = config('define.batch_sync_content.type').'?shop_id='.request('shop_id');
        $data = $this->dispatch(new SyncBatchContents($url));
        return $data;
    }

    private function syncBanner()
    {
        $url = config('define.batch_sync_content.banner').'?shop_id='.request('shop_id');
        $data = $this->dispatch(new SyncBatchContents($url));
        return $data;
    }

    /**
     * 专栏数据
     * @return mixed|\Psr\Http\Message\ResponseInterface
     */
    public function createColumn()
    {
        $this->validateWithAttribute(
            ['shop_id' => 'required|alpha_dash'],['shop_id'=>'店铺id']
        );
        $shop_app = ShopApp::where(['shop_id'=>request('shop_id')])->first();
        if($shop_app){
            $url = str_replace('{app_id}', $shop_app->appkey, config('define.dingdone.api.bulk_create_or_update'));
            $size = SYNC_COUNT;
            $column = Column::where(['shop_id'=>request('shop_id'),'state'=>1,'display'=>1])
                ->select('id','hashid', 'title', 'indexpic', 'finish', 'price', 'brief','subscribe')
                ->get();
            $chunk_column = $column->chunk($size);
            if($chunk_column->isNotEmpty()) {
                foreach ($chunk_column as $item_column) {
                    dispatch(new SyncContents($item_column, $url, $shop_app, 'column'));
                }
                return $this->output(['success' => 1]);
            }
        }


    }


    /**
     * 图文数据
     * @return mixed|\Psr\Http\Message\ResponseInterface
     */
    public function createArticle()
    {
        $this->validateWithAttribute(
            ['shop_id' => 'required|alpha_dash'],['shop_id'=>'店铺id']
        );
        $shop_app = ShopApp::where(['shop_id'=>request('shop_id')])->first();
        if($shop_app){
            $url = str_replace('{app_id}', $shop_app->appkey, config('define.dingdone.api.bulk_create_or_update'));
            $size = SYNC_COUNT;
            $article = Content::where(['shop_id' =>request('shop_id'),'type' => 'article','display'=>1])
                ->where(function ($query) {
                    $query->where('state',1)->orWhere('state',0);
                })
                ->where('up_time','<', time())
                ->select('hashid','title','price','brief','indexpic','column_id','payment_type','subscribe')
                ->get();
            $chunk_article = $article->chunk($size);
            if($chunk_article->isNotEmpty()) {
                foreach ($chunk_article as $item_article) {
                    dispatch(new SyncContents($item_article, $url, $shop_app, 'article'));
                }
            }
            return $this->output(['success' => 1]);

        }
    }


    /**
     * 视频
     * @return mixed
     */
    public function createVideo()
    {
        $this->validateWithAttribute(
            ['shop_id' => 'required|alpha_dash'],['shop_id'=>'店铺id']
        );
        $shop_app = ShopApp::where(['shop_id'=>request('shop_id')])->first();
        if($shop_app){
            $url = str_replace('{app_id}', $shop_app->appkey, config('define.dingdone.api.bulk_create_or_update'));
            $size = SYNC_COUNT;
            $video = Content::where(['shop_id' =>request('shop_id'),'type' => 'video','display'=>1])
                ->where(function ($query) {
                    $query->where('state',1)->orWhere('state',0);
                })
                ->where('up_time','<', time())
                ->select('hashid', 'title', 'price', 'brief', 'indexpic', 'column_id','payment_type','subscribe')
                ->get();
            $chunk_video = $video->chunk($size);
            if($chunk_video->isNotEmpty()) {
                foreach ($chunk_video as $item_video) {
                    dispatch(new SyncContents($item_video, $url, $shop_app, 'video'));
                }
            }
        }
        return $this->output(['success' => 1]);

    }


    /**
     * audio类型
     * @return \Illuminate\Http\JsonResponse
     */
    public function createAudio()
    {
        $this->validateWithAttribute(
            ['shop_id' => 'required|alpha_dash'],['shop_id'=>'店铺id']
        );
        $shop_app = ShopApp::where(['shop_id'=>request('shop_id')])->first();
        if($shop_app){
            $url = str_replace('{app_id}', $shop_app->appkey, config('define.dingdone.api.bulk_create_or_update'));
            $size = SYNC_COUNT;
            $audio = Content::where(['shop_id' =>request('shop_id'),'type' => 'audio','display'=>1])
                ->where(function ($query) {
                    $query->where('state',1)->orWhere('state',0);
                })
                ->where('up_time','<', time())
                ->select('hashid', 'title', 'price', 'brief', 'indexpic', 'column_id','payment_type','subscribe')
                ->get();
            $chunk_audio = $audio->chunk($size);
            if($chunk_audio->isNotEmpty()) {
                foreach ($chunk_audio as $item_audio) {
                    dispatch(new SyncContents($item_audio, $url, $shop_app, 'audio'));
                }
            }
        }
        return $this->output(['success' => 1]);
    }


    public function createLive()
    {
        $this->validateWithAttribute(
            ['shop_id' => 'required|alpha_dash'],['shop_id'=>'店铺id']
        );
        $shop_app = ShopApp::where(['shop_id'=>request('shop_id')])->first();
        if($shop_app){
            $url = str_replace('{app_id}', $shop_app->appkey, config('define.dingdone.api.bulk_create_or_update'));
            $size = SYNC_COUNT;
            $live = Content::join('live','live.content_id','=','content.hashid')
                ->where(['content.type'=>'live','content.display'=>1,'content.shop_id'=>request('shop_id')])
                ->where(function ($query) {
                    $query->where('content.state',1)->orWhere('content.state',0);
                })
                ->where('content.up_time' ,'<', time())
                ->select('hashid','title','price','content.brief','indexpic','column_id','payment_type','shop_id','subscribe')
                ->get();
            $chunk_live = $live->chunk($size);
            if($chunk_live->isNotEmpty()) {
                foreach ($chunk_live as $item_live) {
                    dispatch(new SyncContents($item_live, $url, $shop_app, 'live'));
                }
            }

        }
        return $this->output(['success' => 1]);

    }


    /**
     * 导入会员信息
     * @return mixed
     */
    public function createMember()
    {
        $this->validateWithAttribute(
            ['shop_id' => 'required|alpha_dash'],['shop_id'=>'店铺id']
        );
        $shop_app = ShopApp::where(['shop_id'=>request('shop_id')])->first();
        if($shop_app){
            $total = Member::where('shop_id', request('shop_id'))->count();
            if($total){
                $size = SYNC_COUNT;
                $member = Member::where('shop_id', request('shop_id'))
                    ->select('uid', 'nick_name', 'avatar', 'sex', 'mobile', 'email')
                    ->get();
                $chunk_member = $member->chunk($size);
                foreach ($chunk_member as $item_member){
                    dispatch(new SyncMemberContents($item_member, $shop_app));
                }
                return $this->output(['success' => 1]);
            }
        }
    }


    /**
     * 评论
     * @return \Illuminate\Http\JsonResponse
     */
    public function createComment()
    {
        $this->validateWithAttribute(
            ['shop_id' => 'required|alpha_dash'],['shop_id'=>'店铺id']
        );
        $comment = Comment::where('shop_id', request('shop_id'))->get();
        $shop_app = ShopApp::where(['shop_id'=>request('shop_id')])->first();
        $url = str_replace('{app_id}', $shop_app->appkey, config('define.dingdone.api.comments'));
        if (!$comment->isEmpty() && $shop_app) {
            $size = SYNC_COUNT;
            $chunk_comment = $comment->chunk($size);
            foreach ($chunk_comment as $item_comment){
                dispatch(new \App\Jobs\SyncComment($item_comment,$url,$shop_app,request('shop_id')));
            }
        }
        return $this->output(['success'=>1]);


    }


    /**
     * 购买同步关系
     * @return \Illuminate\Http\JsonResponse
     */
    public function orderToApp()
    {
        $this->validateWithAttribute(
            ['shop_id' => 'required|alpha_dash'],['shop_id'=>'店铺id']
        );
        $shop_app = ShopApp::where(['shop_id'=>request('shop_id')])->first();
        if ($shop_app) {
            //所有订单
            $order = Order::where(['shop_id'=>request('shop_id'),'pay_status'=>1])->select('user_id as uid','content_id','content_type','shop_id')->get();
            $url = str_replace('{app_id}', $shop_app->appkey, config('define.dingdone.api.add_user_to_group'));
            $data = $this->validateOrder($order,$shop_app);
            $total = count($data);
            if ($total > 0) {
                $size = SYNC_COUNT;
                $chunk_order = array_chunk($data,$size);
                foreach ($chunk_order as $item_order) {
                    dispatch(new \App\Jobs\SyncOrder($item_order,$url,$shop_app));
                }
                return $this->output(['success' => 1]);
            }
        }
    }

    /**
     * 数据整理
     *
     * @param $order
     * @param $shop_app
     * @return array
     */
    private function validateOrder($order,$shop_app)
    {
        $syncOrder = SyncOrder::where('shop_id',request('shop_id'))->select('uid','content_id','group_id')->get()->toArray();//已同步购买记录
        $data = [];
        foreach ($order as $item) {
            //根据类型取content_id
            if ($item->content_type == 'column') {
                $item->app_content_id = $item->belongsToColumn && $item->belongsToColumn->app_content ? $item->belongsToColumn->app_content->app_content_id : '';
            } else {
                $item->app_content_id = ($item->belongsToContent && $item->belongsToContent->app_content) ? $item->belongsToContent->app_content->app_content_id : '';
            }
            $group_id = unserialize($shop_app->group_id);
            if($item->content_type != 'course') {
                $item->group_id = $group_id[$item->content_type];
                $info = ['uid' => $item->uid, 'content_id' => $item->app_content_id, 'group_id' => $item->group_id];
                //判断不存在记录
                if (!in_array($info, $syncOrder) && $item->app_content_id && $item->uid && $item->group_id) {
                    $data[] = $info;
                }
            }
        }
        return $data;
    }

    /**
     * 导航同步
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function createType()
    {
        $this->validateWithAttribute([
                'shop_id' => 'required|alpha_dash'
            ],[
                'shop_id'=>'店铺id'
            ]
        );
        $shop_app = ShopApp::where(['shop_id'=>request('shop_id')])->first();
        $url = str_replace('{app_id}', $shop_app->appkey, config('define.dingdone.api.bulk_create_or_update'));
        if ($shop_app) {
            $type = Type::where(['shop_id'=>request('shop_id')])->select('id','title','indexpic','status')->get();
            if (!$type->isEmpty()) {
                dispatch(new SyncType($type,$url,$shop_app));
            }
        }
        return $this->output(['success' => 1]);
    }

    /**
     * 轮播图同步
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function createBanner()
    {
        $this->validateWithAttribute([
            'shop_id' => 'required|alpha_dash'
        ],[
                'shop_id'=>'店铺id'
            ]
        );
        $shop_app = ShopApp::where(['shop_id'=>request('shop_id')])->first();
        $url = str_replace('{app_id}', $shop_app->appkey, config('define.dingdone.api.bulk_create_or_update'));
        if ($shop_app) {
            $banner = Banner::where(['shop_id'=>request('shop_id'),'state'=>1])->get();
            if (!$banner->isEmpty()) {
                dispatch(new SyncBanner($banner,$url,$shop_app));
            }
        }
        return $this->output(['success' => 1]);
    }


}

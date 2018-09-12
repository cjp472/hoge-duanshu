<?php
/**
 * |------------------------------------------------------------------------------
 * | 短书同步公众号文章
 * |------------------------------------------------------------------------------
 * | 作者 | RU
 * | 时间 | 2017-9-8 16:32:32
 * |------------------------------------------------------------------------------
 */
namespace App\Http\Controllers\Admin\OpenPlatform\Servers;

use App\Events\CreateWechatArticleEvent;
use App\Http\Controllers\Admin\BaseController;
use App\Http\Controllers\Admin\OpenPlatform\CoreTrait;
use App\Http\Controllers\Admin\OpenPlatform\Publics\{
    MaterialController as Material, QcloudController as Qcloud
};
use App\Jobs\CachePublicArticle;
use App\Models\Article;
use App\Models\Content;
use App\Models\OpenPlatformPublic;
use App\Models\PublicArticle;
use Illuminate\Support\Facades\Redis;
use Vinkla\Hashids\Facades\Hashids;

class ArticleController extends BaseController
{
    use CoreTrait;

    // 获取店铺下的公众号信息
    public function publicList()
    {
        $where = ['shop_id' => $this->shop['id']];
        $open_platform_public = OpenPlatformPublic::select('appid', 'create_time')->where($where)->get();
        foreach ($open_platform_public as $k => $v) {
            $info = $this->getAuthorizerInfo($v->appid)['authorizer_info'];
            $open_platform_public[$k]['info'] = $info['nick_name'];
            $open_platform_public[$k]['head_img'] = $info['head_img'] ?? '';
        }
        return $this->output($open_platform_public);
    }

    // 选择的图文列表
    public function newsList()
    {
        $this->validateWithAttribute([
            'appid' => 'required',
            'count' => 'numeric | min:1',
            'page'  => 'numeric | min:1'
        ]);
        $app_id = explode(',', request('appid'));
        $count = 20;
        $page = request('page') ?? 1;
        $lists = $news = [];
        $total = 0;
        foreach ($app_id as $ke => $vo) {
            $lists = array_merge($lists, $this->getNewsList($vo));
            $material_count = (new Material($this->shop['id']))->getMaterialCount($vo);
            if($material_count && isset($material_count['news_count'])){
                $total += $material_count['news_count'];
            }
        }

//        $total = count($lists);
//        $lists = collect($lists)->chunk($count)->toArray();
        $last_page = $total ? ceil($total / $count) : 1;
//        $ret['data'] = $page <= $last_page ? $lists[((integer)$page - 1)] : [];
        $ret['data'] = $lists;
        $this->get_pageinfo($ret, ['current_page' => (integer)$page, 'total' => $total, 'last_page' => $last_page]);
        return $this->output($ret);
    }

    // 将公众号图文推到短书

    private function getNewsList($app_id)
    {
        $news = $save_info = $lists = [];
        $news_list = (new Material($this->shop['id']))->getMaterialList($app_id, 'news',(request('page') > 0 ? request('page')-1 : 0)*20,20);
        if(isset($news_list['errcode'])){
            if($news_list['errcode'] == '45009'){
                $this->error('material_api_limit');
            }
            return [];
        }
        if($news_list) {
            $lists = isset($news_list['item']) ? $news_list['item'] : [];
//            if ($news_list['total_count'] > $news_list['item_count']) {
//                $times = ceil($news_list['total_count'] / ARTICLE_NUMBER);
//                for ($i = 1; $i < $times; $i++) {
//                    $info = (new Material($this->shop['id']))->getMaterialList($app_id, 'news', (ARTICLE_NUMBER * $i), ARTICLE_NUMBER) ? : [];
//                    $new_lists = isset($info['item']) ? $info['item'] : [];
//                    $new_lists && $lists = array_merge($lists, $new_lists);
//                }
//            }
            if($lists) {
                foreach ($lists ?? [] as $k => $v) {
                    foreach ($v['content']['news_item'] as $ke => $vo) {
                        $vo['create_time'] = date('Y-m-d H:i:s', $v['update_time']);
                        $this->unsetArr($vo, [
                            'author',
                            'content_source_url',
                            'thumb_media_id',
                            'show_cover_pic',
                            'need_open_comment',
                            'only_fans_can_comment'
                        ]);
                        $vo['appid'] = $app_id;
                        $media_id = md5($vo['url']);
                        $vo['media_id'] = $media_id;
                        $news[] = $vo;
                        dispatch((new CachePublicArticle($vo,$this->shop['id']))->onQueue(DEFAULT_QUEUE));
                    }
                }
            }
        }
        return $news;
    }

    private function unsetArr(&$foo, $array)
    {
        foreach ($array as $v) {
            unset($foo[$v]);
        }
    }

    private function get_pageinfo(&$info = [], $params = [])
    {
        $info['pageinfo'] = [
            'current_page' => $params['current_page'],
            'last_page'    => $params['last_page'],
            'total'        => $params['total']
        ];
        return $info;
    }

    public function put2Ds()
    {
        $this->validateWithAttribute([
            'content'   => 'required|regex:/\w{32}(,\w(32))*$/',
        ]);
        $content = explode(',',request('content'));

        foreach ($content as $k => $v) {
            event(new CreateWechatArticleEvent($v,$this->shop['id'],$this->user));
        }
        $return = ['status' => '1', 'message' => '上架成功'];

        return $this->output($return);

//        $content = request('content');
//        $error = $return = [];
//        foreach ($content as $k => $v) {
//            $ret = $this->saveNewsToDs($v);
//            if (!$ret) {
//                $error[] = $v['title'];
//            }
//        }
//        if ($error) {
//            $return = ['status' => '0', 'message' => $error];
//        } else {
//            $return = ['status' => '1', 'message' => '上架成功'];
//        }
//        return $this->output($return);

    }

    private function saveNewsToDs($params)
    {
        $time = time();
        $ret = null;
        // content --start
        $content = new Content;
        $content->shop_id = $this->shop['id'];
        $content->title = $params['title'];
        $content->indexpic = $params['thumb_url'] ? (new Qcloud)->uploadUrl($params['thumb_url']) : '';
        $content->payment_type = 3;
        $content->column_id = 0;
        $content->price = 0;
        $content->up_time = $time;
        $content->create_time = time();
        $content->update_time = time();
        $content->create_user = $this->user['id'];
        $content->update_user = 0;
        $content->state = 1;
        $content->display = 1;
        $content->is_lock = 0;
        $content->type = 'article';
        $content->comment_count = 0;
        $content->view_count = 0;
        $content->subscribe = 0;
        $content->play_count = 0;
        $content->end_play_count = 0;
        $content->share_count = 0;
        $content->brief = $params['digest'];
        $ret = $content->save();
        if ($ret) {
            $content->hashid = Hashids::encode($content->id);
            $content->save();
            // article --start
            $article = new Article;
            $article->content_id = $content->hashid;
            $article->content = $this->switchImg($params['content']);
            $ret = $article->save();
        }
        return $ret;
    }

    private function switchImg($content)
    {
        preg_match_all('/ (data-)?src=\"(.*?)\"/', $content, $images);
        foreach ($images[2] as $k => $url) {
            $img = (new Qcloud)->uploadUrl($url);
            // 替换内容中的图片链接
            $content = str_replace($images[0][$k], ' src="'. $img .'"', $content);
        }
        return $content;
    }

}

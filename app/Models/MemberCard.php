<?php
/**
 * Created by PhpStorm.
 * User: Allen
 * Date: 17/12/21
 * Time: 下午4:31
 */

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Models\Content;
use App\Models\Community;
use App\Models\Column;
use App\Models\Course;

class MemberCard extends Model
{
    protected $table = 'member_card';

    protected $fillable = ['sales_total', 'unit_member', 'promoter_rate', 'invite_rate', 'is_participate_promotion'];

    const INDEX_PIC_DEFAULT = 'http://duanshu-1253562005.cossh.myqcloud.com/dsapply/mcard.jpg';
    const INDEXPIC = ['host'=>'http://duanshu-1253562005.cossh.myqcloud.com', 'file'=>'/dsapply/mcard.jpg', 'query'=>''];
    const STYLEINDEXPIC = [
        '1'=>['host'=>'http://duanshu-1253562005.cossh.myqcloud.com', 'file'=>'/dsapply/mcard-black.png', 'query'=>''],
        '2'=>['host'=>'http://duanshu-1253562005.cossh.myqcloud.com', 'file'=>'/dsapply/mcard-silver.png', 'query'=>''],
        '3'=>['host'=>'http://duanshu-1253562005.cossh.myqcloud.com', 'file'=>'/dsapply/mcard-gold.png', 'query'=>''],
        '4'=>['host'=>'http://duanshu-1253562005.cossh.myqcloud.com', 'file'=>'/dsapply/mcard-green.png', 'query'=>''],
        '5'=>['host'=>'http://duanshu-1253562005.cossh.myqcloud.com', 'file'=>'/dsapply/mcard-blue.png', 'query'=>'']
    ];
    const SPECKEYS = ['1个月', '3个月', '半年', '1年', '1天', '7天'];
    const SPECOPTIONS = ['1个月'=>1, '3个月'=>3, '半年'=>6, '1年'=>12]; # 规格选项，为了兼容保留下来
    const NEWSPECOPTIONS = [
                            '1个月' => '+1 months',
                            '3个月' => '+3 months',
                            '半年' => '+6 months',
                            '1年' => '+1 years',
                            '1天' => '+1 days',
                            '7天' => '+7 days'
                        ]; # 新的规格选项

    public function record(){
        return $this->hasMany('App\Models\CardRecord','card_id','hashid');
    }
    public function serialize() {
        $this->options = $this->options ? unserialize($this->options) : [];
        $this->up_time = $this->up_time ? hg_format_date($this->up_time) : '';
        $this->subscribe = $this->record?count($this->record):0;
        $this->indexpic = $this::STYLEINDEXPIC[$this->style] ? $this::STYLEINDEXPIC[$this->style] : $this::INDEXPIC;
        $this->makeHidden(['record']);
    }

     public function getOptions() {
        return unserialize($this->options);
    }

    public function getOption($optionId) {
        $options = $this->getOptions();
        for ($i=0; $i < count($options); $i++) { 
            if ($options[$i]['id'] == $optionId) {
                return $options[$i];
            }
        }
        return null;
    }

    public function setIndexPic() {
        // 规格对应有效期
        $this->indexpic = $this::STYLEINDEXPIC[$this->style] ? $this::STYLEINDEXPIC[$this->style] : $this::INDEXPIC;
    }

    public function getOptionValueExpirebyId($optionId) {
        // 规格对应有效期
        $option = $this->getOption($optionId);
        return $this::NEWSPECOPTIONS[$option['value']];
    }

    static function getOptionValueExpire($specValue) {
        // 规格对应有效期
        return self::NEWSPECOPTIONS[$specValue];
    }

    public function nameAtBuy($optionId) { // 购买时的商品名
        return $this->title.'（'.$this->getOption($optionId)['value'].'）';
    }

    public function validOption($optionId) {
        return boolval($this->getOption($optionId));
    }

    public function optionPrice($optionId) {
        return $this->getOption($optionId)['price'];
    }

    static function joinMemberCard($content_type, $content_id, $shop_id, $join) {
        // 是否适用会员卡
        // content_type: community 小社群 content 普通内容 （图文，视频等）
        // content_id: 内容id
        // join: 是否适用 true 适用，false 不适用
        switch ($content_type) {
            case 'column':
                $object = Column::where(['shop_id'=>$shop_id, 'hashid'=>$content_id])->firstOrFail();
                break;
            case 'community':
                $object = Community::where(['shop_id'=>$shop_id, 'hashid'=>$content_id])->firstOrFail();
                break;
            case 'course':
                $object = Course::where(['shop_id'=>$shop_id, 'hashid'=>$content_id])->firstOrFail();
                break;
            default:
                $object = Content::where(['shop_id'=>$shop_id, 'hashid'=>$content_id])->firstOrFail();
                break;
        }
        $object->join_membercard = $join ? 1: 0;
        $object->save();
        return;
    }

    static function availableMembercards($shopid) {
        return parent::where(['shop_id'=>$shopid, 'status' => 1, 'is_del' => 0]);
    }
}
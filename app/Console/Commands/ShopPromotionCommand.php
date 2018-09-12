<?php

namespace App\Console\Commands;

use App\Models\Column;
use App\Models\Content;
use App\Models\Course;
use App\Models\MemberBindPromoter;
use App\Models\MemberCard;
use App\Models\PromotionContent;
use App\Models\PromotionRate;
use App\Models\PromotionRecord;
use App\Models\PromotionShop;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ShopPromotionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shop:promotion:upgrade';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '店铺推广升级脚本';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        echo "start\n";
//        $this->promotion_default();
        echo "promotion_default done\n";
//        $this->promotion_content_rate();
        echo "promotion_content_rate done\n";
//        $this->promotion_shop_content();
        echo "promotion_shop_content done\n";
//        $this->member_bind_promoter();
        echo "member_bind_promoter done\n";
//        $this->promotion_sales();
        echo "end\n";
    }


    /**
     * 迁移店铺推广默认佣金比例
     */
    public function promotion_default()
    {
        $query_set = PromotionShop::where('promotion_rate_id', '=', 0)->where('shop_id', '!=', '')->get();
        foreach ($query_set as $item) {
            $params[] = [
                'shop_id' => $item->shop_id,
                'promoter_rate' => $item->money_percent >= 0 ? $item->money_percent : 0,
                'invite_rate' => $item->visit_percent >= 0 ? $item->visit_percent : 0,
            ];
        }
        if (isset($params) && count($params) > 0) {
            PromotionRate::insert($params);
            $promotion_rates = PromotionRate::pluck('id', 'shop_id')->toArray();
            foreach ($query_set as $item) {
                if (isset($promotion_rates[$item->shop_id])) {
                    $update[] = [
                        'id' => $item->id,
                        'promotion_rate_id' => $promotion_rates[$item->shop_id],
                    ];
                }
            }
            if (isset($update) and count($update) > 0) {
                $this->updateBatch('promotion_shop', $update);
            }
        }
    }

    /**
     * 迁移推广商品佣金比例
     */
    public function promotion_content_rate()
    {
        $query_set = PromotionContent::select(['promotion_content.id', 'promotion_content.shop_id', 'promotion_content.money_percent as promoter_rate', 'promotion_content.visit_percent as invite_rate',
            'promotion_rate.id as promotion_rate_id', 'promotion_rate.promoter_rate as promoter_rate_default', 'promotion_rate.invite_rate as invite_rate_default'])
            ->where('promotion_content.promotion_rate_id', '=', 0)
            ->leftJoin('promotion_shop', 'promotion_shop.shop_id', 'promotion_content.shop_id')
            ->leftJoin('promotion_rate', 'promotion_rate.id', 'promotion_shop.promotion_rate_id')
            ->get();
        $create_list = [];
        $update_list = [];
        foreach ($query_set as $item) {
            if ($item->promoter_rate >= 0 || $item->invite_rate >= 0) {
                $create_list[] = [
                    'shop_id' => $item->shop_id,
                    'promoter_rate' => $item->promoter_rate >= 0 ? $item->promoter_rate : $item->promoter_rate_default,
                    'invite_rate' => $item->invite_rate >= 0 ? $item->invite_rate : $item->promoter_rate_default,
                    'promotion_content_id' => $item->id,     //迁移数据用, 之后可以删除
                ];
            } else {
                $update_list[] = [
                    'id' => $item->id,
                    'promotion_rate_id' => $item->promotion_rate_id,
                ];
            }
        }
        if (isset($create_list) and count($create_list) > 0) {
            PromotionRate::insert($create_list);
            $promotion_rates = PromotionRate::where('promotion_content_id', '!=', 0)->pluck('id', 'promotion_content_id')->toArray();
            foreach ($query_set as $item) {
                if (isset($promotion_rates[$item->id])) {
                    $update_list[] = [
                        'id' => $item->id,
                        'promotion_rate_id' => $promotion_rates[$item->id],
                    ];
                }
            }
        }
        if (isset($update_list) and count($update_list) > 0) {
            $this->updateBatch('promotion_content', $update_list);
        }
    }

    /**
     * 迁移推广店铺商品数据, 默认是不参与推广
     */
    private function promotion_shop_content()
    {
        $shop_ids_list = PromotionShop::where('shop_id', '!=', '')->pluck('shop_id')->toArray();
        foreach ($shop_ids_list as $id){
            $shop_ids = [
                $id
            ];
            echo 'id:'.$id."\n";
            if ($shop_ids && count($shop_ids) > 0) {
                $page = 1;
                $count = 1000;
                $promotion_content_list = [];
                while (1) {
                    $offset = ($page - 1) * $count;
                    $query_set = Content::select('content.shop_id', 'content.hashid', 'content.title', 'content.type', 'promotion_shop.promotion_rate_id')
                        ->join('promotion_content', function ($join) {
                            $join->on('promotion_content.shop_id', '=', 'content.shop_id')
                                ->whereColumn('promotion_content.content_id', 'content.hashid')
                                ->whereColumn('promotion_content.content_type', 'content.type');
                        }, '', '', 'left')
                        ->leftJoin('promotion_shop', 'promotion_shop.shop_id', 'content.shop_id')
                        ->whereIn('content.shop_id', $shop_ids)
                        ->whereIn('content.type', ['article', 'audio', 'video', 'live'])
                        ->where('content.column_id', '=', 0)
                        ->whereNull('promotion_content.id')
                        ->offset($offset)->limit($count)->get();
                    foreach ($query_set as $item) {
                        $promotion_content_list[] = [
                            'shop_id' => $item->shop_id,
                            'content_id' => $item->hashid,
                            'content_type' => $item->type,
                            'content_title' => $item->title,
                            'promotion_rate_id' => $item->promotion_rate_id,
                            'is_participate' => 0,
                        ];
                    }
                    if (count($query_set) < $count)
                        break;
                    $page++;
                }
                echo "content done\n";
                $types = ['column', 'course', 'member_card'];
                foreach ($types as $type) {
                    $page = 1;
                    $table = $type;
                    if ($type == 'column') {
                        $query_set = Column::select($table . '.shop_id', $table . '.hashid', $table . '.title', 'promotion_shop.promotion_rate_id');
                    } else if ($type == 'course') {
                        $query_set = Course::select($table . '.shop_id', $table . '.hashid', $table . '.title', 'promotion_shop.promotion_rate_id');
                    } else if ($type == 'member_card') {
                        $query_set = MemberCard::select($table . '.shop_id', $table . '.hashid', $table . '.title', 'promotion_shop.promotion_rate_id');
                    }
                    if (isset($query_set)) {
                        while (1) {
                            $offset = ($page - 1) * $count;
                            $query_set = $query_set->join('promotion_content', function ($join) use ($table) {
                                $join->on('promotion_content.shop_id', '=', $table . '.shop_id')
                                    ->whereColumn('promotion_content.content_id', $table . '.hashid');
                            }, '', '', 'left')
                                ->leftJoin('promotion_shop', 'promotion_shop.shop_id', $table . '.shop_id')
                                ->whereIn($table . '.shop_id', $shop_ids)
                                ->whereNull('promotion_content.id');
                            if($type == 'member_card')
                            {
                                $query_set = $query_set->where([$table . '.is_del' => 0]);
                            }
                            $query_set = $query_set->offset($offset)->limit($count)->get();
                            foreach ($query_set as $item) {
                                $promotion_content_list[] = [
                                    'shop_id' => $item->shop_id,
                                    'content_id' => $item->hashid,
                                    'content_type' => $type,
                                    'content_title' => $item->title,
                                    'promotion_rate_id' => $item->promotion_rate_id,
                                    'is_participate' => 0,
                                ];
                            }
                            if (count($query_set) < $count)
                                break;
                            $page++;
                        }
                    }
                }
//            var_dump($promotion_content_list);
                if (count($promotion_content_list) > 0) {
                     PromotionContent::insert($promotion_content_list);
                }
            }
        }

    }

    /**
     * 迁移推广员用户
     */
    private function member_bind_promoter()
    {
        $page = 1;
        $count = 1000;
        $datetime = date('Y-m-d H:i:s');
        $params = [];
        while (1) {
            $offset = ($page - 1) * $count;
            $query_set = PromotionRecord::select(['promotion_record.shop_id', 'promotion_record.promotion_id', 'promotion_record.buy_id'])
                ->selectRaw('group_concat(hg_promotion_record.create_time) as times')
                ->join('member_bind_promoter', function ($join) {
                    $join->on('member_bind_promoter.shop_id', '=', 'promotion_record.shop_id')
                        ->whereColumn('member_bind_promoter.promoter_id', 'promotion_record.promotion_id')
                        ->whereColumn('member_bind_promoter.member_id', 'promotion_record.buy_id');
                }, '', '', 'left')
                ->orderBy('promotion_record.id', 'asc')
                ->groupBy('promotion_record.shop_id')
                ->groupBy('promotion_record.promotion_id')
                ->groupBy('promotion_record.buy_id')
                ->whereNull('member_bind_promoter.id')
                ->offset($offset)->limit($count)->get();
            foreach ($query_set as $item) {
                if ($item->shop_id && $item->promotion_id && $item->buy_id) {
                    $times = explode(',', $item->times);
                    rsort($times);
                    $create_time = $times[0];
                    $params[] = [
                        'shop_id' => $item->shop_id,
                        'member_id' => $item->buy_id,
                        'promoter_id' => $item->promotion_id,
                        'bind_timestamp' => $create_time,
                        'invalid_timestamp' => $create_time,
                        'state' => 0,
                        'created_at' => $datetime,
                        'updated_at' => $datetime,
                    ];
                }
            }
            if (count($query_set) < $count)
                break;
            $page++;
        }
        if (count($params) > 0) {
            MemberBindPromoter::insert($params);
        }
    }

    private function promotion_sales()
    {
        $query_set = PromotionRecord::select(['promotion_content.id'])
            ->selectRaw('count(*) as promotion_sales_total')
            ->join('promotion_content', function ($join) {
                $join->on('promotion_content.shop_id', '=', 'promotion_record.shop_id')
                    ->whereColumn('promotion_content.content_id', 'promotion_record.content_id')
                    ->whereColumn('promotion_content.content_type', 'promotion_record.content_type');
            }, '', '', 'left')
            ->groupBy('promotion_record.shop_id')
            ->groupBy('promotion_record.content_id')
            ->groupBy('promotion_record.content_type')
            ->where(['promotion_record.state' => 1])
            ->whereNotNull('promotion_content.id')
            ->get();
        $list = $query_set->toArray();
        $this->updateBatch('promotion_content', $list);
    }

    private function promotion_record_commission(){
        PromotionRecord::update(['promoter_commission' => 'money_percent*deal_money/100', 'invite_commission' => 'visit_percent*deal_money/100']);
    }

    //批量更新
    private function updateBatch($table, $multipleData = [], $where = [])
    {
        if (empty($multipleData)) {
            throw new Exception("数据不能为空");
        }
        $tableName = DB::getTablePrefix() . $table; // 表名
        $firstRow = current($multipleData);

        $updateColumn = array_keys($firstRow);
        // 默认以id为条件更新，如果没有ID则以第一个字段为条件
        $referenceColumn = isset($firstRow['id']) ? 'id' : current($updateColumn);
        unset($updateColumn[0]);
        // 拼接sql语句
        $updateSql = "UPDATE " . $tableName . " SET ";
        $sets = [];
        $bindings = [];
        foreach ($updateColumn as $uColumn) {
            $setSql = "`" . $uColumn . "` = CASE ";
            foreach ($multipleData as $data) {
                $setSql .= "WHEN `" . $referenceColumn . "` = ? THEN ? ";
                $bindings[] = $data[$referenceColumn];
                $bindings[] = $data[$uColumn];
            }
            $setSql .= "ELSE `" . $uColumn . "` END ";
            $sets[] = $setSql;
        }
        $updateSql .= implode(', ', $sets);
        $whereIn = collect($multipleData)->pluck($referenceColumn)->values()->all();
        $bindings = array_merge($bindings, $whereIn);
        $whereIn = rtrim(str_repeat('?,', count($whereIn)), ',');
        $updateSql = rtrim($updateSql, ", ") . " WHERE `" . $referenceColumn . "` IN (" . $whereIn . ")";
        foreach ($where as $key => $value) {
            $updateSql = $updateSql . ' and ' . $key . "='" . $value . "'";
        }
//        echo $updateSql."\n";
        // 传入预处理sql语句和对应绑定数据
        return DB::update($updateSql, $bindings);
    }

}

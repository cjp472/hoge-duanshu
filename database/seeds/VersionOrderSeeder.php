<?php

use Illuminate\Database\Seeder;

class VersionOrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        factory(App\Models\VersionOrder::class, 10)->create()->each(function ($u) {
            $data = factory(App\Models\Shop::class)->make()->where(['hashid'=>$u->shop_id])->first();
            $data->version = 'advanced';
            $data->save();
        });
    }
}

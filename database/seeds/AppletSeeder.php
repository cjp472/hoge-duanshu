<?php

use Illuminate\Database\Seeder;

class AppletSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        factory(App\Models\OpenPlatformPublic::class, 28)->create();
        factory(App\Models\OpenPlatformApplet::class, 28)->create();
    }
}

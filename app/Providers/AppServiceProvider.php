<?php

namespace App\Providers;

use Faker\Factory as FakerFactory;
use Faker\Generator as FakerGenerator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->singleton( FakerGenerator::class , function(){
            return FakerFactory::create('zh_CN');
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}

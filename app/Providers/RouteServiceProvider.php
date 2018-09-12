<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends \Illuminate\Foundation\Support\Providers\RouteServiceProvider
{
    /**
     * This namespace is applied to your controller routes.
     *
     * In addition, it is set as the URL generator's root namespace.
     *
     * @var string
     */
    protected $namespace = 'App\Http\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @param  \Illuminate\Routing\Router  $router
     * @return void
     */
    public function boot()
    {
        //
        parent::boot();
    }

    /**
     * Define the routes for the application.
     *
     * @param  \Illuminate\Routing\Router  $router
     * @return void
     */
    public function map()
    {
        $this->mapWebRoutes();
        $this->mapAdminRoutes();
        $this->mapClientApiRoutes();
        $this->mapH5Routes();
        $this->mapOutRoutes();
        $this->mapManageRoutes();
        $this->mapServiceRoutes();
        $this->mapOauthRoutes();
        $this->mapAppRoutes();
        $this->mapOauthClientRoutes();
        $this->mapRbacRoutes();
        //
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     *
     * @param  \Illuminate\Routing\Router  $router
     * @return void
     */
    protected function mapWebRoutes()
    {
        Route::namespace($this->namespace)
            ->group(app_path('Http/routes.php'));
    }

    protected function mapAdminRoutes()
    {
        Route::prefix('admin')
            ->namespace($this->namespace.'\Admin')
            ->middleware(['web','auth','shop','permission.check'])
            ->group(base_path('routes/adminRoutes.php'));
    }

    /**
     * 短书客户端路由
     */
    protected function mapClientApiRoutes()
    {
        Route::prefix('client_api')
            ->namespace($this->namespace.'\Admin')
            ->middleware(['client.api.sign','web','auth','shop','permission.check'])
            ->group(base_path('routes/clientApiRoutes.php'));
    }


    protected function mapH5Routes()
    {
        Route::prefix('h5')
            ->namespace($this->namespace.'\H5')
            ->middleware(['api.check','shop.h5.check','member.check'])
            ->group(base_path('routes/h5Routes.php'));
    }

    protected function mapOutRoutes()
    {
        Route::namespace($this->namespace)
            ->middleware(['log'])
            ->group(base_path('routes/outRoutes.php'));
    }

    protected function mapManageRoutes()
    {
        Route::prefix('manage')
            ->namespace($this->namespace.'\Manage')
            ->middleware(['web','auth','admin.signature','permission.manage'])
            ->group(base_path('routes/manageRoutes.php'));
    }

    protected function mapServiceRoutes()
    {
        Route::namespace($this->namespace.'\Sms')
            //->middleware(['log'])
            ->group(base_path('routes/serviceRoutes.php'));
    }
    protected function mapOauthRoutes()
    {
        Route::prefix('oauth')
            ->namespace($this->namespace.'\OauthService')
            ->middleware(['log'])
            ->group(base_path('routes/oauthRoutes.php'));
    }
    protected function mapAppRoutes()
    {
        Route::prefix('app')
            ->namespace($this->namespace.'\App')
            ->middleware(['log'])
            ->group(base_path('routes/appRoutes.php'));
    }
    protected function mapOauthClientRoutes()
    {
        Route::namespace($this->namespace.'\OauthClient')
            ->middleware(['web','log'])
            ->group(base_path('routes/oauthClientRoutes.php'));
    }

    protected function mapRbacRoutes()
    {
        Route::prefix('rbac')
            ->namespace($this->namespace.'\Rbac')
            ->middleware(['web','auth','admin.signature','permission.manage'])
            ->group(base_path('routes/rbacRoutes.php'));
    }
}

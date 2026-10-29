<?php

namespace App\Apps\CmsproNotepad;

use App\Apps\CmsproNotepad\Controllers\Admin\DashboardController;
use App\Apps\CmsproNotepad\Middleware\CheckNotepadPermission;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            base_path('app/Apps/CmsproNotepad/Config/notepad.php'),
            'apps.cmspro.notepad'
        );
    }

    public function boot(): void
    {
        $this->registerMiddlewareAlias();
        $this->registerRoutes();
        $this->loadViews();
    }

    /**
     * 注册应用级中间件别名，供 Routes/admin.php 按路由挂载权限校验
     */
    protected function registerMiddlewareAlias(): void
    {
        $this->app['router']->aliasMiddleware(
            'cmspro.notepad.permission',
            CheckNotepadPermission::class
        );
    }

    protected function registerRoutes(): void
    {
        // 仪表板路由覆盖（在 admin 前缀下，覆盖系统原始 dashboard 路由）
        // 注意：此处不挂应用权限中间件，否则未分配权限的管理员将无法打开后台首页；
        // 便签面板是否渲染由 DashboardController::index() 内部判断权限决定。
        Route::prefix('admin')
            ->middleware(['web', 'auth:admin'])
            ->namespace('App\Apps\CmsproNotepad\Controllers\Admin')
            ->group(function () {
                Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
            });

        // 应用专属路由
        Route::prefix('admin/cmspro/notepad')
            ->middleware(['web', 'auth:admin'])
            ->namespace('App\Apps\CmsproNotepad\Controllers\Admin')
            ->group(base_path('app/Apps/CmsproNotepad/Routes/admin.php'));

        // 应用后台 API 路由（/api/admin/cmspro/notepad）：WebOS 桌面小工具等站内数据接口
        Route::prefix('api/admin/cmspro/notepad')
            ->middleware(['web', 'auth:admin'])
            ->namespace('App\Apps\CmsproNotepad\Controllers\Admin')
            ->group(base_path('app/Apps/CmsproNotepad/Routes/admin_api.php'));
    }

    protected function loadViews(): void
    {
        $this->loadViewsFrom(
            base_path('app/Apps/CmsproNotepad/Views'),
            'cmspro.notepad'
        );
    }
}

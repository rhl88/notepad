<?php

namespace App\Apps\CmsproNotepad;

use App\Apps\CmsproNotepad\Controllers\Admin\DashboardController;
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
        $this->registerRoutes();
        $this->loadViews();
    }

    protected function registerRoutes(): void
    {
        // 仪表板路由覆盖（在 admin 前缀下，覆盖系统原始 dashboard 路由）
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
    }

    protected function loadViews(): void
    {
        $this->loadViewsFrom(
            base_path('app/Apps/CmsproNotepad/Views'),
            'cmspro.notepad'
        );
    }
}

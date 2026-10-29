<?php

use App\Apps\CmsproNotepad\Controllers\Admin\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 记事本应用后台 API 路由（/api/admin/cmspro/notepad）
|--------------------------------------------------------------------------
| 与页面路由（/admin/cmspro/notepad）分离：WebOS 桌面小工具只接受
| /api/ 前缀的数据接口，故按框架标准 API 前缀单独注册。
| 权限中间件别名 cmspro.notepad.permission:{suffix}，suffix 见 manifest.json。
*/

// 桌面小工具数据（WebOS 便签概览，按 manifest.widgets[].refresh 秒轮询）
Route::get('/widget/summary', [DashboardController::class, 'widgetSummary'])
    ->middleware('cmspro.notepad.permission:manage');
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

// 桌面小工具操作（便签概览卡片内「新增便签」按钮）
Route::post('/widget/quick-note', [DashboardController::class, 'quickNote'])
    ->middleware('cmspro.notepad.permission:create');

// 桌面小工具候选列表（桌面便签的 picker：当前管理员自己的便签，每条对应一张卡片）
Route::get('/widget/notes', [DashboardController::class, 'widgetNotes'])
    ->middleware('cmspro.notepad.permission:manage');

// 桌面小工具数据（桌面便签，note_id 由桌面项实例参数以查询串传入）
Route::get('/widget/note', [DashboardController::class, 'widgetNote'])
    ->middleware('cmspro.notepad.permission:manage');

// 桌面小工具操作（桌面便签卡片内「标记颜色」，note_id 随实例参数一并提交）
Route::post('/widget/note-color', [DashboardController::class, 'widgetNoteColor'])
    ->middleware('cmspro.notepad.permission:create');

// 桌面小工具操作（桌面便签卡片内「编辑内容」，note_id 随实例参数一并提交）
Route::post('/widget/note-content', [DashboardController::class, 'widgetNoteContent'])
    ->middleware('cmspro.notepad.permission:create');
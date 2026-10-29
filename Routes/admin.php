<?php

use App\Apps\CmsproNotepad\Controllers\Admin\DashboardController;
use App\Apps\CmsproNotepad\Controllers\Admin\NoteController;
use App\Apps\CmsproNotepad\Controllers\Admin\SettingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 记事本应用后台路由
|--------------------------------------------------------------------------
| 权限中间件别名：cmspro.notepad.permission:{suffix}
| suffix 对应 manifest.json 中 permissions[].code 的后缀（cmspro.notepad.{suffix}）。
| 每条路由单独挂载，避免组级中间件与路由级中间件叠加导致「需同时拥有多个权限」。
*/

// ========== 页面路由 ==========
Route::get('/notes', [NoteController::class, 'index'])
    ->middleware('cmspro.notepad.permission:manage');
Route::get('/notes/create', [NoteController::class, 'create'])
    ->middleware('cmspro.notepad.permission:create');
Route::get('/notes/{id}/edit', [NoteController::class, 'edit'])
    ->middleware('cmspro.notepad.permission:edit');
Route::get('/settings', [SettingController::class, 'index'])
    ->middleware('cmspro.notepad.permission:manage');

// ========== API 路由 ==========
Route::prefix('api')->group(function () {
    // 便签管理
    Route::get('/notes', [NoteController::class, 'list'])
        ->middleware('cmspro.notepad.permission:manage');
    Route::post('/notes', [NoteController::class, 'store'])
        ->middleware('cmspro.notepad.permission:create');
    Route::post('/notes/batch-delete', [NoteController::class, 'batchDelete'])
        ->middleware('cmspro.notepad.permission:delete');
    Route::get('/notes/unread-count', [NoteController::class, 'unreadCount'])
        ->middleware('cmspro.notepad.permission:manage');
    Route::get('/notes/{id}', [NoteController::class, 'show'])
        ->middleware('cmspro.notepad.permission:manage');
    Route::put('/notes/{id}', [NoteController::class, 'update'])
        ->middleware('cmspro.notepad.permission:edit');
    Route::put('/notes/{id}/toggle-status', [NoteController::class, 'toggleStatus'])
        ->middleware('cmspro.notepad.permission:edit');
    Route::delete('/notes/{id}', [NoteController::class, 'destroy'])
        ->middleware('cmspro.notepad.permission:delete');

    // 评论
    Route::post('/notes/{noteId}/comments', [NoteController::class, 'addComment'])
        ->middleware('cmspro.notepad.permission:manage');

    // 推送
    Route::post('/notes/{noteId}/push', [NoteController::class, 'push'])
        ->middleware('cmspro.notepad.permission:push');

    // 管理员列表（推送选择用）
    Route::get('/admin-users', [NoteController::class, 'adminUsers'])
        ->middleware('cmspro.notepad.permission:push');

    // 仪表板摘要
    Route::get('/dashboard-summary', [DashboardController::class, 'summary'])
        ->middleware('cmspro.notepad.permission:manage');

    // 设置
    Route::get('/settings', [SettingController::class, 'getSettings'])
        ->middleware('cmspro.notepad.permission:manage');
    Route::put('/settings', [SettingController::class, 'update'])
        ->middleware('cmspro.notepad.permission:manage');
});

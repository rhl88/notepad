<?php

use App\Apps\CmsproNotepad\Controllers\Admin\DashboardController;
use App\Apps\CmsproNotepad\Controllers\Admin\NoteController;
use App\Apps\CmsproNotepad\Controllers\Admin\SettingController;
use Illuminate\Support\Facades\Route;

// ========== 页面路由 ==========
Route::get('/notes', [NoteController::class, 'index']);
Route::get('/notes/create', [NoteController::class, 'create']);
Route::get('/notes/{id}/edit', [NoteController::class, 'edit']);
Route::get('/settings', [SettingController::class, 'index']);

// ========== API 路由 ==========
Route::prefix('api')->group(function () {
    // 便签管理
    Route::get('/notes', [NoteController::class, 'list']);
    Route::post('/notes', [NoteController::class, 'store']);
    Route::post('/notes/batch-delete', [NoteController::class, 'batchDelete']);
    Route::get('/notes/unread-count', [NoteController::class, 'unreadCount']);
    Route::get('/notes/{id}', [NoteController::class, 'show']);
    Route::put('/notes/{id}', [NoteController::class, 'update']);
    Route::put('/notes/{id}/toggle-status', [NoteController::class, 'toggleStatus']);
    Route::delete('/notes/{id}', [NoteController::class, 'destroy']);

    // 评论
    Route::post('/notes/{noteId}/comments', [NoteController::class, 'addComment']);

    // 推送
    Route::post('/notes/{noteId}/push', [NoteController::class, 'push']);

    // 管理员列表（推送选择用）
    Route::get('/admin-users', [NoteController::class, 'adminUsers']);

    // 仪表板摘要
    Route::get('/dashboard-summary', [DashboardController::class, 'summary']);

    // 设置
    Route::get('/settings', [SettingController::class, 'getSettings']);
    Route::put('/settings', [SettingController::class, 'update']);
});

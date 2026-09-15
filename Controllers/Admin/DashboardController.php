<?php

namespace App\Apps\CmsproNotepad\Controllers\Admin;

use App\Apps\CmsproNotepad\Services\NoteService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    protected NoteService $noteService;

    public function __construct(NoteService $noteService)
    {
        $this->noteService = $noteService;
    }

    /**
     * 扩展仪表板页面（接管 /admin/dashboard，但不整体替换）
     *
     * 渲染框架原版 dashboard（保留官网动态等框架特性，随框架升级自动获得新功能），
     * 再将应用便签面板片段插入：HTML 插到"系统信息"卡片之前，脚本插到 </body> 之前
     * （脚本须在 layui.js/pear.js 之后执行）。
     */
    public function index()
    {
        $html = view('admin.dashboard')->render();
        $widgets = view('cmspro.notepad::Admin.dashboard')->render();

        // 以首个 <script 为界拆分片段：前半为样式与 HTML，后半为依赖 layui 的脚本
        $scriptPos = strpos($widgets, '<script');
        $widgetsHtml = $scriptPos === false ? $widgets : substr($widgets, 0, $scriptPos);
        $widgetsScript = $scriptPos === false ? '' : substr($widgets, $scriptPos);

        // HTML 锚点：定位"系统信息"卡片起点，把便签面板插到它之前（保持统计卡片→便签面板→系统信息的布局）
        $inserted = false;
        $marker = '<div class="layui-card-header">系统信息</div>';
        $markerPos = strpos($html, $marker);
        if ($markerPos !== false) {
            $cardStart = strrpos(substr($html, 0, $markerPos), '<div class="layui-card"');
            if ($cardStart !== false) {
                $html = substr($html, 0, $cardStart) . $widgetsHtml . substr($html, $cardStart);
                $inserted = true;
            }
        }

        // 片段末尾落位：正常时仅脚本入尾部；锚点缺失时整体追加兜底，保证便签功能不丢失
        $tail = $inserted ? $widgetsScript : $widgetsHtml . $widgetsScript;
        if ($tail !== '') {
            if (str_contains($html, '</body>')) {
                $html = str_replace('</body>', $tail . '</body>', $html);
            } else {
                $html .= $tail;
            }
        }

        return response($html);
    }

    /**
     * 仪表板便签摘要API
     */
    public function summary(Request $request)
    {
        $adminUserId = auth('admin')->id();
        $perPage = $request->input('limit', 5);

        // 我的便签（最近5条）
        $myNotes = \App\Apps\CmsproNotepad\Models\Note::where('admin_user_id', $adminUserId)
            ->orderByDesc('pinned')
            ->orderByDesc('id')
            ->limit($perPage)
            ->get()
            ->toArray();

        // 未读推送数量
        $unreadCount = $this->noteService->getUnreadCount($adminUserId);

        // 收到的便签（最近5条，含已读和未读）
        $receivedPushes = \App\Apps\CmsproNotepad\Models\Push::where('to_admin_user_id', $adminUserId)
            ->with(['note', 'fromUser'])
            ->orderByDesc('id')
            ->limit($perPage)
            ->get();

        $pushes = [];
        foreach ($receivedPushes as $push) {
            $noteArr = $push->note ? $push->note->toArray() : [];
            $pushes[] = [
                'id' => $push->id,
                'note_id' => $push->note_id,
                'title' => $noteArr['title'] ?? '',
                'from_user' => $push->fromUser ? ($push->fromUser->name ?: $push->fromUser->username) : '未知',
                'message' => $push->message ?? '',
                'is_read' => $push->is_read,
                'create_time' => $push->create_time ? $push->create_time->format('Y-m-d H:i:s') : null,
            ];
        }

        // 便签统计
        $totalNotes = \App\Apps\CmsproNotepad\Models\Note::where('admin_user_id', $adminUserId)->count();
        $pendingNotes = \App\Apps\CmsproNotepad\Models\Note::where('admin_user_id', $adminUserId)->where('status', 0)->count();
        $receivedCount = \App\Apps\CmsproNotepad\Models\Push::where('to_admin_user_id', $adminUserId)->count();

        return response()->json([
            'code' => 0,
            'data' => [
                'my_notes' => $myNotes,
                'unread_count' => $unreadCount,
                'received_notes' => $pushes,
                'total_notes' => $totalNotes,
                'pending_notes' => $pendingNotes,
                'received_count' => $receivedCount,
            ],
        ]);
    }
}

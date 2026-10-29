<?php

namespace App\Apps\CmsproNotepad\Controllers\Admin;

use App\Apps\CmsproNotepad\Middleware\CheckNotepadPermission;
use App\Apps\CmsproNotepad\Models\Note;
use App\Apps\CmsproNotepad\Models\Push;
use App\Apps\CmsproNotepad\Services\NoteService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class DashboardController extends Controller
{
    /**
     * 扩展仪表板页面（接管 /admin/dashboard，但不整体替换）
     *
     * 渲染框架原版 dashboard（保留官网动态等框架特性，随框架升级自动获得新功能），
     * 再将应用便签面板片段插入：HTML 插到"系统信息"卡片之前，脚本插到 </body> 之前
     * （脚本须在 layui.js/pear.js 之后执行）。
     *
     * 本路由不挂应用权限中间件（否则未分配权限的管理员将无法打开后台首页），
     * 改为在此判断权限：无「便签管理」权限时仅返回框架原版仪表盘。
     */
    public function index(NoteService $noteService)
    {
        $html = view('admin.dashboard')->render();

        if (! CheckNotepadPermission::allows('manage')) {
            return response($html);
        }

        // 便签面板属于首页的附加能力，渲染或合并异常时降级为框架原版仪表盘，
        // 避免应用侧故障（视图编译失败、数据库异常等）导致整个后台首页打不开
        try {
            $html = $this->mergeWidgets($html, $this->renderWidgets($noteService));
        } catch (Throwable $e) {
            Log::error('[CmsproNotepad] 仪表盘便签面板渲染失败，已降级为框架原版仪表盘', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);
        }

        return response($html);
    }

    /**
     * 渲染应用便签面板片段
     */
    private function renderWidgets(NoteService $noteService): string
    {
        return view('cmspro.notepad::Admin.dashboard', [
            // 推送人数上限下发到面板，供推送弹窗展示「已选 / 上限」计数
            'maxPushTargets' => $noteService->getMaxPushTargets(),
        ])->render();
    }

    /**
     * 将便签面板片段合并进框架仪表盘
     *
     * 以首个 <script 为界拆分片段：前半为样式与 HTML，后半为依赖 layui 的脚本。
     * HTML 插到"系统信息"卡片之前，脚本插到 </body> 之前；锚点缺失时整体追加兜底，
     * 保证便签功能不丢失。
     */
    private function mergeWidgets(string $html, string $widgets): string
    {
        $scriptPos = strpos($widgets, '<script');
        $widgetsHtml = $scriptPos === false ? $widgets : substr($widgets, 0, $scriptPos);
        $widgetsScript = $scriptPos === false ? '' : substr($widgets, $scriptPos);

        [$html, $inserted] = $this->insertPanelBeforeSystemInfo($html, $widgetsHtml);

        $tail = $inserted ? $widgetsScript : $widgetsHtml . $widgetsScript;
        if ($tail === '') {
            return $html;
        }

        return str_contains($html, '</body>')
            ? str_replace('</body>', $tail . '</body>', $html)
            : $html . $tail;
    }

    /**
     * 把面板 HTML 插到"系统信息"卡片起点之前
     *
     * 保持「统计卡片 → 便签面板 → 系统信息」的布局顺序。
     *
     * @return array{0: string, 1: bool} 处理后的 HTML 与是否插入成功
     */
    private function insertPanelBeforeSystemInfo(string $html, string $widgetsHtml): array
    {
        $marker = '<div class="layui-card-header">系统信息</div>';
        $markerPos = strpos($html, $marker);
        if ($markerPos === false) {
            return [$html, false];
        }

        $cardStart = strrpos(substr($html, 0, $markerPos), '<div class="layui-card"');
        if ($cardStart === false) {
            return [$html, false];
        }

        return [substr($html, 0, $cardStart) . $widgetsHtml . substr($html, $cardStart), true];
    }

    /**
     * 仪表板便签摘要API
     */
    public function summary(Request $request)
    {
        $adminUserId = auth('admin')->id();
        $limit = min(max((int) $request->input('limit', 5), 1), 20);

        // 我的待办便签（已完成的不再占用首页位置）
        $myNotes = Note::where('admin_user_id', $adminUserId)
            ->where('status', 0)
            ->orderByDesc('pinned')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->toArray();

        // 收到的便签（最近 N 条，含已读和未读）
        $receivedPushes = Push::where('to_admin_user_id', $adminUserId)
            ->with(['note', 'fromUser'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        // 便签总数与待办数合并为一次查询，避免两次全表 count
        $noteStat = Note::where('admin_user_id', $adminUserId)
            ->selectRaw('COUNT(*) AS total_notes, COALESCE(SUM(status = 0), 0) AS pending_notes')
            ->first();

        // 收到推送总数与未读数合并为一次查询
        $pushStat = Push::where('to_admin_user_id', $adminUserId)
            ->selectRaw('COUNT(*) AS received_count, COALESCE(SUM(is_read = 0), 0) AS unread_count')
            ->first();

        return response()->json(ApiResponse::success([
            'my_notes' => $myNotes,
            'unread_count' => (int) ($pushStat->unread_count ?? 0),
            'received_notes' => $this->formatPushes($receivedPushes),
            'total_notes' => (int) ($noteStat->total_notes ?? 0),
            'pending_notes' => (int) ($noteStat->pending_notes ?? 0),
            'received_count' => (int) ($pushStat->received_count ?? 0),
        ]));
    }

    /**
     * 将推送记录集合格式化为面板所需的扁平结构
     *
     * @param \Illuminate\Support\Collection<int, Push> $pushes
     * @return array<int, array<string, mixed>>
     */
    private function formatPushes($pushes): array
    {
        $result = [];

        foreach ($pushes as $push) {
            $fromUser = $push->fromUser;

            $result[] = [
                'id' => $push->id,
                'note_id' => $push->note_id,
                'title' => $push->note->title ?? '',
                'from_user' => $fromUser ? ($fromUser->name ?: $fromUser->username) : '未知',
                'message' => $push->message ?? '',
                'is_read' => $push->is_read,
                // 模型层已通过 $casts + serializeDate 统一为 Y-m-d H:i:s，此处不再二次 format
                'create_time' => $push->create_time ? (string) $push->create_time : null,
            ];
        }

        return $result;
    }
}

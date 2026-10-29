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
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class DashboardController extends Controller
{
    /** 桌面便签候选列表单次最多返回的便签数（与 WebOS 候选弹层上限一致） */
    private const WIDGET_PICKER_LIMIT = 50;

    /** 桌面便签标记颜色（十六进制值）=> 卡片配色 tone，与 WebOS WIDGET_TONE_WHITELIST 对齐；空串表示默认 */
    private const WIDGET_NOTE_TONES = [
        '' => 'default',
        '#ff5722' => 'red',
        '#ff9800' => 'orange',
        '#4caf50' => 'green',
        '#2196f3' => 'blue',
        '#9c27b0' => 'purple',
    ];

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
     * 桌面小工具数据：便签概览（当前登录管理员视角）
     *
     * WebOS 桌面小工具按 manifest.widgets[].refresh 秒轮询本接口，返回统一契约
     * {title,status,value,unit,items,updated_at}；status 取 running|warning。
     */
    public function widgetSummary()
    {
        $adminUserId = auth('admin')->id();

        // 便签总数与待办数合并为一次查询（与仪表板摘要同一口径）
        $noteStat = Note::where('admin_user_id', $adminUserId)
            ->selectRaw('COUNT(*) AS total_notes, COALESCE(SUM(status = 0), 0) AS pending_notes')
            ->first();

        $unreadCount = (int) Push::where('to_admin_user_id', $adminUserId)
            ->where('is_read', 0)
            ->count();

        $todayNotes = Note::where('admin_user_id', $adminUserId)
            ->where('create_time', '>=', now()->startOfDay())
            ->count();

        $pendingNotes = (int) ($noteStat->pending_notes ?? 0);

        return ApiResponse::success([
            'title' => '便签概览',
            // 有未读推送即需要关注，其余视为正常运行
            'status' => $unreadCount > 0 ? 'warning' : 'running',
            'value' => $pendingNotes,
            'unit' => '条待办',
            'items' => [
                ['label' => '便签总数', 'value' => (string) (int) ($noteStat->total_notes ?? 0)],
                ['label' => '未读推送', 'value' => (string) $unreadCount],
                ['label' => '今日新增', 'value' => (string) $todayNotes],
            ],
            'updated_at' => now()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 桌面小工具操作：快捷新增便签（卡片内「新增便签」按钮）
     *
     * 只接收一条内容，标题自动取首行（超长截断），为空时用时间兜底；
     * 归属当前登录管理员，创建口径复用 NoteService::createNote（含内容长度上限校验）。
     */
    public function quickNote(Request $request, NoteService $noteService)
    {
        $validated = $request->validate([
            'content' => 'required|string|max:500',
        ]);

        $content = trim((string) $validated['content']);
        if ($content === '') {
            return response()->json(ApiResponse::error(400, '便签内容不能为空'));
        }

        $firstLine = Str::limit(trim(Str::before($content, "\n")), 30, '');
        $title = $firstLine !== '' ? $firstLine : '快捷便签 ' . now()->format('Y-m-d H:i');

        $result = $noteService->createNote([
            'admin_user_id' => auth('admin')->id(),
            'title' => $title,
            'content' => $content,
        ]);

        if (($result['code'] ?? 0) !== 0) {
            return response()->json($result);
        }

        return response()->json(ApiResponse::success([
            'id' => $result['data']['id'] ?? 0,
            'title' => $result['data']['title'] ?? $title,
        ], '已新增便签'));
    }

    /**
     * 桌面小工具候选列表：可作为桌面便签的便签（当前登录管理员自己的便签）
     *
     * WebOS 按 manifest.widgets[].picker 拉取本接口，返回 {items:[{value,label,hint}]}，
     * 每个候选对应一张桌面卡片；按置顶、更新时间倒序，最多 WIDGET_PICKER_LIMIT 条。
     */
    public function widgetNotes()
    {
        $notes = Note::where('admin_user_id', auth('admin')->id())
            ->orderByDesc('pinned')
            ->orderByDesc('update_time')
            ->limit(self::WIDGET_PICKER_LIMIT)
            ->get(['id', 'title', 'status', 'update_time']);

        $items = [];
        foreach ($notes as $note) {
            $title = trim((string) $note->title);

            $items[] = [
                'value' => (string) $note->id,
                'label' => $title !== '' ? $title : '便签 #' . $note->id,
                'hint' => ((int) $note->status === 1 ? '已完成' : '待办')
                    . ' · ' . (string) $note->update_time,
            ];
        }

        return response()->json(ApiResponse::success(['items' => $items]));
    }

    /**
     * 桌面小工具数据：单条便签（桌面便签卡片）
     *
     * 桌面项实例参数 note_id 以查询串传入。契约在概览基础上扩展：
     * title（便签标题）、text（正文，最多 2000 字）、color（卡片配色 tone）、
     * status 取 running|stopped（已完成便签视为停止关注）。
     * 非本人便签统一返回 404，避免泄露他人便签是否存在。
     */
    public function widgetNote(Request $request)
    {
        $validated = $request->validate([
            'note_id' => 'required|integer|min:1',
        ]);

        $note = Note::find((int) $validated['note_id']);
        if (! $note || (int) $note->admin_user_id !== (int) auth('admin')->id()) {
            return response()->json(ApiResponse::error(404, '便签不存在'), 404);
        }

        $title = trim((string) $note->title);

        return response()->json(ApiResponse::success([
            'title' => $title !== '' ? $title : '便签 #' . $note->id,
            'text' => mb_substr((string) $note->content, 0, 2000),
            'color' => self::WIDGET_NOTE_TONES[(string) $note->color] ?? 'default',
            'status' => (int) $note->status === 1 ? 'stopped' : 'running',
            'updated_at' => (string) $note->update_time,
        ]));
    }

    /**
     * 桌面小工具操作：调整便签标记颜色
     *
     * 颜色取 manifest 声明的六个色值，空串表示默认；归属与权限校验复用 NoteService::updateNote
     * （非本人便签返回 403，便签不存在返回 404）。
     */
    public function widgetNoteColor(Request $request, NoteService $noteService)
    {
        $validated = $request->validate([
            'note_id' => 'required|integer|min:1',
            'color' => ['nullable', 'string', Rule::in(array_keys(self::WIDGET_NOTE_TONES))],
        ]);

        // 空串经 ConvertEmptyStringsToNull 转成 null，这里统一归一为空串（默认色）
        $color = (string) ($validated['color'] ?? '');

        $result = $noteService->updateNote(
            (int) $validated['note_id'],
            ['color' => $color],
            (int) auth('admin')->id()
        );

        if (($result['code'] ?? 0) !== 0) {
            // 业务错误码与 HTTP 语义一致（400 内容超限 / 403 非本人 / 404 便签不存在）
            $status = in_array((int) $result['code'], [400, 403, 404], true) ? (int) $result['code'] : 500;

            return response()->json($result, $status);
        }

        return response()->json(ApiResponse::success([
            'color' => self::WIDGET_NOTE_TONES[$color] ?? 'default',
        ], '已更新标记颜色'));
    }

    /**
     * 桌面小工具操作：编辑便签内容
     *
     * 内容上限与卡片输入框一致（500 字），应用侧配置的内容上限由 NoteService::updateNote 再校验一次；
     * 返回 text 供卡片立即刷新正文。
     */
    public function widgetNoteContent(Request $request, NoteService $noteService)
    {
        $validated = $request->validate([
            'note_id' => 'required|integer|min:1',
            'content' => 'required|string|max:500',
        ]);

        $content = trim((string) $validated['content']);
        if ($content === '') {
            return response()->json(ApiResponse::error(400, '便签内容不能为空'));
        }

        $result = $noteService->updateNote(
            (int) $validated['note_id'],
            ['content' => $content],
            (int) auth('admin')->id()
        );

        if (($result['code'] ?? 0) !== 0) {
            // 业务错误码与 HTTP 语义一致（400 内容超限 / 403 非本人 / 404 便签不存在）
            $status = in_array((int) $result['code'], [400, 403, 404], true) ? (int) $result['code'] : 500;

            return response()->json($result, $status);
        }

        return response()->json(ApiResponse::success([
            'text' => mb_substr($content, 0, 2000),
        ], '已更新便签内容'));
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

<?php

namespace App\Apps\CmsproNotepad\Controllers\Admin;

use App\Apps\CmsproNotepad\Services\NoteService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NoteController extends Controller
{
    /** 便签标记颜色白名单，与 Views/Admin/notes/form.blade.php 颜色选择器保持一致 */
    private const COLOR_WHITELIST = ['', '#ff5722', '#ff9800', '#4caf50', '#2196f3', '#9c27b0'];

    /** 评论内容长度上限 */
    private const COMMENT_MAX_LENGTH = 1000;

    /** 单次批量删除数量上限 */
    private const BATCH_DELETE_MAX = 100;

    /** 列表内容摘要截取长度 */
    private const EXCERPT_LENGTH = 100;

    protected NoteService $noteService;

    public function __construct(NoteService $noteService)
    {
        $this->noteService = $noteService;
    }

    /**
     * 便签列表页面
     */
    public function index()
    {
        // 推送人数上限下发到页面，供推送弹窗展示「已选 / 上限」计数
        return view('cmspro.notepad::Admin.notes.index', [
            'maxPushTargets' => $this->noteService->getMaxPushTargets(),
        ]);
    }

    /**
     * 新建便签页面
     */
    public function create()
    {
        return view('cmspro.notepad::Admin.notes.form', $this->formViewData());
    }

    /**
     * 编辑便签页面（数据由前端 AJAX 回填）
     */
    public function edit($id)
    {
        return view('cmspro.notepad::Admin.notes.form', $this->formViewData());
    }

    /**
     * 表单页视图数据
     *
     * 内容字数上限来自应用配置项 max_content_length，由后端下发而非前端另调设置接口
     * （/api/settings 挂 manage 权限，仅有 create 权限的用户调用会被拒）。
     *
     * @return array<string, int>
     */
    private function formViewData(): array
    {
        return ['maxContentLength' => $this->noteService->getMaxContentLength()];
    }

    /**
     * 便签列表API（Layui table）
     */
    public function list(Request $request)
    {
        $tab = $request->input('tab') === 'received' ? 'received' : 'mine';

        $params = array_filter([
            'admin_user_id' => auth('admin')->id(),
            'tab' => $tab,
            'status' => $request->input('status'),
            'priority' => $request->input('priority'),
            'keyword' => $request->input('keyword'),
            'is_read' => $request->input('is_read'),
            // 不塞默认值：前端未传 limit 时由 Service 回落到应用配置项 per_page
            'per_page' => $request->input('limit'),
        ], fn ($value) => $value !== null && $value !== '');

        $result = $this->noteService->getNotes($params);

        if ($result['code'] !== 0) {
            return response()->json([
                'code' => 1,
                'msg' => $result['message'] ?? '获取失败',
                'count' => 0,
                'data' => [],
            ]);
        }

        $items = $result['data']['items'] ?? [];
        $data = $tab === 'received'
            ? array_map([$this, 'formatReceivedRow'], $items)
            : array_map([$this, 'formatMineRow'], $items);

        return response()->json([
            'code' => 0,
            'msg' => '',
            'count' => $result['data']['pagination']['total'] ?? 0,
            'data' => $data,
        ]);
    }

    /**
     * 便签详情API
     */
    public function show($id)
    {
        $result = $this->noteService->getNote((int) $id, auth('admin')->id());
        return response()->json($result);
    }

    /**
     * 创建便签
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'content' => 'nullable|string',
            'priority' => 'nullable|integer|in:0,1,2',
            'color' => ['nullable', 'string', 'max:20', Rule::in(self::COLOR_WHITELIST)],
            'pinned' => 'nullable|integer|in:0,1',
        ]);

        $result = $this->noteService->createNote([
            'admin_user_id' => auth('admin')->id(),
            'title' => $validated['title'],
            'content' => $validated['content'] ?? '',
            'priority' => (int) ($validated['priority'] ?? 0),
            'color' => $validated['color'] ?? '',
            'pinned' => (int) ($validated['pinned'] ?? 0),
        ]);

        return response()->json($result);
    }

    /**
     * 更新便签
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:200',
            'content' => 'sometimes|nullable|string',
            'priority' => 'sometimes|nullable|integer|in:0,1,2',
            'color' => ['sometimes', 'nullable', 'string', 'max:20', Rule::in(self::COLOR_WHITELIST)],
            'pinned' => 'sometimes|nullable|integer|in:0,1',
        ]);

        $result = $this->noteService->updateNote((int) $id, $validated, auth('admin')->id());

        return response()->json($result);
    }

    /**
     * 切换便签状态
     */
    public function toggleStatus($id)
    {
        $result = $this->noteService->toggleStatus((int) $id, auth('admin')->id());
        return response()->json($result);
    }

    /**
     * 删除便签
     */
    public function destroy($id)
    {
        $result = $this->noteService->deleteNote((int) $id, auth('admin')->id());
        return response()->json($result);
    }

    /**
     * 批量删除便签
     */
    public function batchDelete(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1|max:' . self::BATCH_DELETE_MAX,
            'ids.*' => 'integer',
        ]);

        $result = $this->noteService->batchDelete($validated['ids'], auth('admin')->id());

        return response()->json($result);
    }

    /**
     * 添加评论
     */
    public function addComment(Request $request, $noteId)
    {
        $validated = $request->validate([
            'content' => 'required|string|max:' . self::COMMENT_MAX_LENGTH,
        ]);

        $result = $this->noteService->addComment(
            (int) $noteId,
            auth('admin')->id(),
            $validated['content']
        );

        return response()->json($result);
    }

    /**
     * 推送便签
     */
    public function push(Request $request, $noteId)
    {
        $validated = $request->validate([
            'target_user_ids' => 'required|array|min:1',
            'target_user_ids.*' => 'integer',
            'message' => 'nullable|string|max:500',
        ]);

        $result = $this->noteService->pushNote(
            (int) $noteId,
            auth('admin')->id(),
            $validated['target_user_ids'],
            $validated['message'] ?? ''
        );

        return response()->json($result);
    }

    /**
     * 获取未读推送数量
     */
    public function unreadCount()
    {
        $count = $this->noteService->getUnreadCount(auth('admin')->id());

        return response()->json(ApiResponse::success(['count' => $count]));
    }

    /**
     * 获取管理员列表（推送选择用）
     */
    public function adminUsers()
    {
        $result = $this->noteService->getAdminUsers(auth('admin')->id());
        return response()->json($result);
    }

    /**
     * 展平「我的便签」行数据（模型先转数组以复用 $casts 的日期格式）
     *
     * @param Model|array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function formatMineRow($item): array
    {
        $row = $item instanceof Model ? $item->toArray() : (array) $item;

        return [
            'id' => $row['id'] ?? 0,
            'title' => $row['title'] ?? '',
            'content' => $this->excerpt($row['content'] ?? ''),
            'status' => $row['status'] ?? 0,
            'priority' => $row['priority'] ?? 0,
            'color' => $row['color'] ?? '',
            'pinned' => $row['pinned'] ?? 0,
            'create_time' => $row['create_time'] ?? null,
            'update_time' => $row['update_time'] ?? null,
        ];
    }

    /**
     * 展平「收到的便签」行数据
     *
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function formatReceivedRow($item): array
    {
        $item = (array) $item;
        $note = $item['note'] ?? [];
        $noteRow = $note instanceof Model ? $note->toArray() : (array) $note;
        $fromUser = $item['from_user'] ?? null;

        return [
            'id' => $item['id'] ?? 0,
            'note_id' => $item['note_id'] ?? 0,
            'title' => $noteRow['title'] ?? '',
            'content' => $this->excerpt($noteRow['content'] ?? ''),
            'priority' => $noteRow['priority'] ?? 0,
            'status' => $noteRow['status'] ?? 0,
            'from_user' => $fromUser ? ($fromUser['name'] ?: $fromUser['username']) : '未知',
            'message' => $item['message'] ?? '',
            'is_read' => $item['is_read'] ?? 0,
            'create_time' => $item['create_time'] ?? null,
        ];
    }

    /**
     * 截取内容摘要，超长时补省略号，避免前端看到被硬切断的半句话
     */
    private function excerpt($content): string
    {
        $content = (string) $content;

        if (mb_strlen($content) <= self::EXCERPT_LENGTH) {
            return $content;
        }

        return mb_substr($content, 0, self::EXCERPT_LENGTH) . '...';
    }
}

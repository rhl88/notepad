<?php

namespace App\Apps\CmsproNotepad\Controllers\Admin;

use App\Apps\CmsproNotepad\Services\NoteService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NoteController extends Controller
{
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
        return view('cmspro.notepad::Admin.notes.index');
    }

    /**
     * 新建便签页面
     */
    public function create()
    {
        return view('cmspro.notepad::Admin.notes.form');
    }

    /**
     * 编辑便签页面（数据由前端 AJAX 回填）
     */
    public function edit($id)
    {
        return view('cmspro.notepad::Admin.notes.form');
    }

    /**
     * 便签列表API（Layui table）
     */
    public function list(Request $request)
    {
        $params = [
            'admin_user_id' => auth('admin')->id(),
            'tab' => $request->input('tab', 'mine'),
            'status' => $request->input('status'),
            'priority' => $request->input('priority'),
            'keyword' => $request->input('keyword'),
            'is_read' => $request->input('is_read'),
            'per_page' => $request->input('limit', 20),
        ];

        $result = $this->noteService->getNotes(array_filter($params, fn($v) => $v !== null && $v !== ''));

        if ($result['code'] !== 0) {
            return response()->json(['code' => 1, 'msg' => $result['message'] ?? '获取失败', 'data' => null]);
        }

        $items = $result['data']['items'] ?? [];
        $total = $result['data']['pagination']['total'] ?? 0;

        $tab = $params['tab'];

        if ($tab === 'received') {
            // 收到的便签数据展平（create_time 已在 Service 中格式化）
            $data = array_map(function ($item) {
                $note = $item['note'] ?? [];
                // note 是 Eloquent 模型，需先转数组获取格式化后的时间
                $noteArr = $note instanceof \Illuminate\Database\Eloquent\Model ? $note->toArray() : (array) $note;
                return [
                    'id' => $item['id'],
                    'note_id' => $item['note_id'],
                    'title' => $noteArr['title'] ?? '',
                    'content' => isset($noteArr['content']) ? mb_substr($noteArr['content'], 0, 100) : '',
                    'priority' => $noteArr['priority'] ?? 0,
                    'status' => $noteArr['status'] ?? 0,
                    'from_user' => $item['from_user'] ? ($item['from_user']['name'] ?: $item['from_user']['username']) : '未知',
                    'message' => $item['message'] ?? '',
                    'is_read' => $item['is_read'],
                    'create_time' => $item['create_time'],
                ];
            }, $items);
        } else {
            // 我的便签数据展平（Eloquent 模型先转数组确保日期格式化）
            $data = array_map(function ($item) {
                $arr = $item instanceof \Illuminate\Database\Eloquent\Model ? $item->toArray() : (array) $item;
                return [
                    'id' => $arr['id'],
                    'title' => $arr['title'],
                    'content' => mb_substr($arr['content'] ?? '', 0, 100),
                    'status' => $arr['status'],
                    'priority' => $arr['priority'],
                    'color' => $arr['color'] ?? '',
                    'pinned' => $arr['pinned'],
                    'create_time' => $arr['create_time'],
                    'update_time' => $arr['update_time'],
                ];
            }, $items);
        }

        return response()->json([
            'code' => 0,
            'msg' => '',
            'count' => $total,
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
        $request->validate([
            'title' => 'required|string|max:200',
            'content' => 'nullable|string',
        ]);

        $result = $this->noteService->createNote([
            'admin_user_id' => auth('admin')->id(),
            'title' => $request->input('title'),
            'content' => $request->input('content', ''),
            'priority' => $request->input('priority', 0),
            'color' => $request->input('color', ''),
            'pinned' => $request->input('pinned', 0),
        ]);

        return response()->json($result);
    }

    /**
     * 更新便签
     */
    public function update(Request $request, $id)
    {
        $result = $this->noteService->updateNote((int) $id, $request->all(), auth('admin')->id());
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
        $ids = $request->input('ids', []);
        $result = $this->noteService->batchDelete($ids, auth('admin')->id());
        return response()->json($result);
    }

    /**
     * 添加评论
     */
    public function addComment(Request $request, $noteId)
    {
        $request->validate([
            'content' => 'required|string',
        ]);

        $result = $this->noteService->addComment(
            (int) $noteId,
            auth('admin')->id(),
            $request->input('content')
        );

        return response()->json($result);
    }

    /**
     * 推送便签
     */
    public function push(Request $request, $noteId)
    {
        $request->validate([
            'target_user_ids' => 'required|array',
            'target_user_ids.*' => 'integer',
            'message' => 'nullable|string|max:500',
        ]);

        $result = $this->noteService->pushNote(
            (int) $noteId,
            auth('admin')->id(),
            $request->input('target_user_ids'),
            $request->input('message') ?? ''
        );

        return response()->json($result);
    }

    /**
     * 获取未读推送数量
     */
    public function unreadCount()
    {
        $count = $this->noteService->getUnreadCount(auth('admin')->id());
        return response()->json(['code' => 0, 'data' => ['count' => $count]]);
    }

    /**
     * 获取管理员列表（推送选择用）
     */
    public function adminUsers()
    {
        $result = $this->noteService->getAdminUsers(auth('admin')->id());
        return response()->json($result);
    }
}

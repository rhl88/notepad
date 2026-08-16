<?php

namespace App\Apps\CmsproNotepad\Services;

use App\Apps\CmsproNotepad\Models\Note;
use App\Apps\CmsproNotepad\Models\NoteComment;
use App\Apps\CmsproNotepad\Models\Push;
use App\Http\Responses\ApiResponse;
use App\Models\ConfigItem;
use Illuminate\Support\Facades\DB;

class NoteService
{
    /**
     * 获取便签列表（我的便签 / 收到的便签）
     */
    public function getNotes(array $params): array
    {
        $adminUserId = $params['admin_user_id'];
        $tab = $params['tab'] ?? 'mine'; // mine=我的便签, received=收到的便签
        $perPage = $params['per_page'] ?? $this->getConfig('per_page', 20);

        if ($tab === 'received') {
            return $this->getReceivedNotes($adminUserId, $params, $perPage);
        }

        return $this->getMyNotes($adminUserId, $params, $perPage);
    }

    /**
     * 我的便签列表
     */
    protected function getMyNotes(int $adminUserId, array $params, int $perPage): array
    {
        $query = Note::where('admin_user_id', $adminUserId);

        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', (int) $params['status']);
        }
        if (isset($params['priority']) && $params['priority'] !== '') {
            $query->where('priority', (int) $params['priority']);
        }
        if (!empty($params['keyword'])) {
            $query->where(function ($q) use ($params) {
                $q->where('title', 'like', '%' . $params['keyword'] . '%')
                  ->orWhere('content', 'like', '%' . $params['keyword'] . '%');
            });
        }

        $query->orderByDesc('pinned')->orderByDesc('priority')->orderByDesc('id');
        $result = $query->paginate($perPage);

        $items = $result->items();
        $pagination = [
            'total' => $result->total(),
            'current_page' => $result->currentPage(),
            'per_page' => $result->perPage(),
            'last_page' => $result->lastPage(),
        ];

        return ApiResponse::success(['items' => $items, 'pagination' => $pagination]);
    }

    /**
     * 收到的便签列表
     */
    protected function getReceivedNotes(int $adminUserId, array $params, int $perPage): array
    {
        $pushQuery = Push::where('to_admin_user_id', $adminUserId)
            ->with(['note', 'fromUser']);

        if (isset($params['is_read']) && $params['is_read'] !== '') {
            $pushQuery->where('is_read', (int) $params['is_read']);
        }

        $pushQuery->orderByDesc('id');
        $result = $pushQuery->paginate($perPage);

        $items = [];
        foreach ($result->items() as $push) {
            $items[] = [
                'id' => $push->id,
                'note_id' => $push->note_id,
                'note' => $push->note,
                'from_user' => $push->fromUser ? [
                    'id' => $push->fromUser->id,
                    'username' => $push->fromUser->username,
                    'name' => $push->fromUser->name ?? '',
                ] : null,
                'message' => $push->message,
                'is_read' => $push->is_read,
                'create_time' => $push->create_time ? $push->create_time->format('Y-m-d H:i:s') : null,
            ];
        }

        $pagination = [
            'total' => $result->total(),
            'current_page' => $result->currentPage(),
            'per_page' => $result->perPage(),
            'last_page' => $result->lastPage(),
        ];

        return ApiResponse::success(['items' => $items, 'pagination' => $pagination]);
    }

    /**
     * 获取便签详情（含评论和推送记录）
     */
    public function getNote(int $id, int $adminUserId): array
    {
        $note = Note::with(['comments.adminUser', 'pushes.toUser'])->find($id);
        if (!$note) {
            return ApiResponse::error(404, '便签不存在');
        }

        // 权限检查：只能查看自己的便签或被推送给自己的便签
        $isOwner = $note->admin_user_id === $adminUserId;
        $isReceiver = Push::where('note_id', $id)
            ->where('to_admin_user_id', $adminUserId)
            ->exists();

        if (!$isOwner && !$isReceiver) {
            return ApiResponse::error(403, '无权查看此便签');
        }

        // 如果是收到的便签，标记为已读
        if ($isReceiver && !$isOwner) {
            Push::where('note_id', $id)
                ->where('to_admin_user_id', $adminUserId)
                ->where('is_read', 0)
                ->update(['is_read' => 1]);
        }

        return ApiResponse::success($note);
    }

    /**
     * 创建便签
     */
    public function createNote(array $data): array
    {
        $maxContentLength = $this->getConfig('max_content_length', 5000);

        if (mb_strlen($data['content'] ?? '') > $maxContentLength) {
            return ApiResponse::error(400, "内容不能超过{$maxContentLength}个字符");
        }

        $note = Note::create([
            'admin_user_id' => $data['admin_user_id'],
            'title' => $data['title'],
            'content' => $data['content'] ?? '',
            'status' => 0,
            'priority' => $data['priority'] ?? 0,
            'color' => $data['color'] ?? '',
            'pinned' => $data['pinned'] ?? 0,
        ]);

        return ApiResponse::success($note, '创建成功');
    }

    /**
     * 更新便签
     */
    public function updateNote(int $id, array $data, int $adminUserId): array
    {
        $note = Note::find($id);
        if (!$note) {
            return ApiResponse::error(404, '便签不存在');
        }

        if ($note->admin_user_id !== $adminUserId) {
            return ApiResponse::error(403, '无权修改此便签');
        }

        $updateData = [];
        $allowedFields = ['title', 'content', 'priority', 'color', 'pinned'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }

        if (isset($updateData['content'])) {
            $maxContentLength = $this->getConfig('max_content_length', 5000);
            if (mb_strlen($updateData['content']) > $maxContentLength) {
                return ApiResponse::error(400, "内容不能超过{$maxContentLength}个字符");
            }
        }

        if (!empty($updateData)) {
            $note->update($updateData);
        }

        return ApiResponse::success($note, '更新成功');
    }

    /**
     * 切换便签状态（完成/未完成）
     */
    public function toggleStatus(int $id, int $adminUserId): array
    {
        $note = Note::find($id);
        if (!$note) {
            return ApiResponse::error(404, '便签不存在');
        }

        if ($note->admin_user_id !== $adminUserId) {
            return ApiResponse::error(403, '无权操作此便签');
        }

        $newStatus = $note->status === 0 ? 1 : 0;
        $note->update([
            'status' => $newStatus,
            'status_changed_at' => now(),
        ]);

        $statusText = $newStatus === 1 ? '已完成' : '未完成';
        return ApiResponse::success($note, "已标记为{$statusText}");
    }

    /**
     * 删除便签
     */
    public function deleteNote(int $id, int $adminUserId): array
    {
        $note = Note::find($id);
        if (!$note) {
            return ApiResponse::error(404, '便签不存在');
        }

        if ($note->admin_user_id !== $adminUserId) {
            return ApiResponse::error(403, '无权删除此便签');
        }

        DB::transaction(function () use ($note) {
            NoteComment::where('note_id', $note->id)->delete();
            Push::where('note_id', $note->id)->delete();
            $note->delete();
        });

        return ApiResponse::success(null, '删除成功');
    }

    /**
     * 批量删除便签
     */
    public function batchDelete(array $ids, int $adminUserId): array
    {
        if (empty($ids)) {
            return ApiResponse::error(400, '请选择要删除的便签');
        }

        $notes = Note::whereIn('id', $ids)
            ->where('admin_user_id', $adminUserId)
            ->get();

        DB::transaction(function () use ($notes) {
            foreach ($notes as $note) {
                NoteComment::where('note_id', $note->id)->delete();
                Push::where('note_id', $note->id)->delete();
                $note->delete();
            }
        });

        return ApiResponse::success(null, "成功删除{$notes->count()}条便签");
    }

    /**
     * 添加评论
     */
    public function addComment(int $noteId, int $adminUserId, string $content): array
    {
        $note = Note::find($noteId);
        if (!$note) {
            return ApiResponse::error(404, '便签不存在');
        }

        // 权限检查：只能对拥有或被推送的便签评论
        $isOwner = $note->admin_user_id === $adminUserId;
        $isReceiver = Push::where('note_id', $noteId)
            ->where('to_admin_user_id', $adminUserId)
            ->exists();

        if (!$isOwner && !$isReceiver) {
            return ApiResponse::error(403, '无权评论此便签');
        }

        if (empty(trim($content))) {
            return ApiResponse::error(400, '评论内容不能为空');
        }

        $comment = NoteComment::create([
            'note_id' => $noteId,
            'admin_user_id' => $adminUserId,
            'content' => $content,
        ]);

        $comment->load('adminUser');

        return ApiResponse::success($comment, '评论成功');
    }

    /**
     * 推送便签给指定管理员
     */
    public function pushNote(int $noteId, int $fromUserId, array $targetUserIds, string $message = ''): array
    {
        $note = Note::find($noteId);
        if (!$note) {
            return ApiResponse::error(404, '便签不存在');
        }

        if ($note->admin_user_id !== $fromUserId) {
            return ApiResponse::error(403, '只能推送自己的便签');
        }

        $maxTargets = $this->getConfig('max_push_targets', 10);
        if (count($targetUserIds) > $maxTargets) {
            return ApiResponse::error(400, "推送人数不能超过{$maxTargets}人");
        }

        if (in_array($fromUserId, $targetUserIds)) {
            return ApiResponse::error(400, '不能推送给自己');
        }

        $created = 0;
        foreach ($targetUserIds as $toUserId) {
            // 避免重复推送
            $exists = Push::where('note_id', $noteId)
                ->where('to_admin_user_id', $toUserId)
                ->exists();

            if (!$exists) {
                Push::create([
                    'note_id' => $noteId,
                    'from_admin_user_id' => $fromUserId,
                    'to_admin_user_id' => $toUserId,
                    'message' => $message,
                ]);
                $created++;
            }
        }

        return ApiResponse::success(null, "已推送给{$created}位管理员");
    }

    /**
     * 获取未读推送数量
     */
    public function getUnreadCount(int $adminUserId): int
    {
        return Push::where('to_admin_user_id', $adminUserId)
            ->where('is_read', 0)
            ->count();
    }

    /**
     * 获取管理员列表（用于推送选择）
     */
    public function getAdminUsers(int $excludeUserId): array
    {
        $users = \App\Models\AdminUser::where('id', '!=', $excludeUserId)
            ->where('status', 1)
            ->select(['id', 'username', 'name'])
            ->get();

        return ApiResponse::success($users);
    }

    /**
     * 读取应用配置
     */
    protected function getConfig(string $key, $default = null)
    {
        $fullCode = 'app_cmspro_notepad_' . $key;
        $item = ConfigItem::where('code', $fullCode)->first();

        if ($item && $item->value !== null && $item->value !== '') {
            return is_numeric($item->value) ? (int) $item->value : $item->value;
        }

        return $default;
    }
}

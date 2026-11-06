<?php

namespace App\Apps\CmsproNotepad\Services;

use App\Apps\CmsproNotepad\Models\Note;
use App\Apps\CmsproNotepad\Models\NoteComment;
use App\Apps\CmsproNotepad\Models\Push;
use App\Http\Responses\ApiResponse;
use App\Models\ConfigItem;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class NoteService
{
    /**
     * 应用配置缓存键
     *
     * 本应用全部配置项整体缓存为一个键，设置页保存后由 SettingController 主动清除，
     * 避免每条配置各占一个键导致失效逻辑分散。
     */
    public const CONFIG_CACHE_KEY = 'cmspro.notepad:configs';

    /** 应用配置缓存时长（秒） */
    private const CONFIG_CACHE_TTL = 600;

    /** 本应用读取的配置项 code 后缀，与 manifest.json 中 config_groups 声明保持一致 */
    private const CONFIG_KEYS = ['per_page', 'max_content_length', 'max_push_targets'];

    /** per_page 配置缺失或非法时的兜底值 */
    private const DEFAULT_PER_PAGE = 20;

    /** 每页条数硬上限，与 SettingController 中 per_page 的可写范围一致 */
    private const MAX_PER_PAGE = 100;

    /** max_content_length 配置缺失时的兜底值，类常量为该配置项唯一默认来源 */
    private const DEFAULT_MAX_CONTENT_LENGTH = 5000;

    /** max_push_targets 配置缺失时的兜底值，类常量为该配置项唯一默认来源 */
    private const DEFAULT_MAX_PUSH_TARGETS = 10;

    /**
     * 获取便签列表（我的便签 / 收到的便签）
     */
    public function getNotes(array $params): array
    {
        $adminUserId = (int) ($params['admin_user_id'] ?? 0);
        $tab = ($params['tab'] ?? 'mine') === 'received' ? 'received' : 'mine';
        $perPage = $this->resolvePerPage($params['per_page'] ?? null);

        if ($tab === 'received') {
            return $this->getReceivedNotes($adminUserId, $params, $perPage);
        }

        return $this->getMyNotes($adminUserId, $params, $perPage);
    }

    /**
     * 解析每页条数
     *
     * 优先取请求参数（Layui table 的 limit），未传时回落到应用配置项 per_page，
     * 最终结果限制在 1 ~ MAX_PER_PAGE 之间，防止越界参数一次拉取过多记录。
     */
    private function resolvePerPage($perPage): int
    {
        if (!is_numeric($perPage) || (int) $perPage < 1) {
            $perPage = $this->getConfig('per_page', self::DEFAULT_PER_PAGE);
        }

        return min(max((int) $perPage, 1), self::MAX_PER_PAGE);
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

        // items 无需重塑，直接复用框架分页响应构造器；
        // 其输出结构（data.items + data.pagination 四键）与前端约定完全一致
        return ApiResponse::paginate($result);
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
                // 模型层已通过 $casts + serializeDate 统一为 Y-m-d H:i:s，此处不再二次 format
                'create_time' => $push->create_time ? (string) $push->create_time : null,
            ];
        }

        // 收到的便签需重塑 items（附带推送人精简信息），无法整体复用 ApiResponse::paginate()，
        // 故分页元数据在此内联构造，键名与框架保持一致
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
        $maxContentLength = $this->getMaxContentLength();

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
                $value = $data[$field];
                // 颜色字段空值规范化：null 统一转为空字符串，避免违反列非空约束（default ''）
                if ($field === 'color' && $value === null) {
                    $value = '';
                }
                $updateData[$field] = $value;
            }
        }

        if (isset($updateData['content'])) {
            $maxContentLength = $this->getMaxContentLength();
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
     *
     * 先筛出属于当前管理员的便签 ID（越权 ID 静默忽略），再以三条批量语句完成删除，
     * 避免按记录逐条删除产生 3N 次数据库往返。
     */
    public function batchDelete(array $ids, int $adminUserId): array
    {
        // 前置守卫：未选择任何便签时直接返回，避免发起一次无谓的归属查询；
        // 错误体与下方「所选便签均不属于当前管理员」分支保持一致，前端提示统一
        if (empty($ids)) {
            return ApiResponse::error(400, '请选择要删除的便签');
        }

        $ownedIds = Note::whereIn('id', $ids)
            ->where('admin_user_id', $adminUserId)
            ->pluck('id')
            ->all();

        if ($ownedIds === []) {
            return ApiResponse::error(400, '请选择要删除的便签');
        }

        DB::transaction(function () use ($ownedIds) {
            NoteComment::whereIn('note_id', $ownedIds)->delete();
            Push::whereIn('note_id', $ownedIds)->delete();
            Note::whereIn('id', $ownedIds)->delete();
        });

        return ApiResponse::success(null, '成功删除' . count($ownedIds) . '条便签');
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

        // 目标去重并统一为整型，避免同一人重复计数、字符串 ID 导致 in_array 判断失效
        $targetUserIds = array_values(array_unique(array_map('intval', $targetUserIds)));

        $maxTargets = $this->getMaxPushTargets();
        if (count($targetUserIds) > $maxTargets) {
            return ApiResponse::error(400, "推送人数不能超过{$maxTargets}人");
        }

        if (in_array($fromUserId, $targetUserIds, true)) {
            return ApiResponse::error(400, '不能推送给自己');
        }

        if ($targetUserIds === []) {
            return ApiResponse::error(400, '请选择推送对象');
        }

        $created = $this->createPushes($noteId, $fromUserId, $targetUserIds, $message);

        return ApiResponse::success(null, "已推送给{$created}位管理员");
    }

    /**
     * 批量写入推送记录，返回实际新增条数
     *
     * 整体包裹事务：任一目标写入失败即全部回滚，避免「部分人收到、部分人没收到」
     * 却提示推送成功的中间状态。已存在的推送记录跳过，唯一索引冲突同样按跳过处理，
     * 以兼容并发重复提交。
     */
    private function createPushes(int $noteId, int $fromUserId, array $targetUserIds, string $message): int
    {
        $created = 0;

        DB::transaction(function () use ($noteId, $fromUserId, $targetUserIds, $message, &$created) {
            foreach ($targetUserIds as $toUserId) {
                $exists = Push::where('note_id', $noteId)
                    ->where('to_admin_user_id', $toUserId)
                    ->exists();

                if ($exists) {
                    continue;
                }

                try {
                    Push::create([
                        'note_id' => $noteId,
                        'from_admin_user_id' => $fromUserId,
                        'to_admin_user_id' => $toUserId,
                        'message' => $message,
                    ]);
                    $created++;
                } catch (QueryException $e) {
                    // 并发下另一请求已插入同一 (note_id, to_admin_user_id)，由唯一索引拦截，按已推送处理
                    if (!$this->isDuplicateKeyError($e)) {
                        throw $e;
                    }
                }
            }
        });

        return $created;
    }

    /**
     * 判断是否为唯一键冲突异常（SQLSTATE 23000，MySQL 1062 与 SQLite 共用）
     */
    private function isDuplicateKeyError(QueryException $e): bool
    {
        return ($e->errorInfo[0] ?? '') === '23000';
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
     * 获取内容最大字数
     *
     * 供表单页渲染字数上限与实时计数；createNote/updateNote 以同一来源二次校验，
     * 避免前端提示与后端限制不一致。
     */
    public function getMaxContentLength(): int
    {
        return (int) $this->getConfig('max_content_length', self::DEFAULT_MAX_CONTENT_LENGTH);
    }

    /**
     * 获取单次推送人数上限
     *
     * 供推送弹窗展示「已选 / 上限」计数；pushNote 以同一来源二次校验。
     */
    public function getMaxPushTargets(): int
    {
        return (int) $this->getConfig('max_push_targets', self::DEFAULT_MAX_PUSH_TARGETS);
    }

    /**
     * 获取列表默认每页条数
     *
     * 供列表页渲染 Layui table 的 page.limit；列表接口以同一来源解析分页参数，
     * 避免前端写死 limit 使配置项 per_page 永不生效。
     */
    public function getDefaultPerPage(): int
    {
        return $this->resolvePerPage(null);
    }

    /**
     * 读取应用配置
     *
     * 全部配置项一次性查出并整体缓存于 CONFIG_CACHE_KEY，设置页保存后由 SettingController 清除，
     * 避免列表、创建、推送等高频路径每次读取配置都产生一次数据库查询。
     */
    protected function getConfig(string $key, $default = null)
    {
        $configs = Cache::remember(self::CONFIG_CACHE_KEY, self::CONFIG_CACHE_TTL, function () {
            $codes = array_map(fn ($k) => 'app_cmspro_notepad_' . $k, self::CONFIG_KEYS);

            return ConfigItem::whereIn('code', $codes)->pluck('value', 'code')->all();
        });

        $value = $configs['app_cmspro_notepad_' . $key] ?? null;

        if ($value === null || $value === '') {
            return $default;
        }

        return is_numeric($value) ? (int) $value : $value;
    }
}

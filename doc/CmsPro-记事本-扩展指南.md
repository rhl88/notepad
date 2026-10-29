# CmsPro-记事本-扩展指南

> 版本：1.1.0 | 更新日期：2026-10-29
> 应用版本：1.1.0 | 应用 ID：cmspro.notepad

---

## 一、概述

本文档面向 CmsPro 应用开发者，说明如何在自定义应用中调用 **记事本（CmsproNotepad）** 应用的 Service 层与模型，实现便签数据读取、推送、评论等扩展功能。

**核心原则**：扩展接口通过 Service 层内部调用，不暴露 HTTP 端点，防止模拟提交攻击。

---

## 二、前置检查

调用扩展接口前，建议先检查记事本应用是否已安装并启用：

```php
use App\Models\AppModel;

$notepadApp = AppModel::where('app_id', 'cmspro.notepad')->first();
if (!$notepadApp || $notepadApp->status !== 1) {
    // 记事本未安装或未启用，降级处理
    return;
}
```

---

## 三、Service 层调用

### 3.1 NoteService — 便签服务

```php
use App\Apps\CmsproNotepad\Services\NoteService;
```

#### 公开方法

| 方法 | 说明 |
|------|------|
| `getNotes(array $params): array` | 获取便签列表（tab=mine/received，支持 status/priority/keyword/is_read 筛选） |
| `getNote(int $id, int $adminUserId): array` | 获取便签详情（含评论与推送记录；无权查看返回 403） |
| `createNote(array $data): array` | 创建便签（校验内容长度） |
| `updateNote(int $id, array $data, int $adminUserId): array` | 更新便签（仅本人） |
| `toggleStatus(int $id, int $adminUserId): array` | 切换完成状态（仅本人） |
| `deleteNote(int $id, int $adminUserId): array` | 删除便签（级联删除评论与推送） |
| `batchDelete(array $ids, int $adminUserId): array` | 批量删除便签 |
| `addComment(int $noteId, int $adminUserId, string $content): array` | 添加评论（拥有者或被推送者） |
| `pushNote(int $noteId, int $fromUserId, array $targetUserIds, string $message = ''): array` | 推送便签给指定管理员 |
| `getUnreadCount(int $adminUserId): int` | 获取未读推送数量 |
| `getAdminUsers(int $excludeUserId): array` | 获取管理员列表（用于推送选择） |
| `getMaxContentLength(): int` | 读取「内容最大长度」配置，未配置时回退 `Config/notepad.php` 默认值 |
| `getMaxPushTargets(): int` | 读取「推送人数上限」配置，未配置时回退默认值 |

> `getConfig(string $key, $default = null)` 为 `protected`，扩展应用请通过上面两个公开方法取值，不要直接查配置表。
>
> 三项配置（`per_page`、`max_content_length`、`max_push_targets`）在一次请求内合并为一次查询，结果按 `NoteService::CONFIG_CACHE_KEY`（`cmspro.notepad:configs`）缓存 10 分钟；若扩展代码直接改写了配置表，需 `Cache::forget(NoteService::CONFIG_CACHE_KEY)` 才能立即生效。

#### 调用示例

```php
use App\Apps\CmsproNotepad\Services\NoteService;

$noteService = app(NoteService::class);

// 获取某管理员未读推送数
$unread = $noteService->getUnreadCount($adminUserId);

// 创建便签
$result = $noteService->createNote([
    'admin_user_id' => $adminUserId,
    'title' => '待办事项',
    'content' => '完成周报',
    'priority' => 1,
]);

// 推送便签给其他管理员
$result = $noteService->pushNote($noteId, $adminUserId, [2, 3], '请查收');
```

> 注意：`NoteService` 的方法返回 `ApiResponse::success()/error()` 结构（`{code, message, data}`），调用方需按 `code === 0` 判断成功。

---

## 四、钩子扩展点

记事本应用当前**未注册任何 Action/Filter 钩子**（`manifest.json` 未声明 `hooks` 字段，应用内无 `Hooks.php`）。如需在便签创建/推送等事件时联动其他应用，可监听模型事件或自行扩展。

---

## 五、辅助函数

记事本应用**未提供全局辅助函数**（无 `helpers.php`）。

---

## 六、模型引用

### 6.1 Note — 便签

```php
use App\Apps\CmsproNotepad\Models\Note;
```

对应表 `app_cmspro_notepad_notes`。字段：`admin_user_id`、`title`、`content`、`status`（0-未完成 1-已完成）、`priority`（0-普通 1-重要 2-紧急）、`color`、`pinned`、`status_changed_at`。

| 关系 | 说明 |
|------|------|
| `adminUser()` | 多对一关联 AdminUser（创建人） |
| `comments()` | 一对多关联 NoteComment |
| `pushes()` | 一对多关联 Push |

### 6.2 NoteComment — 评论

```php
use App\Apps\CmsproNotepad\Models\NoteComment;
```

对应表 `app_cmspro_notepad_comments`。字段：`note_id`、`admin_user_id`、`content`。

| 关系 | 说明 |
|------|------|
| `note()` | 多对一关联 Note |
| `adminUser()` | 多对一关联 AdminUser（评论人） |

### 6.3 Push — 推送

```php
use App\Apps\CmsproNotepad\Models\Push;
```

对应表 `app_cmspro_notepad_pushes`。字段：`note_id`、`from_admin_user_id`、`to_admin_user_id`、`message`、`is_read`（0-未读 1-已读）。

| 关系 | 说明 |
|------|------|
| `note()` | 多对一关联 Note |
| `fromUser()` | 多对一关联 AdminUser（推送人） |
| `toUser()` | 多对一关联 AdminUser（被推送人） |

#### 调用示例

```php
use App\Apps\CmsproNotepad\Models\Note;

// 查询某管理员的便签（按置顶、优先级、id 排序）
$notes = Note::where('admin_user_id', $adminUserId)
    ->orderByDesc('pinned')
    ->orderByDesc('priority')
    ->orderByDesc('id')
    ->get();

// 查询某便签的评论（含评论人）
$comments = Note::find($noteId)->comments()->with('adminUser')->get();
```

---

## 七、注意事项

1. **数据隔离**：所有数据按 `admin_user_id` 隔离，查询/修改/删除均需带管理员 ID 条件或校验，杜绝越权。
2. **权限校验在中间件层**：记事本自身的路由由应用级中间件 `cmspro.notepad.permission:{suffix}` 逐条校验（详见应用文档 2.3）。**Service 层只校验数据归属，不校验功能权限**——扩展应用直接调用 `NoteService` 时，中间件不会生效，需要自行确认当前管理员是否具备对应权限，可复用 `CheckNotepadPermission::allows('push')` 等静态方法判断。
3. **级联清理**：删除便签（单删/批量）在事务内先删评论与推送记录，保证数据一致性。
4. **推送幂等**：`pushNote` 全程事务包裹；`app_cmspro_notepad_pushes` 建有 `(note_id, to_admin_user_id)` 唯一索引，重复推送会被数据库拒绝，Service 内部捕获唯一键冲突后按「跳过」处理。扩展代码请勿绕过 Service 直接 `Push::create()`，否则会抛出未捕获的唯一键异常。
5. **配置读取**：`NoteService` 通过 `getConfig()` 读取 `app_cmspro_notepad_*` 配置项，未配置时回退 `Config/notepad.php` 默认值，结果缓存 10 分钟（见 3.1）。
6. **仪表盘扩展集成**：本应用接管 `/admin/dashboard` 路由（name `admin.dashboard`），实现方式为「渲染框架原版 `admin.dashboard` 视图 + 插入应用面板片段」，**不整体替换框架页面**（保证框架后续新增的仪表盘特性自动保留）；面板渲染与合并整体包在 `try/catch (Throwable)` 中，异常时降级返回框架原版仪表盘。扩展应用需注意路由命名冲突，如需同样扩展仪表盘，建议参考 `DashboardController::index()` 的锚点插入 + 异常降级模式。
7. **日期格式**：模型 `$casts` 统一输出 `Y-m-d H:i:s`。

---

## 八、版本与更新日志

| 版本 | 日期 | 更新人 | 变更内容 |
|------|------|--------|----------|
| 1.0.0 | 2026-08-13 | 开发团队 | 初始版本（对应应用 v1.0.1） |
| 1.0.1 | 2026-09-15 | 开发团队 | 仪表盘由整体替换改为扩展集成：渲染框架原版 dashboard 并插入便签面板，框架新增特性（如官网动态）自动保留（对应应用 v1.0.3） |
| 1.1.0 | 2026-10-29 | 开发团队 | 补充 `getMaxContentLength()` / `getMaxPushTargets()` 公开方法与配置缓存说明；注意事项新增「权限校验在中间件层，Service 层不校验功能权限」「推送幂等与唯一索引，禁止绕过 Service 直写 Push」；仪表盘集成补充异常降级说明（对应应用 v1.1.0） |

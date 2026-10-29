# CmsPro-记事本-扩展指南

> 版本：1.2.0 | 更新日期：2026-10-29
> 应用版本：1.2.0 | 应用 ID：cmspro.notepad

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
| `getMaxContentLength(): int` | 读取「内容最大长度」配置，未配置时回退类常量 `DEFAULT_MAX_CONTENT_LENGTH` |
| `getMaxPushTargets(): int` | 读取「推送人数上限」配置，未配置时回退类常量 `DEFAULT_MAX_PUSH_TARGETS` |
| `getDefaultPerPage(): int` | 读取「每页便签数」配置，未配置时回退类常量 `DEFAULT_PER_PAGE`，供列表页渲染分页条数 |

> `getConfig(string $key, $default = null)` 为 `protected`，扩展应用请通过上面三个公开方法取值，不要直接查配置表。
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
5. **配置读取**：`NoteService` 通过 `getConfig()` 读取 `app_cmspro_notepad_*` 配置项，未配置时回退 `DEFAULT_*` 类常量（代码内唯一默认来源，应用未注册 Laravel 配置文件），结果缓存 10 分钟（见 3.1）。
6. **仪表盘扩展集成**：本应用接管 `/admin/dashboard` 路由（name `admin.dashboard`），实现方式为「渲染框架原版 `admin.dashboard` 视图 + 插入应用面板片段」，**不整体替换框架页面**（保证框架后续新增的仪表盘特性自动保留）；面板渲染与合并整体包在 `try/catch (Throwable)` 中，异常时降级返回框架原版仪表盘。扩展应用需注意路由命名冲突，如需同样扩展仪表盘，建议参考 `DashboardController::index()` 的锚点插入 + 异常降级模式。
7. **字段类型与日期格式**：模型 `$casts` 同时统一日期与整型输出——日期一律 `Y-m-d H:i:s`；整型列（如 `status`、`priority`、`pinned`、`is_read`、`admin_user_id`）显式声明 `integer`，因为部分 PDO 驱动下查询结果会把整型列返回为字符串，声明后前端才能对状态、优先级等字段安全使用 `===` 严格比较。扩展应用新增整型列时须同步补 `$casts`。

---

## 八、WebOS 桌面小工具接入（1.1.1 新增「便签概览」，1.2.0 新增「桌面便签」）

### 8.1 便签概览（`sticky-overview`）

本应用在 `manifest.json` 的 `widgets` 中声明「便签概览」，供 WebOS 桌面右键「小工具」面板添加。1.1.2 起支持三档尺寸（`1x1` / `2x1` / `2x2`，用户可拖拽右下角手柄吸附切换）与卡片内「新增便签」操作（弹输入框填写内容直接落库，类似 macOS/Windows 桌面便签）。卡片的渲染、轮询、尺寸拖拽与操作弹窗全部由 WebOS 负责，本应用只提供两个接口：

```json
{
    "widgets": [
        {
            "id": "sticky-overview",
            "title": "便签概览",
            "icon": "fa fa-sticky-note",
            "entry": "/api/admin/cmspro/notepad/widget/summary",
            "refresh": 60,
            "sizes": ["1x1", "2x1", "2x2"],
            "default_size": "1x1",
            "actions": [
                {
                    "id": "new-note",
                    "title": "新增便签",
                    "icon": "fa fa-plus",
                    "method": "POST",
                    "entry": "/api/admin/cmspro/notepad/widget/quick-note",
                    "input": {
                        "label": "便签内容",
                        "placeholder": "随手记一条…",
                        "maxlength": 500
                    }
                }
            ]
        }
    ]
}
```

- **数据接口**：`GET /api/admin/cmspro/notepad/widget/summary`，中间件 `web` + `auth:admin` + `cmspro.notepad.permission:manage`。
- **操作接口**：`POST /api/admin/cmspro/notepad/widget/quick-note`，中间件 `web` + `auth:admin` + `cmspro.notepad.permission:create`（新增便签所需权限，只读角色提交返回 403 / `code=40301`）；入参 `content`（`required|string|max:500`，缺失、空白或超长返回 422，不落库）。
- **路由文件**：`Routes/admin_api.php`，由 `ServiceProvider::registerRoutes()` 以 `api/admin/cmspro/notepad` 前缀注册。与页面路由 `/admin/cmspro/notepad`（含其下 `/api` 子组）分离——WebOS 小工具的 `entry` 只接受站内 `/api/` 前缀，故单独一套 API 前缀。
- **统计口径**（均按当前登录管理员）：便签总数与待办数合并为一次聚合查询（`COUNT(*)` + `SUM(status = 0)`）；今日新增按 `create_time >= 当天 00:00:00`；未读推送取 `app_cmspro_notepad_pushes` 中 `is_read = 0` 的记录数。
- **返回契约**：`status` 有未读推送为 `warning`、否则 `running`；`value` / `unit` 为待办数（`条待办`）；`items` 依次为便签总数、未读推送、今日新增（WebOS 最多渲染 3 行）；`updated_at` 为 `Y-m-d H:i:s`。
- **快捷新增口径**：`content` 仅清理首尾空白（内部换行原样保留）；标题取首行并按显示宽度截断 30 字符，首行为空时兜底 `快捷便签 Y-m-d H:i`；归属当前登录管理员，创建走 `NoteService::createNote()`（与后台新建共用校验与默认值）；成功返回 `{ id, title }` 与提示「已新增便签」，WebOS 收到 `code === 0` 后立即刷新卡片数据。`input` 未声明 `name`，WebOS 按约定回落字段名 `content`，与服务端入参一致。
- **测试**：`Tests/Feature/WidgetSummaryTest.php` 覆盖未登录 401、契约字段与统计口径、无未读推送时 `running`、无 `manage` 权限 403（`code=40301`）；`Tests/Feature/QuickNoteTest.php` 6 用例覆盖未登录 401、当前管理员归属与内容/标题口径、标题 30 字符截断、空内容与缺失内容 422、超 500 字符 422、无 `create` 权限 403。

### 8.2 桌面便签（`sticky-note`，1.2.0 新增）

「桌面便签」是**多实例**小工具：用户在「小工具」面板点选后先弹「选择便签」候选列表，每选一条便签即新增一张独立卡片（`desktop_id` 带实例后缀），可同时贴多条便签到桌面，行为对齐 macOS 桌面便签。卡片内容随便签数据实时刷新，可在卡片内改标记颜色、编辑内容，右上角关闭按钮由 WebOS 统一提供（仅从桌面移除卡片，不删除便签数据）。

```json
{
    "id": "sticky-note",
    "title": "桌面便签",
    "icon": "fa fa-sticky-note-o",
    "entry": "/api/admin/cmspro/notepad/widget/note",
    "refresh": 60,
    "sizes": ["1x1", "2x1", "2x2"],
    "default_size": "1x1",
    "picker": {
        "entry": "/api/admin/cmspro/notepad/widget/notes",
        "param": "note_id",
        "label": "选择便签"
    },
    "actions": [
        {
            "id": "mark-color",
            "title": "标记颜色",
            "icon": "fa fa-tag",
            "method": "POST",
            "entry": "/api/admin/cmspro/notepad/widget/note-color",
            "input": {
                "type": "select",
                "label": "标记颜色",
                "name": "color",
                "options": [
                    { "value": "", "label": "默认" },
                    { "value": "#ff5722", "label": "红色" },
                    { "value": "#ff9800", "label": "橙色" },
                    { "value": "#4caf50", "label": "绿色" },
                    { "value": "#2196f3", "label": "蓝色" },
                    { "value": "#9c27b0", "label": "紫色" }
                ]
            }
        },
        {
            "id": "edit-content",
            "title": "编辑内容",
            "icon": "fa fa-pencil",
            "method": "POST",
            "entry": "/api/admin/cmspro/notepad/widget/note-content",
            "input": {
                "label": "便签内容",
                "placeholder": "修改便签内容…",
                "maxlength": 500,
                "name": "content"
            }
        }
    ]
}
```

四个接口（均需 `web` + `auth:admin`，`entry` 只接受站内 `/api/` 前缀）：

| 方法 | 路径 | 中间件权限 | 说明 |
|---|---|---|---|
| GET | `/widget/notes` | `cmspro.notepad.permission:manage` | picker 候选：当前管理员自己的便签，置顶优先、按 `update_time` 倒序，最多 50 条；返回 `items[{value,label,hint}]`（`value` 为便签 ID 字符串，`label` 为标题、空标题回落「便签 #ID」，`hint` 为「已完成/待办 · 更新时间」） |
| GET | `/widget/note` | `cmspro.notepad.permission:manage` | 卡片数据：`note_id` 由桌面项实例参数以查询串传入（`required|integer|min:1`）；非本人或不存在返回 404；返回 `title`（标题）、`text`（内容，截断 2000 字符）、`color`（tone：`default`/`red`/`orange`/`green`/`blue`/`purple`）、`status`（`status=1` 已完成 → `stopped`，否则 `running`）、`updated_at` |
| POST | `/widget/note-color` | `cmspro.notepad.permission:create` | 改标记颜色：`note_id` + `color`（可空，须命中六色白名单，空串/`null` 表示恢复默认）；非本人 403、不存在 404、内容超限 400，均按 HTTP 语义返回并透传业务 `code` |
| POST | `/widget/note-content` | `cmspro.notepad.permission:create` | 编辑内容：`note_id` + `content`（`required|string|max:500`）；`trim` 后为空返回 400；归属校验与错误透传同上；成功返回 `{ text }` |

实现要点：

- 写操作复用 `NoteService::updateNote($id, $data, $adminUserId)`（与后台编辑同一入口，含 owner 校验与内容长度配置上限），不新建 Service 方法。
- 颜色以十六进制原值存库（`notes.color`），出参统一映射为 WebOS 卡片 `tone`；映射表 `WIDGET_NOTE_TONES` 与 WebOS `WIDGET_TONE_WHITELIST` 一一对齐。
- WebOS 侧提交操作时会把桌面项的实例参数（`note_id`）合并进请求体，故四个控制器方法均从请求体/查询串直接取 `note_id`，应用侧无需感知实例 ID。
- 测试：`Tests/Feature/WidgetNoteTest.php` 19 用例覆盖登录态、picker 过滤/排序/上限、数据契约与 404、颜色白名单与复位、内容校验与配置上限、`manage`/`create` 权限分离；应用全量 82 测试 315 断言通过。

---

## 九、版本与更新日志

| 版本 | 日期 | 更新人 | 变更内容 |
|------|------|--------|----------|
| 1.2.0 | 2026-10-29 | 开发团队 | 新增 WebOS 桌面小工具「桌面便签」（多实例，等同 macOS 桌面便签）：`manifest.json` 的 `widgets` 追加 `sticky-note`（`picker` 选便签 + `mark-color` 六色下拉 + `edit-content` 文本编辑）；`Routes/admin_api.php` 新增 4 条路由（`GET /widget/notes`、`GET /widget/note` 用 `manage`；`POST /widget/note-color`、`POST /widget/note-content` 用 `create`）；`DashboardController` 新增 `widgetNotes()` / `widgetNote()` / `widgetNoteColor()` / `widgetNoteContent()` 与 `WIDGET_PICKER_LIMIT`、`WIDGET_NOTE_TONES` 常量，写操作复用 `NoteService::updateNote()` 并按业务码映射 HTTP 400/403/404；新增 `WidgetNoteTest` 19 用例（对应应用 v1.2.0） |
| 1.1.2 | 2026-10-29 | 开发团队 | 便签概览小工具支持三档尺寸与卡片新增便签：`manifest.json` 的 `widgets[0]` 补 `sizes`（`1x1`/`2x1`/`2x2`）、`default_size`（`1x1`）、`actions`（`new-note` → `POST /api/admin/cmspro/notepad/widget/quick-note`，输入框 `label=便签内容`、`maxlength=500`）；`Routes/admin_api.php` 新增操作路由（`cmspro.notepad.permission:create`）；`DashboardController::quickNote()` 校验 `content required|string|max:500`、标题取首行截断 30 字符、归属当前管理员并复用 `NoteService::createNote()`；新增 `QuickNoteTest` 6 用例（对应应用 v1.1.2） |
| 1.1.1 | 2026-10-29 | 开发团队 | 新增 WebOS 桌面小工具「便签概览」：`manifest.json` 声明 `widgets`（`sticky-overview`、entry `/api/admin/cmspro/notepad/widget/summary`、refresh 60）；新增 `Routes/admin_api.php` 与 `ServiceProvider` 的 `api/admin/cmspro/notepad` 路由组；`DashboardController::widgetSummary()` 输出小工具数据契约；新增 `WidgetSummaryTest` 4 用例（对应应用 v1.1.1） |
| 1.1.0 | 2026-10-29 | 开发团队 | 补充 `getMaxContentLength()` / `getMaxPushTargets()` 公开方法与配置缓存说明；注意事项新增「权限校验在中间件层，Service 层不校验功能权限」「推送幂等与唯一索引，禁止绕过 Service 直写 Push」；仪表盘集成补充异常降级说明（对应应用 v1.1.0） |
| 1.0.1 | 2026-09-15 | 开发团队 | 仪表盘由整体替换改为扩展集成：渲染框架原版 dashboard 并插入便签面板，框架新增特性（如官网动态）自动保留（对应应用 v1.0.3） |
| 1.0.0 | 2026-08-13 | 开发团队 | 初始版本（对应应用 v1.0.1） |

# CmsPro-记事本应用文档

> 版本：v1.1.0 | 创建日期：2026-08-12 | 更新日期：2026-10-29
> 适用系统：CmsPro v5.0.0+
> 应用 ID：`cmspro.notepad`

---

## 一、应用概述

### 1.1 用途

记事本（CmsproNotepad）是 CmsPro 后台的便签记录与协作应用，面向后台管理员提供便签管理、留言评论、推送提醒三大能力，基于管理员账号体系实现数据隔离与权限控制。

管理员可以创建个人便签（支持优先级、颜色标记、置顶、完成状态），可将便签推送给其他管理员（带留言），被推送方可在「收到的便签」中查看、标记已读并参与评论，实现团队间的轻量协作与提醒。

### 1.2 适用范围

- 后台管理员个人待办、备忘记录
- 团队内部工作交接、事项提醒（推送 + 评论协作）
- 需要将后台仪表盘改造为「便签 + 待办」工作台的场景

### 1.3 依赖

无外部应用依赖，仅依赖系统后台管理员账号体系（`admin_users` 表）与系统配置机制（ConfigGroup/ConfigItem）。

---

## 二、功能说明

### 2.1 功能模块

基于 `manifest.json` 的 `menus` 结构，后台「记事本」菜单下提供 2 个子模块：

| 菜单 | 路径 | 功能说明 |
|------|------|----------|
| 便签列表 | `/admin/cmspro/notepad/notes` | 「我的便签」与「收到的便签」双视图，新建/编辑/删除/批量删除/状态切换，查看详情（含评论与推送记录）、评论、推送 |
| 记事本设置 | `/admin/cmspro/notepad/settings` | 每页便签数、内容最大长度、推送人数上限 |

此外，ServiceProvider 将 `/admin/dashboard` 路由**接管**（name：`admin.dashboard`）：页面加载框架原版仪表盘内容后，插入记事本便签面板（我的便签、收到的便签、未读推送数与便签统计摘要），不整体替换框架页面，框架后续新增的仪表盘特性自动保留。

### 2.2 功能特性

- **我的便签**：仅本人可见/可编辑/可删除，支持标题、内容、优先级（0 普通/1 重要/2 紧急）、颜色标记、置顶、完成状态切换（status 0 未完成/1 已完成，记录状态变更时间）
- **收到的便签**：推送记录视图，含推送人、留言、已读状态；查看详情自动标记为已读
- **评论**：便签拥有者与被推送者均可评论，评论人信息随评论返回
- **推送**：便签拥有者可将便签推送给其他管理员（可带留言），每人每条便签仅一条推送记录（重复推送自动跳过），不能推送给自己，人数受 `max_push_targets` 限制
- **权限控制（双层）**：① 功能权限由应用级中间件按路由校验（见 2.3）；② 数据权限在业务层校验——详情查看/评论要求是拥有者或被推送者，修改/删除/状态切换仅限拥有者
- **数据隔离**：所有数据按 `admin_user_id` 隔离，管理员之间仅能通过推送看到对方便签
- **交互体验**：标题/内容实时字数统计与上限提示；表单有未保存改动时关闭弹窗二次确认；编辑回填期间显示 loading；推送选人支持搜索与「已选 / 上限」实时计数；列表与仪表盘面板均提供空状态引导文案
- **详情弹窗**：列表页与仪表盘面板共用同一份弹窗片段，展示颜色标记、置顶标识、状态、优先级、内容（超长可滚动）、创建/更新时间、评论列表与推送记录，并可直接评论、推送、切换完成状态
- **仪表盘局部刷新**：面板内新建、评论、切换状态、关闭子弹窗后仅重拉摘要接口局部重渲染，不整页刷新；徽章数量归零时自动隐藏

### 2.3 权限

`manifest.json` 声明 5 项权限：

| 权限码 | 名称 | 覆盖的路由 |
|--------|------|-----------|
| `cmspro.notepad.manage` | 便签管理 | `GET /notes`、`GET /settings`、`GET /api/notes`、`GET /api/notes/unread-count`、`GET /api/notes/{id}`、`POST /api/notes/{noteId}/comments`、`GET /api/dashboard-summary`、`GET /api/settings`、`PUT /api/settings` |
| `cmspro.notepad.create` | 创建便签 | `GET /notes/create`、`POST /api/notes` |
| `cmspro.notepad.edit` | 编辑便签 | `GET /notes/{id}/edit`、`PUT /api/notes/{id}`、`PUT /api/notes/{id}/toggle-status` |
| `cmspro.notepad.delete` | 删除便签 | `DELETE /api/notes/{id}`、`POST /api/notes/batch-delete` |
| `cmspro.notepad.push` | 推送便签 | `POST /api/notes/{noteId}/push`、`GET /api/admin-users` |

**校验方式**：应用自有中间件 `App\Apps\CmsproNotepad\Middleware\CheckNotepadPermission`，由 ServiceProvider 注册别名 `cmspro.notepad.permission`，在 `Routes/admin.php` 中**逐条路由单独挂载**（写法 `->middleware('cmspro.notepad.permission:manage')`，参数为权限码后缀）。之所以不用组级中间件，是为了避免组级与路由级中间件叠加导致「需同时拥有多个权限」。

**放行规则**（与框架 `CheckPermission` 语义一致）：

1. 权限码未在 `admin_permissions` 表注册（应用未经市场安装或权限尚未同步）→ 放行，避免误伤存量站点；注册状态查询结果缓存 5 分钟
2. 超级管理员 → 放行
3. 当前管理员所属角色已分配该权限 → 放行
4. 其余情况 → 页面请求渲染应用自有的 403 页（`Views/Admin/errors/forbidden.blade.php`），AJAX/JSON 请求返回 `{code:40301, message:...}` + HTTP 403

> 页面请求**不能**使用 `abort(403)`：框架 `bootstrap/app.php` 的 HttpException 渲染器只处理 419，而 AuthorizationException 渲染器指向的 `errors.403` 视图并不存在，两者都会退化成 500。

**仪表盘路由的特殊处理**：`GET /admin/dashboard` **不挂**权限中间件（否则无便签权限的管理员会连后台首页都打不开），而是在 `DashboardController::index()` 内部调用 `CheckNotepadPermission::allows('manage')` 决定是否渲染便签面板，无权限时只返回框架原版仪表盘。

**升级授权**：`Install::install()` 与 `Install::upgrade()` 均会调用 `grantPermissionsToExistingRoles()`——先以 `firstOrCreate` 幂等地把 manifest 声明的权限写入 `admin_permissions`，再用 `syncWithoutDetaching` 为所有**已启用角色**补授这 5 项权限，避免存量站点升级后非超管管理员立刻失去访问权限。**升级后新建的角色仍需在「角色管理」中手动勾选。**

---

## 三、技术架构

### 3.1 架构总览

```
后台管理（admin/cmspro/notepad，web + auth:admin）
  ├─ 视图路由：/notes、/notes/create、/notes/{id}/edit、/settings
  ├─ API 路由：/api/notes（CRUD）、/api/notes/{id}/comments、/api/notes/{id}/push、
  │            /api/admin-users、/api/dashboard-summary、/api/settings
  └─ 仪表盘扩展：/admin/dashboard（admin.dashboard，不挂权限中间件）
               │
               ▼
        CheckNotepadPermission（应用级权限中间件，别名 cmspro.notepad.permission:{suffix}）
               │
               ▼
        NoteService（业务逻辑层）
          getNotes / getNote / createNote / updateNote / toggleStatus /
          deleteNote / batchDelete / addComment / pushNote /
          getUnreadCount / getAdminUsers /
          getMaxContentLength / getMaxPushTargets / getConfig
               │
               ▼
        数据层（三张表）
          app_cmspro_notepad_notes     便签
          app_cmspro_notepad_comments  评论
          app_cmspro_notepad_pushes    推送（唯一索引防重复 + 未读查询复合索引）
```

### 3.2 技术栈与关键设计

- **技术栈**：Laravel（PHP >= 8.3）+ MySQL + Layui 后台前端
- **权限校验**：应用自有中间件按路由细粒度校验（详见 2.3），未授权页面渲染应用自有 403 页、接口返回 HTTP 403
- **数据隔离**：查询、修改、删除均带 `admin_user_id` 条件或校验，杜绝越权
- **入参校验（控制器层）**：`store`/`update`/`batchDelete`/`addComment`/`push`/`settings` 全部走 Laravel `validate`，负责结构与固定上限——标题必填且 ≤200 字、优先级限定 0/1/2、颜色限定白名单（`COLOR_WHITELIST`）、批量删除 `ids` 非空整型数组且 ≤100（`BATCH_DELETE_MAX`）、评论内容 ≤1000（`COMMENT_MAX_LENGTH`）、推送 `target_user_ids` 非空整型数组、留言 ≤500 字；设置项 code 必须带 `app_cmspro_notepad_` 前缀且在白名单内，值必须为正整数，任一项非法则整批拒绝不产生部分写入
- **入参校验（Service 层）**：与配置项挂钩的可变上限及归属判断在 `NoteService` 内完成——内容长度按 `getMaxContentLength()` 用 `mb_strlen` 校验；推送目标先 `array_unique` 去重并 `intval` 归一，再按 `getMaxPushTargets()` 校验人数上限，并拒绝推送给自己、拒绝空目标；修改/删除/状态切换/评论/推送均校验便签归属（拥有者或被推送者）
- **级联清理**：删除便签（单删/批量）在事务内先删评论与推送记录，保证数据一致性
- **推送幂等**：`pushNote` 全程事务包裹；`app_cmspro_notepad_pushes` 建立 `(note_id, to_admin_user_id)` 唯一索引从数据库层杜绝重复推送，代码侧同时捕获唯一键冲突异常跳过，双保险
- **配置驱动**：每页数量、内容长度、推送人数上限均从系统配置表读取（`app_cmspro_notepad_*`），未配置时回退 `Config/notepad.php` 默认值；一次请求内的多项配置读取合并为一次查询并缓存 10 分钟（`NoteService::CONFIG_CACHE_KEY`），设置保存成功后主动清除该缓存
- **配置同步机制**：Install 脚本从 `manifest.json` 的 `config_groups` 同步配置到数据库（新增写默认值、已有仅更新结构保留用户值、已删除清理残留、空分组删除），卸载时全量清理
- **统计聚合**：列表总数、待办数、收到数等统计走 `selectRaw('COUNT(*) ..., COALESCE(SUM(status = 0), 0) ...')` 单次聚合查询，不再先取全量数据在 PHP 中计数
- **仪表盘扩展集成**：ServiceProvider 注册 `GET /admin/dashboard`（name `admin.dashboard`）接管系统原始仪表盘路由，`DashboardController::index()` 渲染框架原版 `admin.dashboard` 视图，将应用便签面板片段插入（HTML 插到「系统信息」卡片之前、脚本插到 `</body>` 之前，锚点缺失时兜底追加到页面末尾），卸载/停用应用后恢复系统默认
- **集成降级**：便签面板的渲染与合并整体包在 `try/catch (Throwable)` 中，异常时记录 `Log::error` 并返回框架原版仪表盘，保证应用侧故障不会让整个后台首页打不开
- **视图复用**：详情弹窗（`Views/Admin/notes/_note_detail.blade.php`）与推送弹窗（`_push_dialog.blade.php`）抽为共享片段，列表页与仪表盘面板同源复用；片段内所有样式均用内联 `style`，不依赖宿主页面的 CSS；应用基础路径收敛到 `Views/Admin/_js_base.blade.php` 的 `NOTEPAD_BASE` 常量，视图内不再散落硬编码 URL
- **日期格式**：模型 `$casts` 统一输出 `Y-m-d H:i:s`；前端不使用 `toLocaleString` 等本地化格式化，列表与面板的时间一律由后端下发

### 3.3 数据表结构

| 表名 | 用途 | 关键字段 |
|------|------|----------|
| `app_cmspro_notepad_notes` | 便签 | admin_user_id、title、content、status（0/1）、priority（0/1/2）、color、pinned、status_changed_at |
| `app_cmspro_notepad_comments` | 评论 | note_id、admin_user_id、content |
| `app_cmspro_notepad_pushes` | 推送 | note_id、from_admin_user_id、to_admin_user_id、message、is_read（0/1） |

**索引设计**：

| 表 | 索引 | 类型 | 用途 |
|----|------|------|------|
| `notes` | `admin_user_id`、`status`、`pinned` | 普通单列 | 列表页按拥有者、完成状态、置顶筛选 |
| `comments` | `note_id`、`admin_user_id` | 普通单列 | 详情弹窗按便签取评论 |
| `pushes` | `(note_id, to_admin_user_id)` | **唯一** | 数据库层杜绝重复推送 |
| `pushes` | `(to_admin_user_id, is_read)` | 普通复合 | 收到的便签列表与未读数统计 |

> `pushes` 的两个索引在建表迁移 `2026_07_25_000003` 中声明（新装即生效）；存量站点由 `2026_10_29_000004_add_indexes_to_app_cmspro_notepad_pushes_table.php` 补齐，也可用 `sql/migrations/`、`sql/rollback/` 下脚本手工执行与回滚。
>
> 000004 迁移在加索引前会先清理历史重复推送（同 `note_id` + `to_admin_user_id` 保留最小 `id`，其余删除并记 `Log::warning`），并移除被复合索引覆盖的 3 个冗余单列索引（`note_id`、`to_admin_user_id`、`is_read`）；`up()`/`down()` 均以 `Schema::hasIndex()` 判断，重复执行安全。

---

## 四、接口定义

### 4.1 后台 API（前缀 `/admin/cmspro/notepad/api`，需登录后台）

> 统一返回格式：成功 `{code:0, msg:"", count?, data}`；列表接口遵循 Layui table 规范（code/msg/count/data）；业务错误返回 `{code:1, msg:"错误信息"}` 或 `{code:403/404/400}`。
>
> 下表「权限」列为权限码 `cmspro.notepad.{后缀}` 的后缀（见 2.3）；无对应权限时接口返回 HTTP 403 + `{code:40301}`。

#### 便签管理

| 方法 | 路径 | 控制器方法 | 权限 | 参数 | 返回 data 说明 |
|------|------|-----------|------|------|----------------|
| GET | `/api/notes` | NoteController::list | manage | tab（mine/received，默认 mine）、status、priority、keyword、is_read、limit | 我的便签：id、title、content（截 100 字）、status、priority、color、pinned、create_time、update_time；收到的便签：id、note_id、title、content、priority、status、from_user、message、is_read、create_time |
| GET | `/api/notes/unread-count` | NoteController::unreadCount | manage | - | `{code:0, data:{count}}` 未读推送数 |
| GET | `/api/notes/{id}` | NoteController::show | manage | - | 便签详情（含 comments.adminUser、pushes.toUser）；无权查看返回 403 |
| POST | `/api/notes` | NoteController::store | create | title（<=200 必填）、content、priority、color、pinned | `{code:0, data:便签, message:"创建成功"}` |
| PUT | `/api/notes/{id}` | NoteController::update | edit | title、content、priority、color、pinned（可部分提交） | `{code:0, message:"更新成功"}`；非本人返回 403；color 为 null 或空时统一存为空字符串（默认无色） |
| PUT | `/api/notes/{id}/toggle-status` | NoteController::toggleStatus | edit | - | 0↔1 切换完成状态，`{message:"已标记为已完成/未完成"}` |
| DELETE | `/api/notes/{id}` | NoteController::destroy | delete | - | 级联删除评论与推送记录 |
| POST | `/api/notes/batch-delete` | NoteController::batchDelete | delete | ids[]（<=100） | `{message:"成功删除X条便签"}`（仅统计本人拥有的） |

#### 评论与推送

| 方法 | 路径 | 控制器方法 | 权限 | 参数 | 返回 data 说明 |
|------|------|-----------|------|------|----------------|
| POST | `/api/notes/{noteId}/comments` | NoteController::addComment | manage | content（必填，<=1000） | `{code:0, data:评论(含评论人), message:"评论成功"}` |
| POST | `/api/notes/{noteId}/push` | NoteController::push | push | target_user_ids[]（必填）、message（<=500） | `{code:0, message:"已推送给X位管理员"}`；超人数/推给自己/非本人便签返回错误 |

#### 辅助接口

| 方法 | 路径 | 控制器方法 | 权限 | 参数 | 返回 |
|------|------|-----------|------|------|------|
| GET | `/api/admin-users` | NoteController::adminUsers | push | - | 管理员列表 `{code:0, data:[{id, username, name}]}`（排除当前用户、仅启用） |
| GET | `/api/dashboard-summary` | DashboardController::summary | manage | limit（默认 5，限定 1-20） | `{my_notes, unread_count, received_notes, total_notes, pending_notes, received_count}` |
| GET | `/api/settings` | SettingController::getSettings | manage | - | `{code:0, data:[配置分组(含 items)]}` |
| PUT | `/api/settings` | SettingController::update | manage | items{code: value} | `{code:0, message:"保存成功"}` |

### 4.2 视图路由

| 方法 | 路径 | 控制器方法 | 权限 | 说明 |
|------|------|-----------|------|------|
| GET | `/admin/cmspro/notepad/notes` | NoteController::index | manage | 便签列表页 |
| GET | `/admin/cmspro/notepad/notes/create` | NoteController::create | create | 新建便签页 |
| GET | `/admin/cmspro/notepad/notes/{id}/edit` | NoteController::edit | edit | 编辑便签页（数据前端 AJAX 回填） |
| GET | `/admin/cmspro/notepad/settings` | SettingController::index | manage | 设置页 |
| GET | `/admin/dashboard` | DashboardController::index | 不挂中间件 | 仪表盘扩展（框架原版内容 + 便签面板插入，name `admin.dashboard`）；控制器内部用 `CheckNotepadPermission::allows('manage')` 决定是否渲染便签面板，无权限时仅返回框架原版仪表盘 |

---

## 五、操作使用指南

### 5.1 环境要求

- CmsPro v5.0.0+，PHP >= 8.3
- 无外部应用依赖，安装后即可使用

### 5.2 安装部署

1. 通过应用市场安装本应用（Install 脚本自动执行迁移、将 manifest 配置同步到数据库，并为所有**已启用角色**补授 5 项权限）
2. 安装后 `GET /admin/dashboard` 由本应用接管：在框架原版仪表盘基础上插入便签面板（卸载/停用应用后恢复系统默认仪表盘）
3. **升级同样会自动补授权限**，存量站点不会出现「升级后非超管打不开」的情况；但升级之后**新建的角色**需在「角色管理」中手动勾选记事本相关权限
4. 如需调整默认参数，进入「记事本设置」页修改（每页便签数、内容最大长度、推送人数上限）
5. 存量站点若需手工补建 `pushes` 表索引，可执行 `sql/migrations/V2026102901__add_indexes_to_pushes.sql`，回滚脚本见 `sql/rollback/R2026102901__add_indexes_to_pushes.sql`

### 5.3 日常操作流程

1. **新建便签**：便签列表 → 新建 → 填写标题/内容，可选优先级、颜色、置顶 → 保存
2. **完成任务**：点击行内「完成」按钮切换状态（或详情内切换）
3. **协作推送**：在便签详情/列表选择「推送」→ 勾选目标管理员（可附留言）→ 确认；对方在「收到的便签」中查看并评论
4. **查看提醒**：仪表盘展示未读推送数；「收到的便签」支持按已读状态筛选，查看详情自动标记已读
5. **数据管理**：支持单个删除与批量删除，删除级联清理关联评论与推送记录

### 5.4 团队协作建议

- 推送留言注明事项背景，便于被推送方理解
- 重要事项用「重要/紧急」优先级标记并置顶

---

## 六、配置参数说明

### 6.1 配置来源

配置项定义于 `manifest.json` 的 `config_groups`（记事本设置组），安装时由 Install 脚本同步到系统配置表（code 前缀 `app_cmspro_notepad_`）；代码默认值位于 `Config/notepad.php`（config key：`apps.cmspro.notepad`）。

### 6.2 配置项

| 配置项 code | 说明 | 类型 | 默认值 | 取值范围/说明 |
|-------------|------|------|--------|---------------|
| `per_page` | 每页便签数 | number | `20` | 便签列表每页显示数量（列表接口 limit 缺省时使用） |
| `max_content_length` | 内容最大长度 | number | `5000` | 便签内容最大字符数，创建/更新时校验，超出返回 400 |
| `max_push_targets` | 推送人数上限 | number | `10` | 单次推送最多指定管理员数量 |

### 6.3 配置示例

```
per_page = 20
max_content_length = 5000
max_push_targets = 10
```

---

## 七、常见问题解答

**Q1：为什么安装后后台仪表盘变了？**
- 本应用接管 `/admin/dashboard`（注册路由 name `admin.dashboard`）：页面仍加载框架原版仪表盘内容，再插入便签面板，框架后续新增的仪表盘特性（如官网动态）自动随框架升级获得；停用或卸载应用后恢复系统默认仪表盘

**Q2：收到的便签打开后还显示未读？**
- 查看便签详情时系统会自动将本人收到的推送标记为已读（`getNote` 中处理），请通过「便签详情」而不是列表预览查看

**Q3：无法评论/查看某个便签？**
- 只有便签拥有者与被推送者可以查看和评论；如需要访问，请让拥有者推送给您

**Q4：推送时提示人数超限？**
- 单次推送人数受「推送人数上限」（默认 10）限制，可在记事本设置中调大

**Q5：修改设置后未生效？**
- 设置保存在系统配置表（`app_cmspro_notepad_*`），接口读取配置优先于代码默认值；确认保存成功并刷新页面

**Q6：删除的便签还能恢复吗？**
- 不能。删除会级联清理该便签的评论与推送记录，删除前请确认

**Q7：非超级管理员打开记事本页面显示「无访问权限」？**
- 该管理员所属角色未分配记事本权限。到「角色管理」编辑对应角色，勾选需要的权限（便签管理 / 创建 / 编辑 / 删除 / 推送）后保存即可。安装与升级会自动为存量启用角色补授，**之后新建的角色需手动勾选**

**Q8：为什么没有便签权限的管理员仍能打开后台首页？**
- 这是有意设计。`GET /admin/dashboard` 不挂权限中间件，否则无便签权限的管理员连后台首页都进不去；控制器内部判断权限，无权限时只渲染框架原版仪表盘、不显示便签面板

---

## 更新日志

| 版本 | 日期 | 更新人 | 变更内容 |
|------|------|--------|----------|
| v1.1.0 | 2026-10-29 | 开发团队 | 安全与健壮性：新增应用级权限中间件 `CheckNotepadPermission` 并逐路由挂载，未授权页面渲染应用自有 403 页、接口返回 HTTP 403，安装/升级为存量启用角色自动补授权限；补齐 `store`/`update`/`batchDelete`/`addComment`/`push`/`settings` 入参校验，设置项 code 强制前缀与白名单校验；`pushNote` 全程事务化并为 `pushes` 表增加唯一索引与复合索引（含 SQL 升级/回滚脚本）；仪表盘集成增加异常降级，面板渲染失败时返回框架原版仪表盘；迁移失败改为记录日志并重抛。体验优化：详情弹窗与推送弹窗抽为共享片段供列表页与仪表盘同源复用，弹窗信息密度提升（颜色标记、置顶标识、更新时间、内容限高滚动）；仪表盘面板改为局部刷新不再整页 reload；表单增加标题/内容实时字数统计、回填 loading、未保存改动离开提醒；推送选人支持搜索与「已选/上限」计数；列表与面板增加空状态引导；应用基础路径收敛为 `NOTEPAD_BASE` 常量；统计改为 SQL 聚合、配置读取增加 10 分钟缓存。测试：新增 `NotepadTestCase` 与权限、推送、更新、配置设置等测试类，共 51 个用例 205 条断言 |
| v1.0.3 | 2026-09-15 | 开发团队 | 仪表盘由整体替换改为扩展集成：渲染框架原版 `admin.dashboard` 并插入便签面板，框架后续新增的仪表盘特性（如官网动态）自动保留 |
| v1.0.1 | 2026-08-20 | 开发团队 | 修复编辑便签时「标记颜色」未选择导致更新报错（color 为 null 违反列非空约束），后端统一将 color 空值规范化为空字符串；迁移表注释语句增加 MySQL 方言兼容判断，保证 SQLite 等测试环境可迁移 |
| v1.0.0 | 2026-08-12 | 开发团队 | 初始版本 |

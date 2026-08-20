# CmsPro-记事本应用文档

> 版本：v1.0.1 | 创建日期：2026-08-12
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

此外，ServiceProvider 将 `/admin/dashboard` 路由**覆盖**为记事本仪表盘（name：`admin.dashboard`），仪表盘展示我的便签、收到的便签、未读推送数与便签统计摘要。

### 2.2 功能特性

- **我的便签**：仅本人可见/可编辑/可删除，支持标题、内容、优先级（0 普通/1 重要/2 紧急）、颜色标记、置顶、完成状态切换（status 0 未完成/1 已完成，记录状态变更时间）
- **收到的便签**：推送记录视图，含推送人、留言、已读状态；查看详情自动标记为已读
- **评论**：便签拥有者与被推送者均可评论，评论人信息随评论返回
- **推送**：便签拥有者可将便签推送给其他管理员（可带留言），每人每条便签仅一条推送记录（重复推送自动跳过），不能推送给自己，人数受 `max_push_targets` 限制
- **权限控制**：详情查看/评论要求是拥有者或被推送者；修改/删除/状态切换仅限拥有者
- **数据隔离**：所有数据按 `admin_user_id` 隔离，管理员之间仅能通过推送看到对方便签

### 2.3 权限

`manifest.json` 声明 5 项权限：`cmspro.notepad.manage`（便签管理）、`cmspro.notepad.create`（创建便签）、`cmspro.notepad.edit`（编辑便签）、`cmspro.notepad.delete`（删除便签）、`cmspro.notepad.push`（推送便签）。

---

## 三、技术架构

### 3.1 架构总览

```
后台管理（admin/cmspro/notepad，auth:admin）
  ├─ 视图路由：/notes、/notes/create、/notes/{id}/edit、/settings
  ├─ API 路由：/api/notes（CRUD）、/api/notes/{id}/comments、/api/notes/{id}/push、
  │            /api/admin-users、/api/dashboard-summary、/api/settings
  └─ 仪表盘覆盖：/admin/dashboard（admin.dashboard）
               │
               ▼
        NoteService（业务逻辑层）
          getNotes / getNote / createNote / updateNote / toggleStatus /
          deleteNote / batchDelete / addComment / pushNote /
          getUnreadCount / getAdminUsers
               │
               ▼
        数据层（三张表）
          app_cmspro_notepad_notes     便签
          app_cmspro_notepad_comments  评论
          app_cmspro_notepad_pushes    推送
```

### 3.2 技术栈与关键设计

- **技术栈**：Laravel（PHP >= 8.3）+ MySQL + Layui 后台前端
- **数据隔离**：查询、修改、删除均带 `admin_user_id` 条件或校验，杜绝越权
- **级联清理**：删除便签（单删/批量）在事务内先删评论与推送记录，保证数据一致性
- **配置驱动**：每页数量、内容长度、推送人数上限均从系统配置表读取（`app_cmspro_notepad_*`），未配置时回退 `Config/notepad.php` 默认值
- **配置同步机制**：Install 脚本从 `manifest.json` 的 `config_groups` 同步配置到数据库（新增写默认值、已有仅更新结构保留用户值、已删除清理残留、空分组删除），卸载时全量清理
- **仪表盘覆盖**：ServiceProvider 注册 `GET /admin/dashboard`（name `admin.dashboard`）覆盖系统原始仪表盘路由，卸载/停用应用后恢复系统默认
- **日期格式**：模型 `$casts` 统一输出 `Y-m-d H:i:s`

### 3.3 数据表结构

| 表名 | 用途 | 关键字段 |
|------|------|----------|
| `app_cmspro_notepad_notes` | 便签 | admin_user_id、title、content、status（0/1）、priority（0/1/2）、color、pinned、status_changed_at |
| `app_cmspro_notepad_comments` | 评论 | note_id、admin_user_id、content |
| `app_cmspro_notepad_pushes` | 推送 | note_id、from_admin_user_id、to_admin_user_id、message、is_read（0/1） |

---

## 四、接口定义

### 4.1 后台 API（前缀 `/admin/cmspro/notepad/api`，需登录后台）

> 统一返回格式：成功 `{code:0, msg:"", count?, data}`；列表接口遵循 Layui table 规范（code/msg/count/data）；业务错误返回 `{code:1, msg:"错误信息"}` 或 `{code:403/404/400}`。

#### 便签管理

| 方法 | 路径 | 控制器方法 | 参数 | 返回 data 说明 |
|------|------|-----------|------|----------------|
| GET | `/api/notes` | NoteController::list | tab（mine/received，默认 mine）、status、priority、keyword、is_read、limit | 我的便签：id、title、content（截 100 字）、status、priority、color、pinned、create_time、update_time；收到的便签：id、note_id、title、content、priority、status、from_user、message、is_read、create_time |
| GET | `/api/notes/unread-count` | NoteController::unreadCount | - | `{code:0, data:{count}}` 未读推送数 |
| GET | `/api/notes/{id}` | NoteController::show | - | 便签详情（含 comments.adminUser、pushes.toUser）；无权查看返回 403 |
| POST | `/api/notes` | NoteController::store | title（<=200 必填）、content、priority、color、pinned | `{code:0, data:便签, message:"创建成功"}` |
| PUT | `/api/notes/{id}` | NoteController::update | title、content、priority、color、pinned（可部分提交） | `{code:0, message:"更新成功"}`；非本人返回 403；color 为 null 或空时统一存为空字符串（默认无色） |
| PUT | `/api/notes/{id}/toggle-status` | NoteController::toggleStatus | - | 0↔1 切换完成状态，`{message:"已标记为已完成/未完成"}` |
| DELETE | `/api/notes/{id}` | NoteController::destroy | - | 级联删除评论与推送记录 |
| POST | `/api/notes/batch-delete` | NoteController::batchDelete | ids[] | `{message:"成功删除X条便签"}`（仅统计本人拥有的） |

#### 评论与推送

| 方法 | 路径 | 控制器方法 | 参数 | 返回 data 说明 |
|------|------|-----------|------|----------------|
| POST | `/api/notes/{noteId}/comments` | NoteController::addComment | content（必填，非空） | `{code:0, data:评论(含评论人), message:"评论成功"}` |
| POST | `/api/notes/{noteId}/push` | NoteController::push | target_user_ids[]（必填）、message（<=500） | `{code:0, message:"已推送给X位管理员"}`；超人数/推给自己/非本人便签返回错误 |

#### 辅助接口

| 方法 | 路径 | 控制器方法 | 参数 | 返回 |
|------|------|-----------|------|------|
| GET | `/api/admin-users` | NoteController::adminUsers | - | 管理员列表 `{code:0, data:[{id, username, name}]}`（排除当前用户、仅启用） |
| GET | `/api/dashboard-summary` | DashboardController::summary | limit（默认 5） | `{my_notes, unread_count, received_notes, total_notes, pending_notes, received_count}` |
| GET | `/api/settings` | SettingController::getSettings | - | `{code:0, data:[配置分组(含 items)]}` |
| PUT | `/api/settings` | SettingController::update | items{code: value} | `{code:0, message:"保存成功"}` |

### 4.2 视图路由

| 方法 | 路径 | 控制器方法 | 说明 |
|------|------|-----------|------|
| GET | `/admin/cmspro/notepad/notes` | NoteController::index | 便签列表页 |
| GET | `/admin/cmspro/notepad/notes/create` | NoteController::create | 新建便签页 |
| GET | `/admin/cmspro/notepad/notes/{id}/edit` | NoteController::edit | 编辑便签页（数据前端 AJAX 回填） |
| GET | `/admin/cmspro/notepad/settings` | SettingController::index | 设置页 |
| GET | `/admin/dashboard` | DashboardController::index | 记事本仪表盘（覆盖系统原仪表盘，name `admin.dashboard`） |

---

## 五、操作使用指南

### 5.1 环境要求

- CmsPro v5.0.0+，PHP >= 8.3
- 无外部应用依赖，安装后即可使用

### 5.2 安装部署

1. 通过应用市场安装本应用（Install 脚本自动执行迁移并将 manifest 配置同步到数据库）
2. 安装后 `GET /admin/dashboard` 自动切换为记事本仪表盘（卸载/停用应用后恢复系统默认仪表盘）
3. 如需调整默认参数，进入「记事本设置」页修改（每页便签数、内容最大长度、推送人数上限）

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
- 本应用会覆盖 `/admin/dashboard` 为记事本仪表盘（注册路由 name `admin.dashboard`），停用或卸载应用后恢复系统默认仪表盘

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

---

## 更新日志

| 版本 | 日期 | 更新人 | 变更内容 |
|------|------|--------|----------|
| v1.0.1 | 2026-08-20 | 开发团队 | 修复编辑便签时「标记颜色」未选择导致更新报错（color 为 null 违反列非空约束），后端统一将 color 空值规范化为空字符串；迁移表注释语句增加 MySQL 方言兼容判断，保证 SQLite 等测试环境可迁移 |
| v1.0.0 | 2026-08-12 | 开发团队 | 初始版本 |

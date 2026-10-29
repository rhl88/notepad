# CmsPro-记事本-使用指南

> 版本：1.1.0 | 更新日期：2026-10-29
> 应用版本：1.1.0 | 应用 ID：cmspro.notepad

---

## 一、环境要求

| 项目 | 要求 |
|------|------|
| 系统 | CmsPro v5.0.0+ |
| PHP | >= 8.3 |
| 数据库 | MySQL |
| 依赖应用 | 无（依赖系统后台管理员账号体系 `admin_users` 表与系统配置机制） |

---

## 二、安装

1. 通过 CmsPro 后台「应用市场」安装记事本应用。
2. 启用应用（`Install::install` 自动执行迁移、将 manifest 配置同步到数据库，并为所有**已启用角色**补授 5 项记事本权限）。
3. 安装后 `GET /admin/dashboard` 由本应用接管：在**框架原版仪表盘**（欢迎区、官网动态、统计卡片、系统信息）基础上插入「我的便签 / 收到的便签」面板（卸载/停用应用后恢复系统默认仪表盘）。
4. **升级**（`Install::upgrade`）同样会自动补授权限，存量站点不会出现「升级后非超管打不开」；但升级之后**新建的角色**需在「角色管理」中手动勾选。
5. 如需调整默认参数，进入「记事本设置」页修改（每页便签数、内容最大长度、推送人数上限）。

> 卸载应用会回滚业务数据表、清理 `app_cmspro_notepad_` 前缀配置。
>
> 存量站点若需手工补建 `pushes` 表索引，可执行 `sql/migrations/V2026102901__add_indexes_to_pushes.sql`，回滚脚本为 `sql/rollback/R2026102901__add_indexes_to_pushes.sql`。

---

## 三、功能验证

### 3.1 后台验证

后台入口前缀 `/admin/cmspro/notepad`（中间件 `web + auth:admin + cmspro.notepad.permission:{后缀}`）：

| 菜单 | 路径 | 所需权限 | 验证点 |
|------|------|---------|--------|
| 便签列表 | `/admin/cmspro/notepad/notes` | `manage` | 「我的便签」与「收到的便签」双视图，新建/编辑/删除/批量删除/状态切换，查看详情（含评论与推送记录）、评论、推送 |
| 记事本设置 | `/admin/cmspro/notepad/settings` | `manage` | 每页便签数、内容最大长度、推送人数上限 |
| 仪表盘 | `/admin/dashboard` | 不挂中间件 | 框架原版仪表盘内容 + 我的便签、收到的便签、未读推送数与便签统计摘要 |

**验证步骤**：
1. 新建一条便签（填写标题/内容，可选优先级、颜色、置顶），观察标题与内容输入框下方的实时字数统计。
2. 点击行内「完成」按钮切换状态。
3. 在便签详情/列表选择「推送」，用搜索框筛选并勾选目标管理员（可附留言），确认「已选 / 上限」计数正确后提交。
4. 用另一管理员账号登录，在「收到的便签」中查看并评论。
5. **权限验证**：新建一个不勾选任何记事本权限的角色，用该角色下的管理员登录 —— 访问 `/admin/cmspro/notepad/notes` 应显示应用自有的「无访问权限」403 页（而非 500 报错），而 `/admin/dashboard` 仍可正常打开、只是不显示便签面板。

### 3.2 API 验证

统一返回格式：成功 `{code:0, msg:"", count?, data}`；列表接口遵循 Layui table 规范；业务错误返回 `{code:1, msg:"错误信息"}` 或 `{code:403/404/400}`。

```bash
# 便签列表（我的便签）
curl "http://www.cmspro.com/admin/cmspro/notepad/api/notes?tab=mine"

# 新建便签
curl -X POST "http://www.cmspro.com/admin/cmspro/notepad/api/notes" \
  -H "Content-Type: application/json" \
  -d '{"title": "待办事项", "content": "完成周报", "priority": 1}'

# 推送便签
curl -X POST "http://www.cmspro.com/admin/cmspro/notepad/api/notes/1/push" \
  -H "Content-Type: application/json" \
  -d '{"target_user_ids": [2], "message": "请查收"}'

# 未读推送数
curl "http://www.cmspro.com/admin/cmspro/notepad/api/notes/unread-count"
```

### 3.3 功能特性验证

- **我的便签**：仅本人可见/可编辑/可删除，支持优先级（0 普通/1 重要/2 紧急）、颜色标记、置顶、完成状态切换。
- **收到的便签**：推送记录视图，含推送人、留言、已读状态；查看详情自动标记为已读。
- **评论**：便签拥有者与被推送者均可评论。
- **推送**：便签拥有者可将便签推送给其他管理员（可带留言），每人每条便签仅一条推送记录（重复推送自动跳过），不能推送给自己，人数受 `max_push_targets` 限制。
- **权限控制（双层）**：① 功能权限由应用级中间件按路由校验（`manage`/`create`/`edit`/`delete`/`push`），未授权页面显示应用自有 403 页、接口返回 HTTP 403；② 数据权限在业务层校验 —— 详情查看/评论要求是拥有者或被推送者，修改/删除/状态切换仅限拥有者。
- **数据隔离**：所有数据按 `admin_user_id` 隔离。
- **交互体验**：标题/内容实时字数统计与上限提示；表单有未保存改动时关闭弹窗二次确认；编辑回填期间显示 loading；列表与仪表盘面板均提供空状态引导文案。
- **仪表盘局部刷新**：面板内新建、评论、切换状态、关闭子弹窗后仅重拉摘要接口局部重渲染，不整页刷新；未读徽章数量归零时自动隐藏。

---

## 四、卸载验证

1. 在 CmsPro 后台卸载记事本应用。
2. 确认 `app_cmspro_notepad_notes`、`app_cmspro_notepad_comments`、`app_cmspro_notepad_pushes` 三张表被回滚删除。
3. 确认 `app_cmspro_notepad_` 前缀的配置组/配置项被清理。
4. 确认 `/admin/dashboard` 恢复为系统默认仪表盘。

---

## 五、目录结构

```
app/Apps/CmsproNotepad/
├── ServiceProvider.php       # 服务提供者：路由/视图/配置/中间件别名 + 仪表盘接管扩展
├── Install.php               # 安装/卸载/升级：迁移 + manifest 配置三向同步 + 存量角色授权
├── manifest.json             # 应用元数据唯一声明源（菜单/权限/配置分组）
├── Config/notepad.php        # 应用默认配置
├── Routes/admin.php          # 后台页面 + API 路由（逐路由挂权限中间件）
├── Controllers/Admin/        # Dashboard / Note / Setting
├── Middleware/               # CheckNotepadPermission 应用级权限校验
├── Services/NoteService.php  # 便签业务逻辑层
├── Models/                   # Note / NoteComment / Push
├── Migrations/               # 便签表、评论表、推送表（含 pushes 索引迁移）
├── sql/                      # migrations/ 升级脚本 + rollback/ 回滚脚本
├── Views/Admin/              # notes / settings / dashboard 模板 + _js_base、errors/ 等共享片段
├── Tests/                    # NotepadTestCase + Feature/ 应用级功能测试
└── doc/                      # 应用文档
```

---

## 六、常见问题

### Q1：为什么安装后后台仪表盘变了？

本应用接管 `/admin/dashboard`（注册路由 name `admin.dashboard`）：页面仍加载框架原版仪表盘内容，再将「我的便签 / 收到的便签」面板插入其中，框架后续新增的仪表盘特性（如官网动态）自动随框架升级获得。停用或卸载应用后恢复系统默认仪表盘。

### Q2：收到的便签打开后还显示未读？

查看便签详情时系统会自动将本人收到的推送标记为已读（`getNote` 中处理），请通过「便签详情」而不是列表预览查看。

### Q3：无法评论/查看某个便签？

只有便签拥有者与被推送者可以查看和评论；如需要访问，请让拥有者推送给您。

### Q4：推送时提示人数超限？

单次推送人数受「推送人数上限」（默认 10）限制，可在记事本设置中调大。

### Q5：修改设置后未生效？

设置保存在系统配置表（`app_cmspro_notepad_*`），接口读取配置优先于代码默认值；确认保存成功并刷新页面。

### Q6：删除的便签还能恢复吗？

不能。删除会级联清理该便签的评论与推送记录，删除前请确认。

### Q7：非超级管理员打开记事本页面显示「无访问权限」？

该管理员所属角色未分配记事本权限。到「角色管理」编辑对应角色，勾选需要的权限（便签管理 / 创建 / 编辑 / 删除 / 推送）后保存即可。安装与升级会自动为存量启用角色补授，**之后新建的角色需手动勾选**。

### Q8：为什么没有便签权限的管理员仍能打开后台首页？

有意设计。`GET /admin/dashboard` 不挂权限中间件，否则无便签权限的管理员连后台首页都进不去；控制器内部判断权限，无权限时只渲染框架原版仪表盘、不显示便签面板。

---

## 七、版本历史

| 版本 | 日期 | 更新人 | 变更内容 |
|------|------|--------|----------|
| 1.0.0 | 2026-08-13 | 开发团队 | 初始版本（对应应用 v1.0.1） |
| 1.1.0 | 2026-10-29 | 开发团队 | 补充权限相关说明：安装/升级自动为存量启用角色补授权限、后台验证表格增加「所需权限」列与 403 验证步骤、功能特性改为「双层权限控制」；新增交互体验与仪表盘局部刷新说明；目录结构补 `Middleware/`、`sql/`、`Tests/`、`manifest.json`；常见问题新增 Q7/Q8（对应应用 v1.1.0） |

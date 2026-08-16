# CmsPro-记事本-使用指南

> 版本：1.0.0 | 更新日期：2026-08-13
> 应用版本：1.0.1 | 应用 ID：cmspro.notepad

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
2. 启用应用（`Install::install` 自动执行迁移并将 manifest 配置同步到数据库）。
3. 安装后 `GET /admin/dashboard` 自动切换为记事本仪表盘（卸载/停用应用后恢复系统默认仪表盘）。
4. 如需调整默认参数，进入「记事本设置」页修改（每页便签数、内容最大长度、推送人数上限）。

> 卸载应用会回滚业务数据表、清理 `app_cmspro_notepad_` 前缀配置。

---

## 三、功能验证

### 3.1 后台验证

后台入口前缀 `/admin/cmspro/notepad`（中间件 `web + auth:admin`）：

| 菜单 | 路径 | 验证点 |
|------|------|--------|
| 便签列表 | `/admin/cmspro/notepad/notes` | 「我的便签」与「收到的便签」双视图，新建/编辑/删除/批量删除/状态切换，查看详情（含评论与推送记录）、评论、推送 |
| 记事本设置 | `/admin/cmspro/notepad/settings` | 每页便签数、内容最大长度、推送人数上限 |
| 仪表盘 | `/admin/dashboard` | 我的便签、收到的便签、未读推送数与便签统计摘要 |

**验证步骤**：
1. 新建一条便签（填写标题/内容，可选优先级、颜色、置顶）。
2. 点击行内「完成」按钮切换状态。
3. 在便签详情/列表选择「推送」，勾选目标管理员（可附留言）并确认。
4. 用另一管理员账号登录，在「收到的便签」中查看并评论。

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
- **权限控制**：详情查看/评论要求是拥有者或被推送者；修改/删除/状态切换仅限拥有者。
- **数据隔离**：所有数据按 `admin_user_id` 隔离。

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
├── ServiceProvider.php       # 服务提供者：路由/视图/配置 + 仪表盘覆盖
├── Install.php               # 安装/卸载/升级：迁移 + manifest 配置三向同步
├── Config/notepad.php        # 应用默认配置
├── Routes/admin.php          # 后台页面 + API 路由
├── Controllers/Admin/        # Dashboard / Note / Setting
├── Services/NoteService.php  # 便签业务逻辑层
├── Models/                   # Note / NoteComment / Push
├── Migrations/               # 便签表、评论表、推送表
└── Views/Admin/              # notes / settings / dashboard 模板
```

---

## 六、常见问题

### Q1：为什么安装后后台仪表盘变了？

本应用会覆盖 `/admin/dashboard` 为记事本仪表盘（注册路由 name `admin.dashboard`），停用或卸载应用后恢复系统默认仪表盘。

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

---

## 七、版本历史

| 版本 | 日期 | 更新人 | 变更内容 |
|------|------|--------|----------|
| 1.0.0 | 2026-08-13 | 开发团队 | 初始版本（对应应用 v1.0.1） |

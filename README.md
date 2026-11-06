# 记事本 应用文档

> 应用ID：`cmspro.notepad` | 文档版本：1.2.1 | 应用版本：1.2.0 | 依赖：PHP ≥ 8.3, CmsPro ≥ 5.0.0

- 官方地址：https://www.cmspro.cn/apps/cmspro.notepad
- 本应用是 CmsPro v5 后台便签记录与协作应用，基于 CMSPRO 运行，请先安装 [CMSPRO](https://www.cmspro.cn/help/2)

## 1. 应用概述

面向 CmsPro 后台管理员的便签记录与团队协作应用，实现业务闭环：

- **便签管理**：创建个人便签，支持标题/内容/优先级（普通/重要/紧急）/颜色标记/置顶/完成状态，按管理员隔离，仅本人可改可删
- **推送协作**：便签拥有者可将便签推送给其他管理员（可附留言），每人每条仅一条推送记录、重复推送自动跳过
- **评论互动**：便签拥有者与被推送者均可评论，评论人信息随评论返回
- **提醒与仪表盘**：未读推送数提示；安装后 `/admin/dashboard` 由应用接管——框架原版仪表盘内容 + 插入便签面板（我的便签/收到的便签/未读推送/统计摘要），面板渲染异常时自动降级为框架原版仪表盘
- **桌面小工具**：便签概览（统计 + 快捷新增）与桌面便签（内容查看、颜色标记、内容编辑、便签切换），通过 `widgets` 声明接入框架桌面
- **权限控制**：5 项权限（manage/create/edit/delete/push）通过应用级中间件 `cmspro.notepad.permission:{suffix}` 逐路由校验，未授权页面返回应用自有 403 页、接口返回 HTTP 403；安装/升级时自动为存量启用角色补授权限
- **配置驱动**：每页便签数、内容最大长度、推送人数上限均从系统配置读取（缓存 10 分钟）

## 2. 应用截图

> 截图来源：[官方应用页 · cmspro.notepad](https://www.cmspro.cn/apps/cmspro.notepad)（官网标注 v1.0.3）。每次发布与更新必须核对本节截图与官网「应用截图」是否一致，不一致时以官网为准同步更新。

![应用截图 1](https://www.cmspro.cn/uploads/2026/07/25/Zlz3hzSrrPt0gPtsn0S3mo7DrUyNOYm64WtjOrOC.png)

![应用截图 2](https://www.cmspro.cn/uploads/2026/07/25/lHdqjgOcKZTSxqznxh16w487kzSqmYOL2De9JCYy.png)

![应用截图 3](https://www.cmspro.cn/uploads/2026/07/25/Q7vtdEny2veecYMt46nPQEIx3cqvdpy2gmwzAEpP.png)

![应用截图 4](https://www.cmspro.cn/uploads/2026/07/25/oXJ9y0PGbHSfdEuyzfkRhf9UTpXWVBKxWL3NEGHK.png)

![应用截图 5](https://www.cmspro.cn/uploads/2026/07/25/WUydaSysgLNsHw3ZPah7dqTsqNDnjMqFFRu2ZWg8.png)

![应用截图 6](https://www.cmspro.cn/uploads/2026/07/25/kg6RYblBHJQ5HaatK8Hhzn8OQt4H0NyW8MI0ERbz.png)

## 3. 目录结构

```
CmsproNotepad/
├── Routes/admin.php                   # 后台页面与 API 路由（逐路由挂权限中间件）
├── Routes/admin_api.php               # 站内数据接口路由（/api/admin/cmspro/notepad，桌面小工具等）
├── Controllers/Admin/                 # 后台控制器（便签/仪表盘/设置/小工具接口）
├── Middleware/CheckNotepadPermission.php # 应用级权限校验中间件
├── Models/
│   ├── Note.php                       # 便签模型
│   ├── NoteComment.php                # 评论模型
│   └── Push.php                       # 推送模型
├── Services/NoteService.php           # 便签业务逻辑
├── Migrations/                        # 应用表迁移（app_cmspro_notepad_ 前缀）
├── sql/                               # 升级 SQL 脚本与回滚脚本（migrations/ + rollback/）
├── Views/Admin/                       # 后台视图（列表/表单/设置/仪表盘 + _js_base 等共享片段）
├── Tests/                             # 应用级功能测试（NotepadTestCase + Feature/），打包时排除
├── Install.php                        # 安装/卸载/升级逻辑
├── ServiceProvider.php                # 路由、视图、中间件别名注册与仪表盘接管扩展
├── manifest.json                      # 应用元数据唯一声明源（菜单/配置组/权限/桌面小工具）
├── .exportignore                      # 打包排除规则（.git/、Tests/）
├── icon.svg                           # 应用图标
└── doc/                               # 应用文档
```

## 4. 应用文档

| 文档 | 说明 |
|------|------|
| [CmsPro-记事本应用文档.md](doc/CmsPro-记事本应用文档.md) | 功能说明、架构、接口定义、配置参数与 FAQ |
| [CmsPro-记事本-使用指南.md](doc/CmsPro-记事本-使用指南.md) | 安装、配置、日常操作指南 |
| [CmsPro-记事本-扩展指南.md](doc/CmsPro-记事本-扩展指南.md) | Service 调用、扩展点说明 |

## 5. 验证

```bash
# 在 code 目录执行（应用级测试单独运行，不纳入框架回归）
php artisan test app/Apps/CmsproNotepad/Tests

# 结果：82 passed (315 assertions)
```
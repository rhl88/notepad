# 记事本 应用文档

> 应用ID：`cmspro.notepad` | 文档版本：1.0.2 | 应用版本：1.0.3 | 依赖：PHP ≥ 8.3, CmsPro ≥ 5.0.0

- 官方地址：https://www.cmspro.cn/apps/cmspro.notepad
- 本应用是 CmsPro v5 后台便签记录与协作应用，基于 CMSPRO 运行，请先安装 [CMSPRO](https://www.cmspro.cn/help/2)

## 1. 应用概述

面向 CmsPro 后台管理员的便签记录与团队协作应用，实现业务闭环：

- **便签管理**：创建个人便签，支持标题/内容/优先级（普通/重要/紧急）/颜色标记/置顶/完成状态，按管理员隔离，仅本人可改可删
- **推送协作**：便签拥有者可将便签推送给其他管理员（可附留言），每人每条仅一条推送记录、重复推送自动跳过
- **评论互动**：便签拥有者与被推送者均可评论，评论人信息随评论返回
- **提醒与仪表盘**：未读推送数提示；安装后 `/admin/dashboard` 由应用接管——框架原版仪表盘内容 + 插入便签面板（我的便签/收到的便签/未读推送/统计摘要）
- **配置驱动**：每页便签数、内容最大长度、推送人数上限均从系统配置读取

## 2. 目录结构

```
CmsproNotepad/
├── Config/notepad.php                 # 默认配置值
├── Routes/admin.php                   # 后台页面与 API 路由
├── Controllers/Admin/                 # 后台控制器（便签/仪表盘/设置）
├── Models/
│   ├── Note.php                       # 便签模型
│   ├── NoteComment.php                # 评论模型
│   └── Push.php                       # 推送模型
├── Services/NoteService.php           # 便签业务逻辑
├── Migrations/                        # 应用表迁移（app_cmspro_notepad_ 前缀）
├── Views/Admin/                       # 后台视图（列表/表单/设置/仪表盘）
├── Tests/Feature/                     # 应用级功能测试
├── Install.php                        # 安装/卸载/升级逻辑
├── ServiceProvider.php                # 路由、视图注册与仪表盘接管扩展
├── manifest.json                      # 应用元数据唯一声明源
└── doc/                               # 应用文档
```

## 3. 应用文档

| 文档 | 说明 |
|------|------|
| [CmsPro-记事本应用文档.md](doc/CmsPro-记事本应用文档.md) | 功能说明、架构、接口定义、配置参数与 FAQ |
| [CmsPro-记事本-使用指南.md](doc/CmsPro-记事本-使用指南.md) | 安装、配置、日常操作指南 |
| [CmsPro-记事本-扩展指南.md](doc/CmsPro-记事本-扩展指南.md) | Service 调用、扩展点说明 |

<?php

namespace App\Apps\CmsproNotepad\Tests\Feature;

use App\Apps\CmsproNotepad\Tests\NotepadTestCase;
use App\Models\AdminRole;
use App\Models\AdminUser;

/**
 * 应用权限中间件测试
 *
 * 覆盖 CheckNotepadPermission 的四条核心语义：
 * 权限码未注册时放行（兼容未升级站点）、非超管无权限返回 403、
 * 授予对应权限后放行、超级管理员始终放行。
 */
class PermissionTest extends NotepadTestCase
{
    private AdminUser $admin;

    private AdminRole $role;

    protected function setUp(): void
    {
        parent::setUp();

        $this->role = $this->createRole('notepad_operator', '记事本操作员');
        $this->admin = $this->createAdmin('notepad_perm');
        $this->admin->roles()->attach($this->role->id);
    }

    public function test_权限码未注册时接口放行(): void
    {
        // 未调用 registerPermissions()，权限表中无本应用权限码，中间件按兜底语义放行，
        // 避免升级过程中权限尚未写入就锁死全部功能
        $this->actingAs($this->admin, 'admin')
            ->getJson('/admin/cmspro/notepad/api/notes')
            ->assertOk()
            ->assertJsonPath('code', 0);
    }

    public function test_未授权管理员访问接口被拒绝(): void
    {
        $this->registerPermissions();

        $this->actingAs($this->admin, 'admin')
            ->getJson('/admin/cmspro/notepad/api/notes')
            ->assertStatus(403)
            ->assertJsonPath('code', 40301);
    }

    public function test_未授权管理员访问页面被拒绝(): void
    {
        $this->registerPermissions();

        // 页面请求非 JSON，中间件渲染应用自有的 403 页面；
        // 不能用 abort(403)，框架缺少 HttpException(403) 渲染器与 errors.403 视图，会退化成 500
        $this->actingAs($this->admin, 'admin')
            ->get('/admin/cmspro/notepad/notes')
            ->assertStatus(403)
            ->assertSee('无访问权限')
            ->assertSee('角色管理');
    }

    public function test_授予manage权限后可访问列表接口(): void
    {
        $this->registerPermissions();
        $this->grantRole($this->role, ['manage']);

        $this->actingAs($this->admin, 'admin')
            ->getJson('/admin/cmspro/notepad/api/notes')
            ->assertOk()
            ->assertJsonPath('code', 0);
    }

    public function test_授予权限后列表页与表单页正常渲染(): void
    {
        $this->registerPermissions();
        $this->grantRole($this->role, ['manage', 'create', 'edit']);

        $note = $this->createNote($this->admin);

        // 页面渲染可一并验证 Blade 模板（含 @verbatim 包裹的 Layui 模板块）编译通过
        $this->actingAs($this->admin, 'admin')
            ->get('/admin/cmspro/notepad/notes')
            ->assertOk()
            ->assertSee('新建便签')
            // 推送弹窗共享片段已接入：选人搜索框 + 已选计数
            ->assertSee('push-user-search', false)
            ->assertSee('push-selected-count', false)
            // 详情弹窗共享片段已接入：标题带颜色标记，信息表补充更新时间、内容区限高滚动
            ->assertSee('buildNoteTitleCell', false)
            ->assertSee('更新时间', false)
            ->assertSee('max-height:220px', false)
            // URL 前缀已收敛为基础路径常量，视图内不再散落硬编码
            ->assertSee("var NOTEPAD_BASE = '/admin/cmspro/notepad';", false);

        $this->actingAs($this->admin, 'admin')
            ->get('/admin/cmspro/notepad/notes/create')
            ->assertOk()
            // 标题 / 内容实时字数统计与未保存改动判断
            ->assertSee('title-count', false)
            ->assertSee('content-count', false)
            ->assertSee('isFormDirty', false);

        // 编辑入口使用 /notes/{id}/edit（edit 权限），表单页由前端解析路径回填
        $this->actingAs($this->admin, 'admin')
            ->get('/admin/cmspro/notepad/notes/' . $note->id . '/edit')
            ->assertOk();
    }

    public function test_权限按接口细粒度隔离(): void
    {
        $this->registerPermissions();
        $this->grantRole($this->role, ['manage']);

        // 拥有 manage 不代表拥有 create，创建接口仍应拒绝
        $this->actingAs($this->admin, 'admin')
            ->postJson('/admin/cmspro/notepad/api/notes', ['title' => '新便签', 'content' => '内容'])
            ->assertStatus(403)
            ->assertJsonPath('code', 40301);

        $this->grantRole($this->role, ['create']);

        $this->actingAs($this->admin, 'admin')
            ->postJson('/admin/cmspro/notepad/api/notes', ['title' => '新便签', 'content' => '内容'])
            ->assertOk()
            ->assertJsonPath('code', 0);
    }

    public function test_删除与推送权限相互独立(): void
    {
        $this->registerPermissions();
        $this->grantRole($this->role, ['manage', 'delete']);

        $note = $this->createNote($this->admin);

        $this->actingAs($this->admin, 'admin')
            ->deleteJson('/admin/cmspro/notepad/api/notes/' . $note->id)
            ->assertOk()
            ->assertJsonPath('code', 0);

        // 未授予 push，推送选人用的管理员列表接口应被拒绝
        $this->actingAs($this->admin, 'admin')
            ->getJson('/admin/cmspro/notepad/api/admin-users')
            ->assertStatus(403)
            ->assertJsonPath('code', 40301);
    }

    public function test_超级管理员始终放行(): void
    {
        $this->registerPermissions();

        $superAdmin = $this->createSuperAdmin();

        $this->actingAs($superAdmin, 'admin')
            ->getJson('/admin/cmspro/notepad/api/notes')
            ->assertOk()
            ->assertJsonPath('code', 0);

        $this->actingAs($superAdmin, 'admin')
            ->getJson('/admin/cmspro/notepad/api/admin-users')
            ->assertOk()
            ->assertJsonPath('code', 0);
    }

    public function test_未登录访问接口被拦截(): void
    {
        $this->registerPermissions();

        $this->getJson('/admin/cmspro/notepad/api/notes')->assertStatus(401);
    }
}

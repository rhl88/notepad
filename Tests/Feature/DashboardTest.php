<?php

namespace App\Apps\CmsproNotepad\Tests\Feature;

use App\Apps\CmsproNotepad\Tests\NotepadTestCase;
use App\Models\AdminUser;
use Illuminate\Support\Facades\View;

/**
 * 记事本仪表盘集成测试
 * 覆盖：/admin/dashboard 输出框架原版内容（官网动态等）+ 应用便签面板插入，而非整体替换；
 * 以及无便签权限的管理员只看到框架原版仪表盘，面板渲染异常时降级为框架原版仪表盘。
 */
class DashboardTest extends NotepadTestCase
{
    private AdminUser $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // 框架仪表盘的官网动态、应用卡片等区块仅对超级管理员渲染，故此处用超管
        $this->admin = $this->createSuperAdmin();
    }

    public function test_dashboard_同时包含框架内容与应用便签面板(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->get('/admin/dashboard')
            ->assertOk();

        // 框架原版特性（整体替换方案下会丢失的内容）
        $response->assertSee('portal-moment-list'); // 官网动态容器
        $response->assertSee('stat-users');         // 统计卡片
        $response->assertSee('系统信息');            // 系统信息卡片
        $response->assertSee('CmsProUi/component/layui/layui.js', false); // 框架静态资源

        // 应用便签面板
        $response->assertSee('my-notes-list');        // 我的便签
        $response->assertSee('received-notes-list');  // 收到的便签
        $response->assertSee('quick-note-input');     // 快速创建

        // 详情弹窗与推送弹窗均复用共享片段，与便签列表页同源
        $response->assertSee('push-user-search', false);
        $response->assertSee('window.openNoteDetail = function (noteId)', false);

        // 面板刷新统一走摘要接口局部重渲染，不再整页刷新
        $response->assertSee('function loadSummary()', false);
        $response->assertDontSee('location.reload()', false);
    }

    public function test_dashboard_便签面板插入系统信息之前(): void
    {
        $content = $this->actingAs($this->admin, 'admin')
            ->get('/admin/dashboard')
            ->assertOk()
            ->getContent();

        $panelPos = strpos($content, 'my-notes-list');
        $infoPos = strpos($content, '系统信息');
        $this->assertNotFalse($panelPos);
        $this->assertNotFalse($infoPos);
        $this->assertLessThan($infoPos, $panelPos, '便签面板应插到系统信息卡片之前');
    }

    public function test_dashboard_便签脚本位于layui加载之后且在body闭合之前(): void
    {
        $content = $this->actingAs($this->admin, 'admin')
            ->get('/admin/dashboard')
            ->assertOk()
            ->getContent();

        $layuiPos = strpos($content, 'CmsProUi/component/layui/layui.js');
        $scriptPos = strpos($content, 'dashboard-summary');
        $bodyEndPos = strpos($content, '</body>');
        $this->assertNotFalse($layuiPos);
        $this->assertNotFalse($scriptPos);
        $this->assertNotFalse($bodyEndPos);
        $this->assertGreaterThan($layuiPos, $scriptPos, '便签脚本须在 layui.js 之后执行');
        $this->assertLessThan($bodyEndPos, $scriptPos, '便签脚本应插到 </body> 之前');

        // 基础路径常量由片段注入，必须落在脚本半区（首个 <script 之后），
        // 否则会被拆到面板 HTML 里导致 JS 报未定义
        $basePos = strpos($content, "var NOTEPAD_BASE = '/admin/cmspro/notepad';");
        $this->assertNotFalse($basePos, '仪表盘应注入基础路径常量');
        $this->assertGreaterThan($layuiPos, $basePos, '基础路径常量须在 layui.js 之后声明');
        $this->assertLessThan($bodyEndPos, $basePos, '基础路径常量应插到 </body> 之前');
    }

    public function test_未登录访问dashboard被拦截(): void
    {
        $this->get('/admin/dashboard')->assertRedirect();
    }

    public function test_无便签权限的管理员仍能看到框架原版仪表盘(): void
    {
        // 权限已注册但当前管理员未被授予：仪表盘路由不能挂中间件，
        // 否则整个后台首页 403；应仅隐藏便签面板。
        $this->registerPermissions();
        $limited = $this->createAdmin('notepad_limited');
        $limited->roles()->attach($this->createRole('notepad_none', '无便签权限')->id);

        $response = $this->actingAs($limited, 'admin')
            ->get('/admin/dashboard')
            ->assertOk();

        $response->assertSee('系统信息');
        $response->assertDontSee('my-notes-list');
        $response->assertDontSee('quick-note-input');
    }

    public function test_应用面板渲染异常时降级为框架原版仪表盘(): void
    {
        // 把应用视图命名空间指向临时目录，用一个必然抛异常的模板模拟面板渲染故障，
        // 验证控制器捕获后降级返回框架原版仪表盘，而不是让整个后台首页 500
        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'notepad_view_' . uniqid();
        mkdir($tempDir . DIRECTORY_SEPARATOR . 'Admin', 0777, true);
        file_put_contents(
            $tempDir . DIRECTORY_SEPARATOR . 'Admin' . DIRECTORY_SEPARATOR . 'dashboard.blade.php',
            '@php throw new \RuntimeException("模拟便签面板渲染失败"); @endphp'
        );

        View::replaceNamespace('cmspro.notepad', $tempDir);

        try {
            $response = $this->actingAs($this->admin, 'admin')
                ->get('/admin/dashboard')
                ->assertOk();

            $response->assertSee('系统信息');
            $response->assertDontSee('my-notes-list');
        } finally {
            array_map('unlink', glob($tempDir . DIRECTORY_SEPARATOR . 'Admin' . DIRECTORY_SEPARATOR . '*'));
            rmdir($tempDir . DIRECTORY_SEPARATOR . 'Admin');
            rmdir($tempDir);
        }
    }

    public function test_摘要接口返回统一响应结构(): void
    {
        $this->createNote($this->admin, ['status' => 0]);
        $this->createNote($this->admin, ['status' => 1]);

        $this->actingAs($this->admin, 'admin')
            ->getJson('/admin/cmspro/notepad/api/dashboard-summary')
            ->assertOk()
            ->assertJsonStructure([
                'code',
                'message',
                'timestamp',
                'data' => [
                    'my_notes',
                    'unread_count',
                    'received_notes',
                    'total_notes',
                    'pending_notes',
                    'received_count',
                ],
            ])
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.total_notes', 2)
            ->assertJsonPath('data.pending_notes', 1)
            ->assertJsonPath('data.unread_count', 0);
    }

    public function test_摘要接口limit参数被限制在合法区间(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->createNote($this->admin, ['title' => '便签' . $i]);
        }

        // limit=999 超出上限 20，不应一次返回全部记录的越界数量
        $this->actingAs($this->admin, 'admin')
            ->getJson('/admin/cmspro/notepad/api/dashboard-summary?limit=999')
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonCount(5, 'data.my_notes');

        // limit=0 为非法值，应被收敛到下限 1 而非报错
        $this->actingAs($this->admin, 'admin')
            ->getJson('/admin/cmspro/notepad/api/dashboard-summary?limit=0')
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonCount(1, 'data.my_notes');
    }

    public function test_摘要接口只返回待办便签且不含已完成项(): void
    {
        $this->createNote($this->admin, ['title' => '待办事项', 'status' => 0]);
        $this->createNote($this->admin, ['title' => '已完成事项', 'status' => 1]);

        $response = $this->actingAs($this->admin, 'admin')
            ->getJson('/admin/cmspro/notepad/api/dashboard-summary')
            ->assertOk();

        $titles = array_column($response->json('data.my_notes'), 'title');
        $this->assertSame(['待办事项'], $titles);
    }
}

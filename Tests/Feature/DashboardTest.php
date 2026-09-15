<?php

namespace App\Apps\CmsproNotepad\Tests\Feature;

use App\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * 记事本仪表盘集成测试
 * 覆盖：/admin/dashboard 输出框架原版内容（官网动态等）+ 应用便签面板插入，而非整体替换
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private AdminUser $admin;

    protected function setUp(): void
    {
        parent::setUp();
        // 内存库中 apps 表为空，应用路由不会自动注册，手动注册以启用待测路由
        $this->app->register(\App\Apps\CmsproNotepad\ServiceProvider::class);
        $this->createTables();

        $this->admin = AdminUser::create([
            'username' => 'notepad_dash_' . uniqid(),
            'password' => bcrypt('123456'),
            'status' => 1,
        ]);
    }

    protected function createTables(): void
    {
        if (!Schema::hasTable('app_cmspro_notepad_notes')) {
            (require base_path('app/Apps/CmsproNotepad/Migrations/2026_07_25_000001_create_app_cmspro_notepad_notes_table.php'))->up();
        }
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
    }

    public function test_未登录访问dashboard被拦截(): void
    {
        $this->get('/admin/dashboard')->assertRedirect();
    }
}

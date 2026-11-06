<?php

namespace App\Apps\CmsproNotepad\Tests\Feature;

use App\Apps\CmsproNotepad\Services\NoteService;
use App\Apps\CmsproNotepad\Tests\NotepadTestCase;
use App\Models\AdminUser;
use App\Models\ConfigItem;
use Illuminate\Support\Facades\Cache;

/**
 * 应用配置与设置接口测试
 *
 * 重点覆盖两类历史缺陷的回归：
 * 1. 配置 code 前缀不一致（框架写入 app_cmspro_notepad_*，应用误拼 app_cmspro.notepad_*），
 *    会导致设置页出现重复条目、保存被白名单拒绝、per_page 配置永久失效；
 * 2. 每页条数被控制器塞入默认值，导致后台配置的 per_page 永不生效。
 */
class ConfigSettingTest extends NotepadTestCase
{
    private AdminUser $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createAdmin('notepad_config');
    }

    public function test_设置接口保存的每页条数能被列表接口读取(): void
    {
        $this->seedConfig('per_page', '20');
        $this->createNotes(5);

        $this->actingAs($this->admin, 'admin')
            ->putJson('/admin/cmspro/notepad/api/settings', ['items' => ['per_page' => 2]])
            ->assertOk()
            ->assertJsonPath('code', 0);

        // 不传 limit 时应回落到刚保存的配置值，证明写入与读取使用同一 code
        $this->actingAs($this->admin, 'admin')
            ->getJson('/admin/cmspro/notepad/api/notes')
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('count', 5);
    }

    public function test_未配置每页条数时回落到代码默认值(): void
    {
        $this->createNotes(3);

        $this->actingAs($this->admin, 'admin')
            ->getJson('/admin/cmspro/notepad/api/notes')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_前端传入的limit优先于配置且被限制在上限内(): void
    {
        $this->seedConfig('per_page', '2');
        $this->createNotes(3);

        // limit=1 小于配置值，以前端传参为准
        $this->actingAs($this->admin, 'admin')
            ->getJson('/admin/cmspro/notepad/api/notes?limit=1')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // limit 超出上限 100 时被收敛，不因非法分页参数拖垮查询
        $this->actingAs($this->admin, 'admin')
            ->getJson('/admin/cmspro/notepad/api/notes?limit=99999')
            ->assertOk()
            ->assertJsonPath('code', 0);
    }

    /**
     * 列表页渲染的默认分页条数取自配置项 per_page
     *
     * 回归点：Layui table 每次请求都会带上 page.limit，视图曾写死 limit: 20，
     * 使后台配置的 per_page 在真实 UI 流程下永不生效（接口层的配置回落被前端参数覆盖）。
     */
    public function test_列表页默认分页条数取自配置(): void
    {
        $this->seedConfig('per_page', '5');
        $this->createNotes(8);

        $this->actingAs($this->admin, 'admin')
            ->get('/admin/cmspro/notepad/notes')
            ->assertOk()
            ->assertSee("limit: parseInt('5', 10)", false);

        // 模拟真实 UI 流程：Layui 携带页面渲染出的 limit 请求接口，条数应与配置一致
        $this->actingAs($this->admin, 'admin')
            ->getJson('/admin/cmspro/notepad/api/notes?limit=5')
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('count', 8);
    }

    public function test_配置接口返回的code均为下划线前缀(): void
    {
        $this->seedConfig('per_page', '20');
        $this->seedConfig('max_content_length', '5000');
        $this->seedConfig('max_push_targets', '10');

        $response = $this->actingAs($this->admin, 'admin')
            ->getJson('/admin/cmspro/notepad/api/settings')
            ->assertOk()
            ->assertJsonPath('code', 0);

        $codes = [];
        foreach ($response->json('data') as $group) {
            foreach ($group['items'] ?? [] as $item) {
                $codes[] = $item['code'];
            }
        }

        $this->assertNotEmpty($codes);
        foreach ($codes as $code) {
            // 前端依赖 replace('app_cmspro_notepad_', '') 提取短名，含点号的 code 无法被识别
            $this->assertStringStartsWith('app_cmspro_notepad_', $code);
            $this->assertStringNotContainsString('.', $code);
        }
    }

    public function test_白名单外的配置项被拒绝(): void
    {
        $this->seedConfig('per_page', '20');

        // 尝试借 items 参数越权改写框架其它配置
        $this->actingAs($this->admin, 'admin')
            ->putJson('/admin/cmspro/notepad/api/settings', ['items' => ['site_name' => 'hacked']])
            ->assertOk()
            ->assertJsonPath('code', 400)
            ->assertJsonPath('message', '不支持的配置项：site_name');

        $this->assertDatabaseMissing('config_items', ['code' => 'site_name', 'value' => 'hacked']);
    }

    public function test_带点号的旧式配置项被拒绝(): void
    {
        $this->seedConfig('per_page', '20');

        $this->actingAs($this->admin, 'admin')
            ->putJson('/admin/cmspro/notepad/api/settings', [
                'items' => ['app_cmspro.notepad_per_page' => 3],
            ])
            ->assertOk()
            ->assertJsonPath('code', 400);

        // 正确 code 的值未被污染
        $this->assertDatabaseHas('config_items', [
            'code' => 'app_cmspro_notepad_per_page',
            'value' => '20',
        ]);
    }

    public function test_每页条数为零被拒绝(): void
    {
        $this->seedConfig('per_page', '20');

        $this->actingAs($this->admin, 'admin')
            ->putJson('/admin/cmspro/notepad/api/settings', ['items' => ['per_page' => 0]])
            ->assertOk()
            ->assertJsonPath('code', 400)
            ->assertJsonPath('message', '每页显示条数必须在 1 ~ 100 之间');
    }

    public function test_非整数的配置值被拒绝(): void
    {
        $this->seedConfig('max_content_length', '5000');

        $this->actingAs($this->admin, 'admin')
            ->putJson('/admin/cmspro/notepad/api/settings', ['items' => ['max_content_length' => 'abc']])
            ->assertOk()
            ->assertJsonPath('code', 400)
            ->assertJsonPath('message', '便签内容最大字数必须为整数');
    }

    public function test_空配置项提交被拒绝(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->putJson('/admin/cmspro/notepad/api/settings', ['items' => []])
            ->assertOk()
            ->assertJsonPath('code', 400)
            ->assertJsonPath('message', '没有需要保存的配置项');
    }

    public function test_任一项非法时整批拒绝不产生部分写入(): void
    {
        $this->seedConfig('per_page', '20');
        $this->seedConfig('max_push_targets', '10');

        $this->actingAs($this->admin, 'admin')
            ->putJson('/admin/cmspro/notepad/api/settings', [
                'items' => ['per_page' => 5, 'max_push_targets' => 999],
            ])
            ->assertOk()
            ->assertJsonPath('code', 400);

        $this->assertDatabaseHas('config_items', [
            'code' => 'app_cmspro_notepad_per_page',
            'value' => '20',
        ]);
    }

    public function test_保存成功后清除业务层配置缓存(): void
    {
        $this->seedConfig('per_page', '20');

        // 先触发一次读取，让配置进入缓存
        $this->actingAs($this->admin, 'admin')->getJson('/admin/cmspro/notepad/api/notes')->assertOk();
        $this->assertTrue(Cache::has(NoteService::CONFIG_CACHE_KEY));

        $this->actingAs($this->admin, 'admin')
            ->putJson('/admin/cmspro/notepad/api/settings', ['items' => ['per_page' => 3]])
            ->assertOk()
            ->assertJsonPath('code', 0);

        // 缓存未清除会导致新值需等过期才生效
        $this->assertFalse(Cache::has(NoteService::CONFIG_CACHE_KEY));
        $this->assertSame('3', ConfigItem::where('code', 'app_cmspro_notepad_per_page')->value('value'));
    }

    /**
     * 内容字数上限与推送人数上限由后端下发到页面
     *
     * 回归点：表单页曾硬编码 5000、推送弹窗曾无上限提示，导致前端限制与后端
     * NoteService 校验脱节；且设置接口挂 manage 权限，仅有 create 权限的用户
     * 无法自行拉取配置，必须由控制器随页面下发。
     */
    public function test_内容字数上限与推送人数上限下发到页面(): void
    {
        $this->seedConfig('max_content_length', '800');
        $this->seedConfig('max_push_targets', '3');

        $this->actingAs($this->admin, 'admin')
            ->get('/admin/cmspro/notepad/notes/create')
            ->assertOk()
            ->assertSee('maxlength="800"', false)
            ->assertSee('0 / 800', false);

        $this->actingAs($this->admin, 'admin')
            ->get('/admin/cmspro/notepad/notes')
            ->assertOk()
            ->assertSee("parseInt('3', 10)", false);
    }

    /**
     * 设置页正常渲染
     *
     * 设置页此前无任何渲染用例，Blade 编译错误只能等到人工点击才暴露；
     * 同时校验基础路径常量已注入（URL 前缀收敛后视图不再硬编码）。
     */
    public function test_设置页正常渲染(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->get('/admin/cmspro/notepad/settings')
            ->assertOk()
            ->assertSee('setting-fields', false)
            ->assertSee("var NOTEPAD_BASE = '/admin/cmspro/notepad';", false);
    }

    /**
     * 批量创建归属于当前管理员的便签
     */
    private function createNotes(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->createNote($this->admin, ['title' => '便签' . $i]);
        }
    }
}

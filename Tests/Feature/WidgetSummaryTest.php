<?php

namespace App\Apps\CmsproNotepad\Tests\Feature;

use App\Apps\CmsproNotepad\Models\Push;
use App\Apps\CmsproNotepad\Tests\NotepadTestCase;
use Illuminate\Support\Facades\DB;

/**
 * 桌面小工具数据接口（GET /api/admin/cmspro/notepad/widget/summary）
 *
 * WebOS 桌面小工具按 manifest.widgets[].refresh 秒轮询该接口，统一返回契约：
 * {title,status,value,unit,items[{label,value}],updated_at}，status 取 running|warning。
 */
class WidgetSummaryTest extends NotepadTestCase
{
    private const ENDPOINT = '/api/admin/cmspro/notepad/widget/summary';

    public function test_requires_admin_login(): void
    {
        $this->getJson(self::ENDPOINT)->assertStatus(401);
    }

    public function test_returns_overview_contract_for_current_admin(): void
    {
        $admin = $this->createAdmin('notepad_widget');
        $other = $this->createAdmin('notepad_other');

        $this->createNote($admin);
        $this->createNote($admin);
        $this->createNote($admin, ['status' => 1]);
        // 他人便签不计入当前管理员口径
        $this->createNote($other);

        // 昨日便签只计入总数，不计入今日新增
        $yesterday = $this->createNote($admin);
        DB::table('app_cmspro_notepad_notes')
            ->where('id', $yesterday->id)
            ->update(['create_time' => now()->subDay()->format('Y-m-d H:i:s')]);

        Push::create([
            'note_id' => $this->createNote($other)->id,
            'from_admin_user_id' => $other->id,
            'to_admin_user_id' => $admin->id,
            'message' => '请查看',
        ]);

        $response = $this->actingAs($admin, 'admin')->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.title', '便签概览')
            // 存在未读推送时状态为 warning（需要关注）
            ->assertJsonPath('data.status', 'warning')
            ->assertJsonPath('data.value', 3)
            ->assertJsonPath('data.unit', '条待办')
            ->assertJsonPath('data.items.0.label', '便签总数')
            ->assertJsonPath('data.items.0.value', '4')
            ->assertJsonPath('data.items.1.label', '未读推送')
            ->assertJsonPath('data.items.1.value', '1')
            ->assertJsonPath('data.items.2.label', '今日新增')
            ->assertJsonPath('data.items.2.value', '3');

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            (string) $response->json('data.updated_at')
        );
    }

    public function test_status_running_when_no_unread_push(): void
    {
        $admin = $this->createAdmin('notepad_widget_quiet');
        $this->createNote($admin);

        $this->actingAs($admin, 'admin')->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonPath('data.status', 'running')
            ->assertJsonPath('data.value', 1);
    }

    public function test_denies_admin_without_manage_permission(): void
    {
        $this->registerPermissions();

        $admin = $this->createAdmin('notepad_widget_denied');
        $admin->roles()->attach($this->createRole('notepad_widget_viewer')->id);

        $this->actingAs($admin, 'admin')->getJson(self::ENDPOINT)
            ->assertStatus(403)
            ->assertJsonPath('code', 40301);
    }
}
<?php

namespace App\Apps\CmsproNotepad\Tests\Feature;

use App\Apps\CmsproNotepad\Tests\NotepadTestCase;

/**
 * 桌面便签（WebOS 小工具实例）四个接口
 *
 * picker   GET  /api/admin/cmspro/notepad/widget/notes         候选便签 {items:[{value,label,hint}]}
 * data     GET  /api/admin/cmspro/notepad/widget/note          单条便签数据，note_id 由实例参数以查询串传入
 * color    POST /api/admin/cmspro/notepad/widget/note-color    调整标记颜色
 * content  POST /api/admin/cmspro/notepad/widget/note-content  编辑正文
 *
 * 数据契约在概览基础上扩展 title / text / color（tone 枚举）/ status（running|stopped）；
 * 所有接口只作用于当前登录管理员自己的便签，他人便签读取返回 404、修改返回 403。
 */
class WidgetNoteTest extends NotepadTestCase
{
    private const PICKER_ENDPOINT = '/api/admin/cmspro/notepad/widget/notes';

    private const DATA_ENDPOINT = '/api/admin/cmspro/notepad/widget/note';

    private const COLOR_ENDPOINT = '/api/admin/cmspro/notepad/widget/note-color';

    private const CONTENT_ENDPOINT = '/api/admin/cmspro/notepad/widget/note-content';

    public function test_all_endpoints_require_admin_login(): void
    {
        $this->getJson(self::PICKER_ENDPOINT)->assertStatus(401);
        $this->getJson(self::DATA_ENDPOINT . '?note_id=1')->assertStatus(401);
        $this->postJson(self::COLOR_ENDPOINT, ['note_id' => 1, 'color' => ''])->assertStatus(401);
        $this->postJson(self::CONTENT_ENDPOINT, ['note_id' => 1, 'content' => '内容'])->assertStatus(401);
    }

    public function test_picker_lists_own_notes_only(): void
    {
        $admin = $this->createAdmin('notepad_pick');
        $other = $this->createAdmin('notepad_pick_other');

        $pinned = $this->createNote($admin, ['title' => '置顶便签', 'pinned' => 1]);
        $normal = $this->createNote($admin, ['title' => '普通便签']);
        // 标题为空的便签：候选列表回落为「便签 #ID」
        $untitled = $this->createNote($admin, ['title' => '', 'status' => 1]);
        $this->createNote($other, ['title' => '他人便签']);

        $response = $this->actingAs($admin, 'admin')->getJson(self::PICKER_ENDPOINT)
            ->assertOk()
            ->assertJsonPath('code', 0)
            // 置顶便签排在最前
            ->assertJsonPath('data.items.0.value', (string) $pinned->id)
            ->assertJsonPath('data.items.0.label', '置顶便签');

        $items = collect($response->json('data.items'))->keyBy('value');
        // 仅本人三条便签，他人便签不计入
        $this->assertCount(3, $items);
        $this->assertArrayHasKey((string) $normal->id, $items->all());
        $this->assertSame('便签 #' . $untitled->id, $items[(string) $untitled->id]['label']);
        $this->assertStringStartsWith('已完成', $items[(string) $untitled->id]['hint']);
        $this->assertStringStartsWith('待办', $items[(string) $normal->id]['hint']);
    }

    public function test_picker_limits_to_fifty_notes(): void
    {
        $admin = $this->createAdmin('notepad_pick_limit');
        for ($i = 0; $i < 51; $i++) {
            $this->createNote($admin);
        }

        $response = $this->actingAs($admin, 'admin')->getJson(self::PICKER_ENDPOINT)->assertOk();

        $this->assertCount(50, $response->json('data.items'));
    }

    public function test_returns_note_data_contract(): void
    {
        $admin = $this->createAdmin('notepad_data');
        $note = $this->createNote($admin, [
            'title' => '买牛奶',
            'content' => "买牛奶\n顺便买面包",
            'color' => '#4caf50',
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->getJson(self::DATA_ENDPOINT . '?note_id=' . $note->id)
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.title', '买牛奶')
            // 正文保留换行（卡片按尺寸档位截断行数，不做服务端压缩）
            ->assertJsonPath('data.text', "买牛奶\n顺便买面包")
            // 六色中的绿色映射为 green
            ->assertJsonPath('data.color', 'green')
            ->assertJsonPath('data.status', 'running');

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            (string) $response->json('data.updated_at')
        );
    }

    public function test_data_uses_default_tone_and_stopped_status(): void
    {
        $admin = $this->createAdmin('notepad_data_default');
        $note = $this->createNote($admin, [
            'title' => '',
            'color' => '',
            'status' => 1,
        ]);

        $this->actingAs($admin, 'admin')
            ->getJson(self::DATA_ENDPOINT . '?note_id=' . $note->id)
            ->assertOk()
            // 空标题回落为「便签 #ID」，空颜色回落 default，已完成视为 stopped
            ->assertJsonPath('data.title', '便签 #' . $note->id)
            ->assertJsonPath('data.color', 'default')
            ->assertJsonPath('data.status', 'stopped');
    }

    public function test_data_text_is_truncated_to_two_thousand_characters(): void
    {
        $admin = $this->createAdmin('notepad_data_long');
        $note = $this->createNote($admin, ['content' => str_repeat('长', 2100)]);

        $response = $this->actingAs($admin, 'admin')
            ->getJson(self::DATA_ENDPOINT . '?note_id=' . $note->id)
            ->assertOk();

        $this->assertSame(2000, mb_strlen($response->json('data.text')));
    }

    public function test_data_returns_404_for_other_admin_note(): void
    {
        $admin = $this->createAdmin('notepad_data_owner');
        $other = $this->createAdmin('notepad_data_stranger');
        $note = $this->createNote($other);

        $this->actingAs($admin, 'admin')
            ->getJson(self::DATA_ENDPOINT . '?note_id=' . $note->id)
            ->assertStatus(404)
            ->assertJsonPath('code', 404);
    }

    public function test_data_requires_note_id(): void
    {
        $admin = $this->createAdmin('notepad_data_no_id');

        $this->actingAs($admin, 'admin')
            ->getJson(self::DATA_ENDPOINT)
            ->assertUnprocessable();
    }

    public function test_updates_mark_color(): void
    {
        $admin = $this->createAdmin('notepad_color');
        $note = $this->createNote($admin, ['color' => '#ff5722']);

        $this->actingAs($admin, 'admin')
            ->postJson(self::COLOR_ENDPOINT, ['note_id' => $note->id, 'color' => '#2196f3'])
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.color', 'blue');

        $this->assertSame('#2196f3', $note->fresh()->color);
    }

    public function test_resets_mark_color_to_default(): void
    {
        $admin = $this->createAdmin('notepad_color_reset');
        $note = $this->createNote($admin, ['color' => '#4caf50']);

        $this->actingAs($admin, 'admin')
            ->postJson(self::COLOR_ENDPOINT, ['note_id' => $note->id, 'color' => ''])
            ->assertOk()
            ->assertJsonPath('data.color', 'default');

        $this->assertSame('', $note->fresh()->color);
    }

    public function test_rejects_color_outside_whitelist(): void
    {
        $admin = $this->createAdmin('notepad_color_invalid');
        $note = $this->createNote($admin, ['color' => '#4caf50']);

        $this->actingAs($admin, 'admin')
            ->postJson(self::COLOR_ENDPOINT, ['note_id' => $note->id, 'color' => '#123456'])
            ->assertUnprocessable();

        $this->assertSame('#4caf50', $note->fresh()->color);
    }

    public function test_color_denied_for_other_admin_note(): void
    {
        $admin = $this->createAdmin('notepad_color_stranger');
        $other = $this->createAdmin('notepad_color_owner');
        $note = $this->createNote($other, ['color' => '#4caf50']);

        $this->actingAs($admin, 'admin')
            ->postJson(self::COLOR_ENDPOINT, ['note_id' => $note->id, 'color' => '#ff5722'])
            ->assertStatus(403)
            ->assertJsonPath('code', 403);

        $this->assertSame('#4caf50', $note->fresh()->color);
    }

    public function test_color_returns_404_for_missing_note(): void
    {
        $admin = $this->createAdmin('notepad_color_missing');

        $this->actingAs($admin, 'admin')
            ->postJson(self::COLOR_ENDPOINT, ['note_id' => 999999, 'color' => '#ff5722'])
            ->assertStatus(404)
            ->assertJsonPath('code', 404);
    }

    public function test_updates_content(): void
    {
        $admin = $this->createAdmin('notepad_content');
        $note = $this->createNote($admin, ['content' => '旧内容']);

        $this->actingAs($admin, 'admin')
            ->postJson(self::CONTENT_ENDPOINT, ['note_id' => $note->id, 'content' => '新内容'])
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.text', '新内容');

        $this->assertSame('新内容', $note->fresh()->content);
    }

    public function test_rejects_blank_content(): void
    {
        $admin = $this->createAdmin('notepad_content_blank');
        $note = $this->createNote($admin, ['content' => '旧内容']);

        $this->actingAs($admin, 'admin')
            ->postJson(self::CONTENT_ENDPOINT, ['note_id' => $note->id, 'content' => '   '])
            ->assertUnprocessable();

        $this->assertSame('旧内容', $note->fresh()->content);
    }

    public function test_rejects_content_over_500_characters(): void
    {
        $admin = $this->createAdmin('notepad_content_long');
        $note = $this->createNote($admin, ['content' => '旧内容']);

        $this->actingAs($admin, 'admin')
            ->postJson(self::CONTENT_ENDPOINT, ['note_id' => $note->id, 'content' => str_repeat('a', 501)])
            ->assertUnprocessable();

        $this->assertSame('旧内容', $note->fresh()->content);
    }

    public function test_respects_configured_content_limit(): void
    {
        $admin = $this->createAdmin('notepad_content_config');
        $note = $this->createNote($admin, ['content' => '旧内容']);
        // 应用配置把内容上限压到 10 字：接口层 500 字校验通过，业务层再拦一次
        $this->seedConfig('max_content_length', '10');

        $this->actingAs($admin, 'admin')
            ->postJson(self::CONTENT_ENDPOINT, ['note_id' => $note->id, 'content' => str_repeat('a', 20)])
            ->assertStatus(400)
            ->assertJsonPath('code', 400);

        $this->assertSame('旧内容', $note->fresh()->content);
    }

    public function test_content_denied_for_other_admin_note(): void
    {
        $admin = $this->createAdmin('notepad_content_stranger');
        $other = $this->createAdmin('notepad_content_owner');
        $note = $this->createNote($other, ['content' => '他人内容']);

        $this->actingAs($admin, 'admin')
            ->postJson(self::CONTENT_ENDPOINT, ['note_id' => $note->id, 'content' => '篡改内容'])
            ->assertStatus(403)
            ->assertJsonPath('code', 403);

        $this->assertSame('他人内容', $note->fresh()->content);
    }

    public function test_denies_admin_without_manage_permission(): void
    {
        $this->registerPermissions();

        $admin = $this->createAdmin('notepad_widget_note_viewer');
        $admin->roles()->attach($this->createRole('notepad_widget_note_viewer_role')->id);

        $this->actingAs($admin, 'admin')
            ->getJson(self::PICKER_ENDPOINT)
            ->assertStatus(403)
            ->assertJsonPath('code', 40301);

        $this->actingAs($admin, 'admin')
            ->getJson(self::DATA_ENDPOINT . '?note_id=1')
            ->assertStatus(403)
            ->assertJsonPath('code', 40301);
    }

    public function test_denies_admin_with_manage_but_without_create_permission(): void
    {
        $this->registerPermissions();

        $admin = $this->createAdmin('notepad_widget_note_reader');
        $role = $this->createRole('notepad_widget_note_reader_role');
        $this->grantRole($role, ['manage']);
        $admin->roles()->attach($role->id);

        $note = $this->createNote($admin, ['color' => '#4caf50']);

        // 有 manage 可读卡片数据
        $this->actingAs($admin, 'admin')
            ->getJson(self::DATA_ENDPOINT . '?note_id=' . $note->id)
            ->assertOk();

        // 无 create 不可操作卡片
        $this->actingAs($admin, 'admin')
            ->postJson(self::COLOR_ENDPOINT, ['note_id' => $note->id, 'color' => '#ff5722'])
            ->assertStatus(403)
            ->assertJsonPath('code', 40301);

        $this->assertSame('#4caf50', $note->fresh()->color);
    }
}
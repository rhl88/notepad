<?php

namespace App\Apps\CmsproNotepad\Tests\Feature;

use App\Apps\CmsproNotepad\Tests\NotepadTestCase;
use App\Models\AdminUser;

/**
 * 记事本便签更新接口回归测试
 * 覆盖：标记颜色未选择（null）时的空值规范化、部分字段更新、越权拦截
 */
class NoteUpdateTest extends NotepadTestCase
{
    private AdminUser $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createAdmin();
    }

    public function test_update_note_with_null_color_succeeds(): void
    {
        // 标记颜色未选择时，前端提交 color 为 null，应规范化为空字符串而非写入 null
        $note = $this->createNote($this->admin);

        $this->actingAs($this->admin, 'admin')
            ->putJson('/admin/cmspro/notepad/api/notes/' . $note->id, [
                'title' => '更新标题',
                'color' => null,
            ])
            ->assertOk()
            ->assertJson(['code' => 0]);

        $this->assertDatabaseHas('app_cmspro_notepad_notes', [
            'id' => $note->id,
            'title' => '更新标题',
            'color' => '',
        ]);
    }

    public function test_update_note_without_color_field_succeeds(): void
    {
        // 未提交 color 字段时，不覆盖原颜色值
        $note = $this->createNote($this->admin, ['color' => '#4caf50']);

        $this->actingAs($this->admin, 'admin')
            ->putJson('/admin/cmspro/notepad/api/notes/' . $note->id, [
                'title' => '仅更新标题',
            ])
            ->assertOk()
            ->assertJson(['code' => 0]);

        $this->assertDatabaseHas('app_cmspro_notepad_notes', [
            'id' => $note->id,
            'color' => '#4caf50',
        ]);
    }

    public function test_update_note_forbidden_for_non_owner(): void
    {
        // 非本人便签不可修改
        $other = $this->createAdmin('notepad_other');
        $note = $this->createNote($this->admin);

        $this->actingAs($other, 'admin')
            ->putJson('/admin/cmspro/notepad/api/notes/' . $note->id, [
                'title' => '越权修改',
            ])
            ->assertJson(['code' => 403]);
    }

    public function test_更新不存在的便签返回404(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->putJson('/admin/cmspro/notepad/api/notes/999999', ['title' => '不存在的便签'])
            ->assertJson(['code' => 404]);
    }

    public function test_标题为空时更新被拒绝(): void
    {
        $note = $this->createNote($this->admin);

        $this->actingAs($this->admin, 'admin')
            ->putJson('/admin/cmspro/notepad/api/notes/' . $note->id, ['title' => ''])
            ->assertStatus(422);

        $this->assertDatabaseHas('app_cmspro_notepad_notes', [
            'id' => $note->id,
            'title' => '测试便签',
        ]);
    }

    public function test_非白名单颜色被拒绝(): void
    {
        $note = $this->createNote($this->admin);

        // 颜色只允许前端选择器提供的固定色值，防止任意字符串写入样式属性
        $this->actingAs($this->admin, 'admin')
            ->putJson('/admin/cmspro/notepad/api/notes/' . $note->id, [
                'title' => '合法标题',
                'color' => 'red;background:url(javascript:alert(1))',
            ])
            ->assertStatus(422);
    }

    public function test_优先级越界被拒绝(): void
    {
        $note = $this->createNote($this->admin);

        $this->actingAs($this->admin, 'admin')
            ->putJson('/admin/cmspro/notepad/api/notes/' . $note->id, [
                'title' => '合法标题',
                'priority' => 99,
            ])
            ->assertStatus(422);
    }

    public function test_切换状态在未完成与已完成之间往返(): void
    {
        $note = $this->createNote($this->admin, ['status' => 0]);

        $this->actingAs($this->admin, 'admin')
            ->putJson('/admin/cmspro/notepad/api/notes/' . $note->id . '/toggle-status')
            ->assertOk()
            ->assertJson(['code' => 0]);

        $this->assertDatabaseHas('app_cmspro_notepad_notes', ['id' => $note->id, 'status' => 1]);

        $this->actingAs($this->admin, 'admin')
            ->putJson('/admin/cmspro/notepad/api/notes/' . $note->id . '/toggle-status')
            ->assertOk()
            ->assertJson(['code' => 0]);

        $this->assertDatabaseHas('app_cmspro_notepad_notes', ['id' => $note->id, 'status' => 0]);
    }

    public function test_非本人便签不可切换状态(): void
    {
        $other = $this->createAdmin('notepad_other');
        $note = $this->createNote($this->admin);

        $this->actingAs($other, 'admin')
            ->putJson('/admin/cmspro/notepad/api/notes/' . $note->id . '/toggle-status')
            ->assertJson(['code' => 403]);

        $this->assertDatabaseHas('app_cmspro_notepad_notes', ['id' => $note->id, 'status' => 0]);
    }
}

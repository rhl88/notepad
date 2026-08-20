<?php

namespace App\Apps\CmsproNotepad\Tests\Feature;

use App\Apps\CmsproNotepad\Models\Note;
use App\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * 记事本便签更新接口回归测试
 * 覆盖：标记颜色未选择（null）时的空值规范化、部分字段更新、越权拦截
 */
class NoteUpdateTest extends TestCase
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
            'username' => 'notepad_test_' . uniqid(),
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

    private function createNote(array $overrides = []): Note
    {
        return Note::create(array_merge([
            'admin_user_id' => $this->admin->id,
            'title' => '测试便签',
            'content' => '内容',
            'status' => 0,
            'priority' => 0,
            'color' => '#ff5722',
            'pinned' => 0,
        ], $overrides));
    }

    public function test_update_note_with_null_color_succeeds(): void
    {
        // 标记颜色未选择时，前端提交 color 为 null，应规范化为空字符串而非写入 null
        $note = $this->createNote();

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
        $note = $this->createNote(['color' => '#4caf50']);

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
        $other = AdminUser::create([
            'username' => 'notepad_other_' . uniqid(),
            'password' => bcrypt('123456'),
            'status' => 1,
        ]);
        $note = $this->createNote();

        $this->actingAs($other, 'admin')
            ->putJson('/admin/cmspro/notepad/api/notes/' . $note->id, [
                'title' => '越权修改',
            ])
            ->assertJson(['code' => 403]);
    }
}

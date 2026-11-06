<?php

namespace App\Apps\CmsproNotepad\Tests\Feature;

use App\Apps\CmsproNotepad\Models\Note;
use App\Apps\CmsproNotepad\Tests\NotepadTestCase;

/**
 * 桌面小工具操作接口（POST /api/admin/cmspro/notepad/widget/quick-note）
 *
 * WebOS 便签概览卡片内的「新增便签」按钮提交一条内容，标题自动取首行（超长截断），
 * 便签归属当前登录管理员。
 */
class QuickNoteTest extends NotepadTestCase
{
    private const ENDPOINT = '/api/admin/cmspro/notepad/widget/quick-note';

    public function test_requires_admin_login(): void
    {
        $this->postJson(self::ENDPOINT, ['content' => '随手记一条'])
            ->assertStatus(401);
    }

    public function test_creates_note_for_current_admin(): void
    {
        $admin = $this->createAdmin('notepad_quick');

        $this->actingAs($admin, 'admin')
            ->postJson(self::ENDPOINT, ['content' => "  第一条快捷便签  \n第二行内容"])
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.title', '第一条快捷便签');

        $note = Note::where('admin_user_id', $admin->id)->firstOrFail();
        // 内容整体保留（仅首尾空白被清理），标题取首行
        $this->assertSame("第一条快捷便签  \n第二行内容", $note->content);
        $this->assertSame('第一条快捷便签', $note->title);
        $this->assertSame(0, (int) $note->status);
    }

    public function test_title_is_truncated_to_thirty_characters(): void
    {
        $admin = $this->createAdmin('notepad_quick_long');

        // Str::limit 按显示宽度截断：用半角字符验证时宽度 30 即 30 个字符
        $content = str_repeat('a', 40);

        $this->actingAs($admin, 'admin')
            ->postJson(self::ENDPOINT, ['content' => $content])
            ->assertOk()
            ->assertJsonPath('data.title', str_repeat('a', 30));

        $this->assertSame(30, mb_strlen(Note::where('admin_user_id', $admin->id)->value('title')));
    }

    public function test_rejects_empty_or_missing_content(): void
    {
        $admin = $this->createAdmin('notepad_quick_empty');

        $this->actingAs($admin, 'admin')
            ->postJson(self::ENDPOINT, [])
            ->assertUnprocessable();

        $this->actingAs($admin, 'admin')
            ->postJson(self::ENDPOINT, ['content' => '   '])
            ->assertUnprocessable();

        $this->assertSame(0, Note::where('admin_user_id', $admin->id)->count());
    }

    public function test_rejects_content_over_500_characters(): void
    {
        $admin = $this->createAdmin('notepad_quick_too_long');

        $this->actingAs($admin, 'admin')
            ->postJson(self::ENDPOINT, ['content' => str_repeat('a', 501)])
            ->assertUnprocessable();

        $this->assertSame(0, Note::where('admin_user_id', $admin->id)->count());
    }

    public function test_denies_admin_without_create_permission(): void
    {
        $this->registerPermissions();

        $admin = $this->createAdmin('notepad_quick_denied');
        $admin->roles()->attach($this->createRole('notepad_quick_viewer')->id);

        $this->actingAs($admin, 'admin')
            ->postJson(self::ENDPOINT, ['content' => '无权限'])
            ->assertStatus(403)
            ->assertJsonPath('code', 40301);

        $this->assertSame(0, Note::where('admin_user_id', $admin->id)->count());
    }
}
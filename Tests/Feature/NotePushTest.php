<?php

namespace App\Apps\CmsproNotepad\Tests\Feature;

use App\Apps\CmsproNotepad\Models\Push;
use App\Apps\CmsproNotepad\Tests\NotepadTestCase;
use App\Models\AdminUser;
use Illuminate\Support\Facades\Schema;

/**
 * 便签推送测试
 *
 * 覆盖推送的归属校验、人数上限、目标去重与重复推送幂等，
 * 以及支撑幂等的数据库唯一索引是否随迁移正确建立。
 *
 * 本文件不调用 registerPermissions()：权限码未注册时中间件按兜底语义放行，
 * 以便聚焦推送业务逻辑；权限校验由 PermissionTest 单独覆盖。
 */
class NotePushTest extends NotepadTestCase
{
    private AdminUser $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->createAdmin('notepad_pusher');
    }

    public function test_推送便签生成记录并返回人数(): void
    {
        $note = $this->createNote($this->owner);
        $targets = [$this->createAdmin('target_a'), $this->createAdmin('target_b')];

        $response = $this->actingAs($this->owner, 'admin')
            ->postJson('/admin/cmspro/notepad/api/notes/' . $note->id . '/push', [
                'target_user_ids' => array_map(fn ($t) => $t->id, $targets),
                'message' => '请查收',
            ])
            ->assertOk();

        $response->assertJsonPath('code', 0)
            ->assertJsonPath('message', '已推送给2位管理员');

        $this->assertDatabaseCount('app_cmspro_notepad_pushes', 2);
        $this->assertDatabaseHas('app_cmspro_notepad_pushes', [
            'note_id' => $note->id,
            'from_admin_user_id' => $this->owner->id,
            'to_admin_user_id' => $targets[0]->id,
            'message' => '请查收',
            'is_read' => 0,
        ]);
    }

    public function test_重复推送同一人只保留一条记录(): void
    {
        $note = $this->createNote($this->owner);
        $target = $this->createAdmin('target_repeat');

        $this->actingAs($this->owner, 'admin')
            ->postJson('/admin/cmspro/notepad/api/notes/' . $note->id . '/push', [
                'target_user_ids' => [$target->id],
            ])
            ->assertOk()
            ->assertJsonPath('code', 0);

        // 第二次推送同一人：已存在的记录跳过，不报错也不重复计数
        $this->actingAs($this->owner, 'admin')
            ->postJson('/admin/cmspro/notepad/api/notes/' . $note->id . '/push', [
                'target_user_ids' => [$target->id],
            ])
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('message', '已推送给0位管理员');

        $this->assertDatabaseCount('app_cmspro_notepad_pushes', 1);
    }

    public function test_目标列表含重复ID时按去重后计数(): void
    {
        $note = $this->createNote($this->owner);
        $target = $this->createAdmin('target_dup');

        // 字符串 ID 同样需要被正确去重，验证 intval 归一化生效
        $this->actingAs($this->owner, 'admin')
            ->postJson('/admin/cmspro/notepad/api/notes/' . $note->id . '/push', [
                'target_user_ids' => [(string) $target->id, $target->id, (string) $target->id],
            ])
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('message', '已推送给1位管理员');

        $this->assertDatabaseCount('app_cmspro_notepad_pushes', 1);
    }

    public function test_已存在的推送记录在事务内被跳过而不中断其余目标(): void
    {
        $note = $this->createNote($this->owner);
        $existing = $this->createAdmin('target_existing');
        $fresh = $this->createAdmin('target_fresh');

        // 模拟并发场景下另一请求已先行写入的记录
        Push::create([
            'note_id' => $note->id,
            'from_admin_user_id' => $this->owner->id,
            'to_admin_user_id' => $existing->id,
            'message' => '',
        ]);

        $this->actingAs($this->owner, 'admin')
            ->postJson('/admin/cmspro/notepad/api/notes/' . $note->id . '/push', [
                'target_user_ids' => [$existing->id, $fresh->id],
            ])
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('message', '已推送给1位管理员');

        $this->assertDatabaseCount('app_cmspro_notepad_pushes', 2);
    }

    public function test_不能推送给自己(): void
    {
        $note = $this->createNote($this->owner);

        $this->actingAs($this->owner, 'admin')
            ->postJson('/admin/cmspro/notepad/api/notes/' . $note->id . '/push', [
                'target_user_ids' => [$this->owner->id],
            ])
            ->assertOk()
            ->assertJsonPath('code', 400)
            ->assertJsonPath('message', '不能推送给自己');

        $this->assertDatabaseCount('app_cmspro_notepad_pushes', 0);
    }

    public function test_超出单次推送人数上限被拒绝(): void
    {
        $this->seedConfig('max_push_targets', '2');

        $note = $this->createNote($this->owner);
        $targets = [
            $this->createAdmin('limit_a')->id,
            $this->createAdmin('limit_b')->id,
            $this->createAdmin('limit_c')->id,
        ];

        $this->actingAs($this->owner, 'admin')
            ->postJson('/admin/cmspro/notepad/api/notes/' . $note->id . '/push', [
                'target_user_ids' => $targets,
            ])
            ->assertOk()
            ->assertJsonPath('code', 400)
            ->assertJsonPath('message', '推送人数不能超过2人');

        // 整体拒绝，不应产生部分推送记录
        $this->assertDatabaseCount('app_cmspro_notepad_pushes', 0);
    }

    public function test_不能推送他人的便签(): void
    {
        $other = $this->createAdmin('other_owner');
        $note = $this->createNote($other);

        $this->actingAs($this->owner, 'admin')
            ->postJson('/admin/cmspro/notepad/api/notes/' . $note->id . '/push', [
                'target_user_ids' => [$this->createAdmin('bystander')->id],
            ])
            ->assertOk()
            ->assertJsonPath('code', 403)
            ->assertJsonPath('message', '只能推送自己的便签');

        $this->assertDatabaseCount('app_cmspro_notepad_pushes', 0);
    }

    public function test_推送不存在的便签返回404(): void
    {
        $this->actingAs($this->owner, 'admin')
            ->postJson('/admin/cmspro/notepad/api/notes/999999/push', [
                'target_user_ids' => [$this->createAdmin('nobody')->id],
            ])
            ->assertOk()
            ->assertJsonPath('code', 404)
            ->assertJsonPath('message', '便签不存在');
    }

    public function test_推送目标为空数组被校验拒绝(): void
    {
        $note = $this->createNote($this->owner);

        $this->actingAs($this->owner, 'admin')
            ->postJson('/admin/cmspro/notepad/api/notes/' . $note->id . '/push', [
                'target_user_ids' => [],
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('app_cmspro_notepad_pushes', 0);
    }

    public function test_推送留言超长被校验拒绝(): void
    {
        $note = $this->createNote($this->owner);

        $this->actingAs($this->owner, 'admin')
            ->postJson('/admin/cmspro/notepad/api/notes/' . $note->id . '/push', [
                'target_user_ids' => [$this->createAdmin('long_msg')->id],
                'message' => str_repeat('推', 501),
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('app_cmspro_notepad_pushes', 0);
    }

    public function test_推送表已建立防重复的唯一索引与未读查询复合索引(): void
    {
        $this->assertTrue(
            Schema::hasIndex('app_cmspro_notepad_pushes', ['note_id', 'to_admin_user_id'], 'unique'),
            '缺少 (note_id, to_admin_user_id) 唯一索引，并发推送可能产生重复记录'
        );

        $this->assertTrue(
            Schema::hasIndex('app_cmspro_notepad_pushes', ['to_admin_user_id', 'is_read']),
            '缺少 (to_admin_user_id, is_read) 复合索引，未读数查询会退化为全表扫描'
        );
    }
}

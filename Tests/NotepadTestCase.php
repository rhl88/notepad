<?php

namespace App\Apps\CmsproNotepad\Tests;

use App\Apps\CmsproNotepad\Models\Note;
use App\Apps\CmsproNotepad\ServiceProvider;
use App\Apps\CmsproNotepad\Services\NoteService;
use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\ConfigGroup;
use App\Models\ConfigItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * 记事本应用测试基类
 *
 * 统一完成三件各测试文件原本重复的事：
 * 1. 注册应用 ServiceProvider（内存库 apps 表为空，应用路由不会自动注册）；
 * 2. 按顺序执行应用全部迁移，建齐便签 / 评论 / 推送三张表；
 * 3. 提供超管、普通管理员、权限注册与角色授权等构造方法。
 *
 * 每个用例开始前清空缓存：应用配置与权限注册状态均有缓存，
 * 不清空会导致上一个用例的缓存结果串扰当前用例。
 */
abstract class NotepadTestCase extends TestCase
{
    use RefreshDatabase;

    /** 应用标识，与 manifest.json 的 app_id 一致 */
    protected const APP_ID = 'cmspro.notepad';

    /** 应用声明的权限码 => 中文名称，与 manifest.json 的 permissions 一致 */
    protected const PERMISSIONS = [
        'cmspro.notepad.manage' => '便签管理',
        'cmspro.notepad.create' => '创建便签',
        'cmspro.notepad.edit' => '编辑便签',
        'cmspro.notepad.delete' => '删除便签',
        'cmspro.notepad.push' => '推送便签',
    ];

    /** 应用迁移文件，按执行顺序排列 */
    private const MIGRATIONS = [
        '2026_07_25_000001_create_app_cmspro_notepad_notes_table.php',
        '2026_07_25_000002_create_app_cmspro_notepad_comments_table.php',
        '2026_07_25_000003_create_app_cmspro_notepad_pushes_table.php',
        '2026_10_29_000004_add_indexes_to_app_cmspro_notepad_pushes_table.php',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->register(ServiceProvider::class);
        $this->createAppTables();
        Cache::flush();
    }

    /**
     * 执行应用迁移建表（迁移自身带 hasTable 保护，重复调用安全）
     */
    protected function createAppTables(): void
    {
        foreach (self::MIGRATIONS as $file) {
            $path = base_path('app/Apps/CmsproNotepad/Migrations/' . $file);
            (require $path)->up();
        }
    }

    /**
     * 创建启用状态的管理员
     */
    protected function createAdmin(string $prefix = 'notepad'): AdminUser
    {
        return AdminUser::create([
            'username' => $prefix . '_' . uniqid(),
            'password' => bcrypt('123456'),
            'name' => $prefix,
            'status' => 1,
        ]);
    }

    /**
     * 创建超级管理员（框架仪表盘仅对超管渲染官网动态等区块）
     */
    protected function createSuperAdmin(): AdminUser
    {
        $admin = $this->createAdmin('notepad_super');
        $admin->roles()->attach($this->createRole('super_admin', '超级管理员')->id);

        return $admin;
    }

    /**
     * 创建角色
     */
    protected function createRole(string $code, string $name = '测试角色'): AdminRole
    {
        return AdminRole::firstOrCreate(
            ['code' => $code],
            ['name' => $name, 'status' => 1]
        );
    }

    /**
     * 将应用声明的权限码写入框架权限表
     *
     * 权限码不存在时中间件会直接放行，因此权限相关用例必须先调用本方法，
     * 让校验真正生效。
     */
    protected function registerPermissions(): void
    {
        foreach (self::PERMISSIONS as $code => $name) {
            AdminPermission::firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'module' => self::APP_ID, 'app_id' => self::APP_ID, 'status' => 1]
            );
        }

        Cache::flush();
    }

    /**
     * 给角色授予指定的记事本权限（传后缀，如 manage / create）
     *
     * @param array<int, string> $suffixes
     */
    protected function grantRole(AdminRole $role, array $suffixes): void
    {
        $codes = array_map(fn ($s) => 'cmspro.notepad.' . $s, $suffixes);
        $ids = AdminPermission::whereIn('code', $codes)->pluck('id')->all();
        $role->permissions()->syncWithoutDetaching($ids);

        Cache::flush();
    }

    /**
     * 创建归属于指定管理员的便签
     */
    protected function createNote(AdminUser $owner, array $overrides = []): Note
    {
        return Note::create(array_merge([
            'admin_user_id' => $owner->id,
            'title' => '测试便签',
            'content' => '内容',
            'status' => 0,
            'priority' => 0,
            'color' => '#ff5722',
            'pinned' => 0,
        ], $overrides));
    }

    /**
     * 写入应用配置项
     *
     * code 前缀必须与框架 registerConfigGroups 一致（app_id 中的点号转下划线），
     * 业务层 NoteService::getConfig 正是按该前缀读取配置。
     */
    protected function seedConfig(string $key, string $value): void
    {
        $group = ConfigGroup::firstOrCreate(
            ['code' => 'app_cmspro_notepad_notepad_setting'],
            ['name' => '记事本设置', 'app_id' => self::APP_ID, 'sort' => 0, 'status' => 1]
        );

        ConfigItem::updateOrCreate(
            ['group_id' => $group->id, 'code' => 'app_cmspro_notepad_' . $key],
            ['name' => $key, 'type' => 'text', 'value' => $value, 'sort' => 0, 'status' => 1]
        );

        // 业务层配置为整体缓存，写入后需失效，否则读到旧值
        Cache::forget(NoteService::CONFIG_CACHE_KEY);
    }

    /**
     * 断言三张应用表均已建立
     */
    protected function assertAppTablesExist(): void
    {
        $this->assertTrue(Schema::hasTable('app_cmspro_notepad_notes'));
        $this->assertTrue(Schema::hasTable('app_cmspro_notepad_comments'));
        $this->assertTrue(Schema::hasTable('app_cmspro_notepad_pushes'));
    }
}

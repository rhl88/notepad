<?php

namespace App\Apps\CmsproNotepad;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Install
{
    /** 应用标识 */
    private const APP_ID = 'cmspro.notepad';

    /**
     * 应用安装
     */
    public function install(): void
    {
        $this->runMigrations();
        $this->seedDefaultConfigs();
        $this->grantPermissionsToExistingRoles();
    }

    /**
     * 应用卸载
     */
    public function uninstall(): void
    {
        $this->rollbackMigrations();
        $this->removeConfigs();
    }

    /**
     * 应用升级
     */
    public function upgrade(string $fromVersion, string $toVersion): void
    {
        $this->runMigrations();
        $this->seedDefaultConfigs();
        $this->grantPermissionsToExistingRoles();
    }

    /**
     * 执行迁移文件（兜底机制）
     *
     * 迁移失败时记录日志并向上抛出，避免「安装/升级提示成功但数据表缺失」的静默故障；
     * 框架侧 executeLocalInstall 捕获后会回滚迁移并返回失败信息。
     */
    protected function runMigrations(): void
    {
        $migrationsPath = __DIR__ . '/Migrations';
        $migrationFiles = glob($migrationsPath . '/*.php');

        foreach ($migrationFiles as $file) {
            $migrationName = pathinfo($file, PATHINFO_FILENAME);

            if (DB::table('migrations')->where('migration', $migrationName)->exists()) {
                continue;
            }

            $migration = require $file;
            if (method_exists($migration, 'up')) {
                try {
                    $migration->up();

                    DB::table('migrations')->insert([
                        'migration' => $migrationName,
                        'batch' => DB::table('migrations')->max('batch') + 1,
                    ]);
                } catch (\Throwable $e) {
                    Log::error('记事本应用迁移执行失败', [
                        'app' => self::APP_ID,
                        'file' => basename($file),
                        'error' => $e->getMessage(),
                    ]);

                    throw new \RuntimeException(
                        '记事本应用迁移执行失败：' . basename($file) . '，原因：' . $e->getMessage(),
                        0,
                        $e
                    );
                }
            }
        }
    }

    /**
     * 回滚迁移文件（兜底机制）
     */
    protected function rollbackMigrations(): void
    {
        $migrationsPath = __DIR__ . '/Migrations';
        $migrationFiles = glob($migrationsPath . '/*.php');

        foreach (array_reverse($migrationFiles) as $file) {
            $migrationName = pathinfo($file, PATHINFO_FILENAME);

            if (!DB::table('migrations')->where('migration', $migrationName)->exists()) {
                continue;
            }

            $migration = require $file;
            if (method_exists($migration, 'down')) {
                try {
                    $migration->down();

                    DB::table('migrations')->where('migration', $migrationName)->delete();
                } catch (\Throwable $e) {
                    Log::error('记事本应用迁移回滚失败', [
                        'app' => self::APP_ID,
                        'file' => basename($file),
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * 从 manifest.json 同步配置到数据库
     * - 新增配置：写入默认值
     * - 已有配置：仅更新结构，保留用户设置的 value
     * - 已删除配置：清理 DB 残留
     */
    protected function seedDefaultConfigs(): void
    {
        if (!class_exists(\App\Models\ConfigGroup::class) || !class_exists(\App\Models\ConfigItem::class)) {
            return;
        }

        $manifest = $this->readManifest();
        if ($manifest === []) {
            return;
        }

        $configGroups = $manifest['config_groups'] ?? [];
        $appId = self::APP_ID;
        // 配置 code 前缀必须与框架 registerConfigGroups 保持一致（app_id 中的点号转下划线），
        // 否则会写入一批带点号的重复配置项，导致设置页出现重复条目且保存被白名单校验拒绝
        $prefix = app_id_to_table_prefix($appId);

        $manifestCodes = [];

        foreach ($configGroups as $groupDef) {
            $group = \App\Models\ConfigGroup::updateOrCreate(
                ['code' => $prefix . $groupDef['name']],
                [
                    'name' => $groupDef['title'],
                    'app_id' => $appId,
                    'sort' => 0,
                    'status' => 1,
                ]
            );

            foreach ($groupDef['items'] as $index => $item) {
                $fullCode = $prefix . $item['name'];
                $manifestCodes[] = $fullCode;

                $existing = \App\Models\ConfigItem::where('code', $fullCode)->first();

                if ($existing) {
                    // 已有记录 → 仅更新结构信息，不覆盖用户设置的 value
                    $existing->update([
                        'group_id' => $group->id,
                        'name'     => $item['title'],
                        'type'     => $item['type'] ?? 'text',
                        'tips'     => $item['tips'] ?? '',
                        'sort'     => $index,
                        'status'   => 1,
                    ]);
                } else {
                    // 新增配置 → 写入默认值
                    \App\Models\ConfigItem::create([
                        'code'     => $fullCode,
                        'group_id' => $group->id,
                        'name'     => $item['title'],
                        'type'     => $item['type'] ?? 'text',
                        'value'    => $item['value'] ?? '',
                        'tips'     => $item['tips'] ?? '',
                        'sort'     => $index,
                        'status'   => 1,
                    ]);
                }
            }
        }

        // 清理 manifest 中已删除的配置项；
        // LIKE 的下划线为单字符通配，历史版本误写入的带点号 code 同样会被匹配并清理
        \App\Models\ConfigItem::where('code', 'like', $prefix . '%')
            ->whereNotIn('code', $manifestCodes)
            ->delete();

        // 清理无子项的配置组（含历史带点号 code 的残留组）
        \App\Models\ConfigGroup::where('app_id', $appId)
            ->whereDoesntHave('items')
            ->delete();
    }

    /**
     * 卸载时全量清理配置
     */
    protected function removeConfigs(): void
    {
        $appId = self::APP_ID;

        \App\Models\ConfigItem::where('code', 'like', app_id_to_table_prefix($appId) . '%')->delete();
        \App\Models\ConfigGroup::where('app_id', $appId)->delete();
    }

    /**
     * 读取 manifest.json，读取失败返回空数组
     *
     * @return array<string, mixed>
     */
    protected function readManifest(): array
    {
        $manifestPath = __DIR__ . '/manifest.json';
        if (!file_exists($manifestPath)) {
            return [];
        }

        return json_decode((string) file_get_contents($manifestPath), true) ?: [];
    }

    /**
     * 为已存在的启用角色补授应用权限
     *
     * 框架在安装/升级流程中先执行本脚本、后写入 admin_permissions（见 AppInstallerService），
     * 因此这里需自行保证权限已落库，再把权限分配给存量角色；否则应用一旦启用权限校验，
     * 非超管管理员会在升级后立刻失去访问权限（超管不受影响）。
     * 升级后新建的角色仍需在「角色管理」中手动勾选。
     */
    protected function grantPermissionsToExistingRoles(): void
    {
        if (!class_exists(AdminPermission::class) || !class_exists(AdminRole::class)) {
            return;
        }

        $permissionIds = $this->ensurePermissionsRegistered();
        if ($permissionIds === []) {
            return;
        }

        AdminRole::enabled()->each(function (AdminRole $role) use ($permissionIds): void {
            $role->permissions()->syncWithoutDetaching($permissionIds);
        });
    }

    /**
     * 确保 manifest 声明的权限已写入框架权限表（幂等）
     *
     * 写法与框架 AppInstallerService::registerPermissions 保持一致，重复执行不会产生脏数据。
     *
     * @return array<int, int> 权限主键集合
     */
    protected function ensurePermissionsRegistered(): array
    {
        $permissions = $this->readManifest()['permissions'] ?? [];
        $permissionIds = [];

        foreach ($permissions as $permission) {
            $code = is_array($permission) ? ($permission['code'] ?? null) : $permission;
            if (!$code) {
                continue;
            }

            $name = is_array($permission) ? ($permission['name'] ?? $code) : $permission;

            $permissionIds[] = AdminPermission::firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'module' => self::APP_ID, 'app_id' => self::APP_ID]
            )->id;
        }

        return $permissionIds;
    }
}

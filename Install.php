<?php

namespace App\Apps\CmsproNotepad;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Install
{
    /**
     * 应用安装
     */
    public function install(): void
    {
        $this->runMigrations();
        $this->seedDefaultConfigs();
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
    }

    /**
     * 执行迁移文件（兜底机制）
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
                        'app' => 'cmspro.notepad',
                        'file' => basename($file),
                        'error' => $e->getMessage(),
                    ]);
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
                        'app' => 'cmspro.notepad',
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

        $manifestPath = __DIR__ . '/manifest.json';
        if (!file_exists($manifestPath)) {
            return;
        }

        $manifest = json_decode(file_get_contents($manifestPath), true);
        $configGroups = $manifest['config_groups'] ?? [];
        $appId = 'cmspro.notepad';

        $manifestCodes = [];

        foreach ($configGroups as $groupDef) {
            $group = \App\Models\ConfigGroup::updateOrCreate(
                ['code' => 'app_' . $appId . '_' . $groupDef['name']],
                [
                    'name' => $groupDef['title'],
                    'app_id' => $appId,
                    'sort' => 0,
                    'status' => 1,
                ]
            );

            foreach ($groupDef['items'] as $index => $item) {
                $fullCode = 'app_' . $appId . '_' . $item['name'];
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

        // 清理 manifest 中已删除的配置项
        \App\Models\ConfigItem::where('code', 'like', 'app_' . $appId . '_%')
            ->whereNotIn('code', $manifestCodes)
            ->delete();

        // 清理无子项的配置组
        \App\Models\ConfigGroup::where('app_id', $appId)
            ->whereDoesntHave('items')
            ->delete();
    }

    /**
     * 卸载时全量清理配置
     */
    protected function removeConfigs(): void
    {
        $appId = 'cmspro.notepad';

        \App\Models\ConfigItem::where('code', 'like', 'app_' . $appId . '_%')->delete();
        \App\Models\ConfigGroup::where('app_id', $appId)->delete();
    }
}

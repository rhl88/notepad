<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * 为已安装的推送记录表补齐索引结构
 *
 * 背景：000003 建表迁移的早期版本只建了 note_id / to_admin_user_id / is_read 三个单列索引，
 * 既无法从库层面阻止并发重复推送，也无法高效支撑「我收到的未读推送」这一最高频查询。
 * 由于建表迁移对已存在的表会直接跳过（hasTable 保护），已安装站点必须靠本迁移补齐。
 *
 * 处理顺序：清理历史重复数据 → 加唯一索引 → 加复合索引 → 删除被覆盖的冗余单列索引。
 * 全部步骤均以「索引是否已存在」为判断依据，重复执行安全。
 */
return new class extends Migration
{
    /** 推送记录表名 */
    private const TABLE = 'app_cmspro_notepad_pushes';

    /** 便签 + 接收人 唯一索引，防止重复推送 */
    private const UNIQUE_INDEX = 'app_cmspro_notepad_pushes_note_id_to_admin_user_id_unique';

    /** 接收人 + 已读标记 复合索引，支撑未读计数与收件箱查询 */
    private const COMPOSITE_INDEX = 'app_cmspro_notepad_pushes_to_admin_user_id_is_read_index';

    /** 被上述两个索引最左前缀覆盖、需清理的冗余单列索引 */
    private const REDUNDANT_INDEXES = [
        'app_cmspro_notepad_pushes_note_id_index',
        'app_cmspro_notepad_pushes_to_admin_user_id_index',
        'app_cmspro_notepad_pushes_is_read_index',
    ];

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        $this->removeDuplicatePushes();
        $this->addIndexes();
        $this->dropRedundantIndexes();
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        $this->removeNewIndexes();

        // 恢复原三个单列索引；重复数据已在 up() 中清理，回滚不会让其重新出现
        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->index('note_id');
            $table->index('to_admin_user_id');
            $table->index('is_read');
        });
    }

    /**
     * 清理历史重复推送：同一便签推给同一管理员时只保留 id 最小的一条
     *
     * 唯一索引建立前必须先消除重复，否则加索引会直接失败。
     * 被删除的记录 id 会写入 warning 日志，便于事后追溯。
     */
    private function removeDuplicatePushes(): void
    {
        $duplicates = DB::table(self::TABLE)
            ->selectRaw('note_id, to_admin_user_id, MIN(id) AS keep_id')
            ->groupBy('note_id', 'to_admin_user_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $row) {
            $removedIds = DB::table(self::TABLE)
                ->where('note_id', $row->note_id)
                ->where('to_admin_user_id', $row->to_admin_user_id)
                ->where('id', '>', $row->keep_id)
                ->pluck('id')
                ->all();

            if ($removedIds === []) {
                continue;
            }

            DB::table(self::TABLE)->whereIn('id', $removedIds)->delete();

            Log::warning('记事本应用清理重复推送记录', [
                'table' => self::TABLE,
                'note_id' => $row->note_id,
                'to_admin_user_id' => $row->to_admin_user_id,
                'kept_id' => $row->keep_id,
                'removed_ids' => $removedIds,
            ]);
        }
    }

    /**
     * 添加唯一索引与复合索引（已存在则跳过）
     */
    private function addIndexes(): void
    {
        $needUnique = ! Schema::hasIndex(self::TABLE, self::UNIQUE_INDEX);
        $needComposite = ! Schema::hasIndex(self::TABLE, self::COMPOSITE_INDEX);

        if (! $needUnique && ! $needComposite) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) use ($needUnique, $needComposite) {
            if ($needUnique) {
                $table->unique(['note_id', 'to_admin_user_id'], self::UNIQUE_INDEX);
            }

            if ($needComposite) {
                $table->index(['to_admin_user_id', 'is_read'], self::COMPOSITE_INDEX);
            }
        });
    }

    /**
     * 删除被新索引最左前缀覆盖的冗余单列索引
     */
    private function dropRedundantIndexes(): void
    {
        $existing = array_values(array_filter(
            self::REDUNDANT_INDEXES,
            fn (string $name): bool => Schema::hasIndex(self::TABLE, $name)
        ));

        if ($existing === []) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) use ($existing) {
            $table->dropIndex($existing);
        });
    }

    /**
     * 回滚时删除本迁移新增的两个索引
     */
    private function removeNewIndexes(): void
    {
        $dropUnique = Schema::hasIndex(self::TABLE, self::UNIQUE_INDEX);
        $dropComposite = Schema::hasIndex(self::TABLE, self::COMPOSITE_INDEX);

        if (! $dropUnique && ! $dropComposite) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) use ($dropUnique, $dropComposite) {
            if ($dropUnique) {
                $table->dropUnique(self::UNIQUE_INDEX);
            }

            if ($dropComposite) {
                $table->dropIndex(self::COMPOSITE_INDEX);
            }
        });
    }
};

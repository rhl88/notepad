<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('app_cmspro_notepad_notes')) {
            return;
        }

        Schema::create('app_cmspro_notepad_notes', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('主键ID');
            $table->unsignedBigInteger('admin_user_id')->comment('创建人ID，关联admin_users表');
            $table->string('title', 200)->comment('便签标题');
            $table->text('content')->nullable()->comment('便签内容');
            $table->unsignedTinyInteger('status')->default(0)->comment('状态：0-未完成 1-已完成');
            $table->unsignedTinyInteger('priority')->default(0)->comment('优先级：0-普通 1-重要 2-紧急');
            $table->string('color', 20)->default('')->comment('标记颜色，空为默认');
            $table->unsignedTinyInteger('pinned')->default(0)->comment('是否置顶：0-否 1-是');
            $table->timestamp('status_changed_at')->nullable()->comment('状态变更时间');
            $table->timestamp('create_time')->nullable()->comment('创建时间');
            $table->timestamp('update_time')->nullable()->comment('更新时间');

            $table->index('admin_user_id');
            $table->index('status');
            $table->index('pinned');
        });

        // COMMENT 为 MySQL 方言，仅 MySQL 驱动执行，保证 SQLite 等测试环境可迁移
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `app_cmspro_notepad_notes` COMMENT '记事本便签表'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_cmspro_notepad_notes');
    }
};

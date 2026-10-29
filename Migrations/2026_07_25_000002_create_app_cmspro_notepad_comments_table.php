<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('app_cmspro_notepad_comments')) {
            return;
        }

        Schema::create('app_cmspro_notepad_comments', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('主键ID');
            $table->unsignedBigInteger('note_id')->comment('关联便签ID，关联app_cmspro_notepad_notes表');
            $table->unsignedBigInteger('admin_user_id')->comment('评论人ID，关联admin_users表');
            $table->text('content')->comment('评论内容');
            $table->timestamp('create_time')->nullable()->comment('创建时间');
            $table->timestamp('update_time')->nullable()->comment('更新时间');

            $table->index('note_id');
            $table->index('admin_user_id');
        });

        // COMMENT 为 MySQL 方言，仅 MySQL 驱动执行，保证 SQLite 等测试环境可迁移
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `app_cmspro_notepad_comments` COMMENT '记事本评论表'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_cmspro_notepad_comments');
    }
};

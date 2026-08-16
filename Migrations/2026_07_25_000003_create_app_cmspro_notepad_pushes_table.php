<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('app_cmspro_notepad_pushes')) {
            return;
        }

        Schema::create('app_cmspro_notepad_pushes', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('主键ID');
            $table->unsignedBigInteger('note_id')->comment('关联便签ID，关联app_cmspro_notepad_notes表');
            $table->unsignedBigInteger('from_admin_user_id')->comment('推送人ID，关联admin_users表');
            $table->unsignedBigInteger('to_admin_user_id')->comment('被推送人ID，关联admin_users表');
            $table->string('message', 500)->default('')->comment('推送留言');
            $table->unsignedTinyInteger('is_read')->default(0)->comment('是否已读：0-未读 1-已读');
            $table->timestamp('create_time')->nullable()->comment('创建时间');
            $table->timestamp('update_time')->nullable()->comment('更新时间');

            $table->index('note_id');
            $table->index('to_admin_user_id');
            $table->index('is_read');
        });

        DB::statement("ALTER TABLE `app_cmspro_notepad_pushes` COMMENT '记事本推送记录表'");
    }

    public function down(): void
    {
        Schema::dropIfExists('app_cmspro_notepad_pushes');
    }
};

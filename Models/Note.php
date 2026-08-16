<?php

namespace App\Apps\CmsproNotepad\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Note extends BaseModel
{
    protected $table = 'app_cmspro_notepad_notes';

    public const CREATED_AT = 'create_time';
    public const UPDATED_AT = 'update_time';

    protected $fillable = [
        'admin_user_id', 'title', 'content', 'status',
        'priority', 'color', 'pinned', 'status_changed_at',
    ];

    protected $casts = [
        'create_time' => 'datetime:Y-m-d H:i:s',
        'update_time' => 'datetime:Y-m-d H:i:s',
        'status_changed_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * 创建人
     */
    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\AdminUser::class, 'admin_user_id');
    }

    /**
     * 评论列表
     */
    public function comments(): HasMany
    {
        return $this->hasMany(NoteComment::class, 'note_id');
    }

    /**
     * 推送记录
     */
    public function pushes(): HasMany
    {
        return $this->hasMany(Push::class, 'note_id');
    }
}

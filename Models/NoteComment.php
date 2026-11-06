<?php

namespace App\Apps\CmsproNotepad\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoteComment extends BaseModel
{
    protected $table = 'app_cmspro_notepad_comments';

    public const CREATED_AT = 'create_time';
    public const UPDATED_AT = 'update_time';

    protected $fillable = [
        'note_id', 'admin_user_id', 'content',
    ];

    protected $casts = [
        'create_time' => 'datetime:Y-m-d H:i:s',
        'update_time' => 'datetime:Y-m-d H:i:s',
        // 整型字段显式转型：前端判断「是否本人评论」时的严格比较依赖此处
        'note_id' => 'integer',
        'admin_user_id' => 'integer',
    ];

    /**
     * 关联便签
     */
    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class, 'note_id');
    }

    /**
     * 评论人
     */
    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\AdminUser::class, 'admin_user_id');
    }
}

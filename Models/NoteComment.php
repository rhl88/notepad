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

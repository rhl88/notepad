<?php

namespace App\Apps\CmsproNotepad\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Push extends BaseModel
{
    protected $table = 'app_cmspro_notepad_pushes';

    public const CREATED_AT = 'create_time';
    public const UPDATED_AT = 'update_time';

    protected $fillable = [
        'note_id', 'from_admin_user_id', 'to_admin_user_id',
        'message', 'is_read',
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
     * 推送人
     */
    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\AdminUser::class, 'from_admin_user_id');
    }

    /**
     * 被推送人
     */
    public function toUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\AdminUser::class, 'to_admin_user_id');
    }
}

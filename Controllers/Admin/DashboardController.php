<?php

namespace App\Apps\CmsproNotepad\Controllers\Admin;

use App\Apps\CmsproNotepad\Services\NoteService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    protected NoteService $noteService;

    public function __construct(NoteService $noteService)
    {
        $this->noteService = $noteService;
    }

    /**
     * 扩展仪表板页面（覆盖原 /admin/dashboard）
     */
    public function index()
    {
        return view('cmspro.notepad::Admin.dashboard');
    }

    /**
     * 仪表板便签摘要API
     */
    public function summary(Request $request)
    {
        $adminUserId = auth('admin')->id();
        $perPage = $request->input('limit', 5);

        // 我的便签（最近5条）
        $myNotes = \App\Apps\CmsproNotepad\Models\Note::where('admin_user_id', $adminUserId)
            ->orderByDesc('pinned')
            ->orderByDesc('id')
            ->limit($perPage)
            ->get()
            ->toArray();

        // 未读推送数量
        $unreadCount = $this->noteService->getUnreadCount($adminUserId);

        // 收到的便签（最近5条，含已读和未读）
        $receivedPushes = \App\Apps\CmsproNotepad\Models\Push::where('to_admin_user_id', $adminUserId)
            ->with(['note', 'fromUser'])
            ->orderByDesc('id')
            ->limit($perPage)
            ->get();

        $pushes = [];
        foreach ($receivedPushes as $push) {
            $noteArr = $push->note ? $push->note->toArray() : [];
            $pushes[] = [
                'id' => $push->id,
                'note_id' => $push->note_id,
                'title' => $noteArr['title'] ?? '',
                'from_user' => $push->fromUser ? ($push->fromUser->name ?: $push->fromUser->username) : '未知',
                'message' => $push->message ?? '',
                'is_read' => $push->is_read,
                'create_time' => $push->create_time ? $push->create_time->format('Y-m-d H:i:s') : null,
            ];
        }

        // 便签统计
        $totalNotes = \App\Apps\CmsproNotepad\Models\Note::where('admin_user_id', $adminUserId)->count();
        $pendingNotes = \App\Apps\CmsproNotepad\Models\Note::where('admin_user_id', $adminUserId)->where('status', 0)->count();
        $receivedCount = \App\Apps\CmsproNotepad\Models\Push::where('to_admin_user_id', $adminUserId)->count();

        return response()->json([
            'code' => 0,
            'data' => [
                'my_notes' => $myNotes,
                'unread_count' => $unreadCount,
                'received_notes' => $pushes,
                'total_notes' => $totalNotes,
                'pending_notes' => $pendingNotes,
                'received_count' => $receivedCount,
            ],
        ]);
    }
}

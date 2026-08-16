<?php

namespace App\Apps\CmsproNotepad\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\ConfigGroup;
use App\Models\ConfigItem;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /**
     * 设置页面
     */
    public function index()
    {
        return view('cmspro.notepad::Admin.settings.index');
    }

    /**
     * 获取设置
     */
    public function getSettings()
    {
        $appId = 'cmspro.notepad';
        $groups = ConfigGroup::where('app_id', $appId)
            ->with('items')
            ->get();

        return ApiResponse::success($groups);
    }

    /**
     * 更新设置
     */
    public function update(Request $request)
    {
        $appId = 'cmspro.notepad';
        $items = $request->input('items', []);

        foreach ($items as $code => $value) {
            $fullCode = 'app_' . $appId . '_' . $code;
            $configItem = ConfigItem::where('code', $fullCode)->first();
            if ($configItem) {
                $configItem->update(['value' => $value]);
            }
        }

        return ApiResponse::success(null, '保存成功');
    }
}

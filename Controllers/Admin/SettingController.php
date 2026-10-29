<?php

namespace App\Apps\CmsproNotepad\Controllers\Admin;

use App\Apps\CmsproNotepad\Services\NoteService;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\ConfigGroup;
use App\Models\ConfigItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SettingController extends Controller
{
    /** 应用标识，与 manifest.json 的 app_id 保持一致 */
    private const APP_ID = 'cmspro.notepad';

    /**
     * 可写配置项白名单：code => [中文名称, 最小值, 最大值]
     *
     * 仅允许写入 manifest.json 中 config_groups 声明的配置项，
     * 白名单之外的 code 一律拒绝，避免通过 items 参数越权覆盖框架其它配置。
     */
    private const CONFIG_RULES = [
        'per_page' => ['每页显示条数', 1, 100],
        'max_content_length' => ['便签内容最大字数', 100, 50000],
        'max_push_targets' => ['单次推送人数上限', 1, 100],
    ];

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
        $groups = ConfigGroup::where('app_id', self::APP_ID)
            ->with('items')
            ->get();

        return ApiResponse::success($groups);
    }

    /**
     * 更新设置
     *
     * 逐项校验配置 code 是否在白名单内、值是否为区间内的整数，任一项不合法即整体拒绝，
     * 防止非法值（如 per_page=0 导致分页死循环）写入后被业务层读取。
     */
    public function update(Request $request)
    {
        $items = $request->input('items', []);

        if (!is_array($items) || $items === []) {
            return ApiResponse::error(400, '没有需要保存的配置项');
        }

        $error = $this->validateItems($items);
        if ($error !== null) {
            return ApiResponse::error(400, $error);
        }

        // 配置 code 前缀与框架 registerConfigGroups 保持一致（app_id 中的点号转下划线）
        $prefix = app_id_to_table_prefix(self::APP_ID);

        foreach ($items as $code => $value) {
            $configItem = ConfigItem::where('code', $prefix . $code)->first();
            if ($configItem) {
                $configItem->update(['value' => (string) $value]);
            }
        }

        // 业务层配置为整体缓存，保存后必须清除，否则新值需等缓存过期才生效
        Cache::forget(NoteService::CONFIG_CACHE_KEY);

        return ApiResponse::success(null, '保存成功');
    }

    /**
     * 校验提交的配置项
     *
     * @param array<string, mixed> $items
     * @return string|null 校验通过返回 null，否则返回面向用户的错误提示
     */
    private function validateItems(array $items): ?string
    {
        foreach ($items as $code => $value) {
            if (!isset(self::CONFIG_RULES[$code])) {
                return "不支持的配置项：{$code}";
            }

            [$name, $min, $max] = self::CONFIG_RULES[$code];

            $intValue = filter_var($value, FILTER_VALIDATE_INT);
            if ($intValue === false) {
                return "{$name}必须为整数";
            }

            if ($intValue < $min || $intValue > $max) {
                return "{$name}必须在 {$min} ~ {$max} 之间";
            }
        }

        return null;
    }
}

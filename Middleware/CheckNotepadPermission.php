<?php

namespace App\Apps\CmsproNotepad\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * 记事本应用权限校验中间件
 *
 * 用法：Route::get('/notes', ...)->middleware('cmspro.notepad.permission:manage')
 * 参数为 manifest.json 中 permissions[].code 的后缀部分，内部自动补全为 cmspro.notepad.{suffix}。
 *
 * 放行规则（与框架 App\Http\Middleware\CheckPermission 语义保持一致）：
 * 1. 权限码未在 admin_permissions 表注册（应用未经市场安装或权限尚未同步）→ 放行，避免误伤存量站点；
 * 2. 超级管理员 → 放行；
 * 3. 当前管理员所属角色已分配该权限 → 放行；
 * 4. 其余情况 → 页面请求渲染应用自有的 403 页面，AJAX/JSON 请求返回统一错误体 + HTTP 403。
 */
class CheckNotepadPermission
{
    /** 应用权限码前缀 */
    private const PERMISSION_PREFIX = 'cmspro.notepad.';

    /** 权限码注册状态的缓存时长（秒） */
    private const CACHE_TTL = 300;

    /** 无权限错误码 */
    private const CODE_FORBIDDEN = 40301;

    /** 无权限 HTTP 状态码 */
    private const HTTP_FORBIDDEN = 403;

    public function handle(Request $request, Closure $next, string $suffix = 'manage'): Response
    {
        if (self::allows($suffix)) {
            return $next($request);
        }

        return $this->deny($request, '无访问权限，请联系管理员在「角色管理」中分配记事本相关权限');
    }

    /**
     * 判断当前登录管理员是否具备指定记事本权限
     *
     * 供路由中间件与仪表盘控制器共用（后者需据此决定便签面板是否渲染）。
     * 权限码未注册时返回 true，保证未安装场景与测试环境不受影响。
     */
    public static function allows(string $suffix): bool
    {
        $permissionCode = self::PERMISSION_PREFIX . $suffix;

        if (! self::permissionRegistered($permissionCode)) {
            return true;
        }

        $user = auth('admin')->user();

        return $user !== null && $user->hasPermission($permissionCode);
    }

    /**
     * 判断权限码是否已在框架权限表中注册
     *
     * 结果缓存 5 分钟，避免每个请求都查库；表不存在时视为未注册（放行）。
     */
    private static function permissionRegistered(string $permissionCode): bool
    {
        return (bool) Cache::remember(
            'cmspro.notepad:permission:registered:' . $permissionCode,
            self::CACHE_TTL,
            function () use ($permissionCode) {
                try {
                    return DB::table('admin_permissions')->where('code', $permissionCode)->exists();
                } catch (\Throwable) {
                    return false;
                }
            }
        );
    }

    /**
     * 拒绝访问：AJAX/JSON 请求返回统一错误体，页面请求渲染应用自有的 403 页面
     *
     * 页面请求不能使用 abort(403)：框架 bootstrap/app.php 的 HttpException 渲染器只处理 419，
     * 而 AuthorizationException 渲染器指向的 errors.403 视图并不存在，两者都会退化成 500。
     */
    private function deny(Request $request, string $message): Response
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(ApiResponse::error(self::CODE_FORBIDDEN, $message), 403);
        }

        return response()->view(
            'cmspro.notepad::Admin.errors.forbidden',
            ['message' => $message],
            self::HTTP_FORBIDDEN
        );
    }
}

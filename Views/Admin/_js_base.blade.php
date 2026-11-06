{{--
    应用后台共享 JS 片段：基础路径常量 + AJAX 错误文案助手 + HTML 转义

    用途：收敛散落在各视图中的硬编码 URL 前缀与重复的错误处理、转义逻辑，
          后续调整路由前缀、错误文案或转义口径时只需改这一处。
    用法：在宿主页面的 layui.use 回调开头 @include，之后统一用 NOTEPAD_BASE + '/api/...' 拼接，
          AJAX 失败时调用 notepadAjaxErrorText(xhr, '兜底文案') 取可展示的错误信息，
          拼接 innerHTML 前调用 escapeHtml(value) 转义后端数据。
    对外提供：NOTEPAD_BASE、notepadAjaxErrorText()、escapeHtml()，三者缺一不可，
              宿主与二级片段均直接引用，删除任一项都会导致页面 JS 报 ReferenceError。
    注意：本片段被 include 后会直接输出 JS 语句，宿主 include 行自身的缩进即为输出缩进；
          因 include 在 layui.use 回调内，上述函数为回调作用域的局部声明，
          二级片段（_note_detail / _push_dialog）在宿主回调作用域内，
          直接复用宿主声明的常量与函数，不要重复声明。
--}}
var NOTEPAD_BASE = '/admin/cmspro/notepad';

/**
 * 将 AJAX 失败响应统一解析为可展示文案
 *
 * 取值优先级：Laravel 422 验证错误详情 → 后端统一错误体的 message → HTTP 状态码文案 → 兜底文案。
 * 只返回字符串、不弹层，由各调用点自行决定展示方式（layer.msg 或表单行内提示）。
 *
 * @param {Object} xhr         jQuery 传入的 jqXHR 对象
 * @param {string} fallbackMsg 解析不出任何信息时使用的业务兜底文案
 * @return {string} 可直接展示给管理员的错误描述
 */
function notepadAjaxErrorText(xhr, fallbackMsg) {
    var statusText = {
        401: '登录状态已失效，请重新登录后再试',
        403: '无权限执行该操作，请联系管理员在「角色管理」中分配记事本权限',
        404: '请求的内容不存在或已被删除',
        419: '页面已过期，请刷新后重新提交',
        500: '服务器处理出错，请稍后重试'
    };
    var body = (xhr && xhr.responseJSON) || null;
    var status = (xhr && xhr.status) || 0;

    if (body && body.errors) {
        var messages = body.errors[Object.keys(body.errors)[0]];
        if (messages && messages.length) {
            return messages[0];
        }
    }
    if (body && (body.message || body.msg)) {
        return body.message || body.msg;
    }

    return statusText[status] || fallbackMsg || '网络异常，请求未完成';
}

/**
 * HTML 转义：把后端数据拼进 innerHTML 或属性值之前统一过滤
 *
 * 采用强口径，避免早期各页面本地弱实现的两个缺陷：
 * 1. 用 `value || ''` 兜底会把数字 0 与字符串 '0' 一起吞成空串
 *    （priority=0「普通」、pinned=0「未置顶」都会渲染成空白），
 *    故仅对 null / undefined 返回空串，其余一律 String() 后按字面保留；
 * 2. 漏转单引号，导致拼进 onclick='...' 或 data-keyword='...' 这类
 *    单引号属性时可被内容中的 ' 提前闭合，故 ' 一并转义为 &#39;。
 *
 * @param {*} value 任意待转义值，允许传入 null / undefined / 数字
 * @return {string} 可安全拼入 HTML 的字符串
 */
function escapeHtml(value) {
    if (value === null || value === undefined) {
        return '';
    }
    var entityMap = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    };

    return String(value).replace(/[&<>"']/g, function (ch) {
        return entityMap[ch];
    });
}

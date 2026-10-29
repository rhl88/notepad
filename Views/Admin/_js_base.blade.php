{{--
    应用后台基础路径常量（纯 JS 片段）

    用途：收敛散落在各视图中的硬编码 URL 前缀，后续调整路由前缀时只需改这一处。
    用法：在宿主页面的 layui.use 回调开头 @include，之后统一用 NOTEPAD_BASE + '/api/...' 拼接。
    注意：本片段被 include 后会直接输出 JS 语句，宿主 include 行自身的缩进即为输出缩进；
          _push_dialog 等二级片段在宿主回调作用域内，直接复用宿主声明的常量，不要重复声明。
--}}
var NOTEPAD_BASE = '/admin/cmspro/notepad';

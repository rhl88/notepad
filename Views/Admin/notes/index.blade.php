<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>便签列表</title>
    <link rel="stylesheet" href="{{ asset('CmsProUi/component/pear/css/pear.css') }}">
    <link rel="stylesheet" href="{{ asset('CmsProUi/font-awesome/4.7.0/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/variables.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/reset.css') }}">
    <style>
        .tab-bar { margin-bottom: 0; }
        .tab-bar .layui-btn { margin-right: 5px; position: relative; }
        .unread-badge {
            position: absolute; top: -6px; right: -8px;
            background: #ff5722; color: #fff; font-size: 11px; font-weight: bold;
            min-width: 18px; height: 18px; line-height: 18px; text-align: center;
            border-radius: 9px; padding: 0 4px;
            border: 2px solid #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        .unread-badge.pulse { animation: badge-pulse 1.5s ease-out; }
        @keyframes badge-pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.4); }
            100% { transform: scale(1); }
        }
        /* 标记颜色圆点（仅「我的便签」列表使用，颜色值经后端白名单校验） */
        .color-dot {
            display: inline-block; width: 16px; height: 16px; border-radius: 50%;
            vertical-align: middle; box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.08);
        }
        .color-none { color: #c2c2c2; }
    </style>
</head>
<body>
<div class="pear-container">
    {{-- Tab切换 --}}
    <div class="layui-card">
        <div class="layui-card-body tab-bar">
            <button class="layui-btn layui-btn-sm" id="tab-mine" onclick="switchTab('mine')">
                <i class="fa fa-sticky-note"></i> 我的便签
            </button>
            <button class="layui-btn layui-btn-sm layui-btn-primary" id="tab-received" onclick="switchTab('received')">
                <i class="fa fa-inbox"></i> 收到的便签 <span class="unread-badge" id="unread-badge" style="display:none;">0</span>
            </button>
        </div>
    </div>

    {{-- 搜索条件卡 — 我的便签 --}}
    <div class="layui-card" id="search-card-mine">
        <div class="layui-card-body">
            <form class="layui-form" action="">
                <div class="layui-inline">
                    <div class="layui-input-inline">
                        <select name="status" lay-filter="status">
                            <option value="">全部状态</option>
                            <option value="0">未完成</option>
                            <option value="1">已完成</option>
                        </select>
                    </div>
                </div>
                <div class="layui-inline">
                    <div class="layui-input-inline">
                        <select name="priority" lay-filter="priority">
                            <option value="">全部优先级</option>
                            <option value="0">普通</option>
                            <option value="1">重要</option>
                            <option value="2">紧急</option>
                        </select>
                    </div>
                </div>
                <div class="layui-inline">
                    <div class="layui-input-inline">
                        <input type="text" name="keyword" placeholder="标题/内容关键词" class="layui-input">
                    </div>
                </div>
                <div class="layui-inline">
                    <button class="layui-btn layui-btn-md" lay-submit lay-filter="note-query">
                        <i class="layui-icon layui-icon-search"></i> 查询
                    </button>
                    <button type="reset" class="layui-btn layui-btn-md layui-btn-primary">
                        <i class="layui-icon layui-icon-refresh"></i> 重置
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- 搜索条件卡 — 收到的便签 --}}
    <div class="layui-card" id="search-card-received" style="display:none;">
        <div class="layui-card-body">
            <form class="layui-form" action="">
                <div class="layui-inline">
                    <div class="layui-input-inline">
                        <select name="is_read" lay-filter="is_read">
                            <option value="">全部状态</option>
                            <option value="0">未读</option>
                            <option value="1">已读</option>
                        </select>
                    </div>
                </div>
                <div class="layui-inline">
                    <button class="layui-btn layui-btn-md" lay-submit lay-filter="note-query">
                        <i class="layui-icon layui-icon-search"></i> 查询
                    </button>
                    <button type="reset" class="layui-btn layui-btn-md layui-btn-primary">
                        <i class="layui-icon layui-icon-refresh"></i> 重置
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- 表格卡 --}}
    <div class="layui-card">
        <div class="layui-card-body">
            <table id="note-table" lay-filter="note-table"></table>
        </div>
    </div>

    {{-- 工具栏 --}}
    <script type="text/html" id="note-toolbar">
        <button class="layui-btn layui-btn-normal layui-btn-sm" lay-event="add">
            <i class="fa fa-plus"></i> 新建便签
        </button>
        <button class="layui-btn layui-btn-danger layui-btn-sm" lay-event="batchRemove">
            <i class="layui-icon layui-icon-delete"></i> 批量删除
        </button>
    </script>

    {{-- Layui 模板 --}}
    <script type="text/html" id="status-tpl">
@verbatim
        {{# if(d.status === 0){ }}
            <span class="layui-badge layui-bg-orange">未完成</span>
        {{# } else { }}
            <span class="layui-badge layui-bg-green">已完成</span>
        {{# } }}
@endverbatim
    </script>

    <script type="text/html" id="priority-tpl">
@verbatim
        {{# if(d.priority === 2){ }}
            <span class="layui-badge">紧急</span>
        {{# } else if(d.priority === 1){ }}
            <span class="layui-badge layui-bg-orange">重要</span>
        {{# } else { }}
            <span class="layui-badge layui-bg-blue">普通</span>
        {{# } }}
@endverbatim
    </script>

    <script type="text/html" id="pinned-tpl">
@verbatim
        {{# if(d.pinned == 1){ }}
            <i class="fa fa-thumb-tack" style="color:#ff5722;font-size:16px;" title="置顶"></i>
        {{# } }}
@endverbatim
    </script>

    <script type="text/html" id="color-tpl">
@verbatim
        {{# if(d.color){ }}
            <span class="color-dot" style="background:{{ d.color }};" title="标记颜色 {{ d.color }}"></span>
        {{# } else { }}
            <span class="color-none">—</span>
        {{# } }}
@endverbatim
    </script>

    <script type="text/html" id="is-read-tpl">
@verbatim
        {{# if(d.is_read === 0){ }}
            <span class="layui-badge layui-bg-orange">未读</span>
        {{# } else { }}
            <span class="layui-badge layui-bg-green">已读</span>
        {{# } }}
@endverbatim
    </script>

    {{-- 我的便签行操作：完成/置顶为快捷切换，无需进入详情或编辑弹窗 --}}
    <script type="text/html" id="bar-tpl">
@verbatim
        <div class="layui-btn-container">
            <a class="layui-btn layui-btn-xs" lay-event="detail">详情</a>
            <a class="layui-btn layui-btn-xs layui-btn-normal" lay-event="edit">编辑</a>
            {{# if(d.status === 0){ }}
                <a class="layui-btn layui-btn-xs layui-btn-primary" lay-event="toggleStatus" title="标记为已完成"><i class="fa fa-check"></i></a>
            {{# } else { }}
                <a class="layui-btn layui-btn-xs layui-btn-primary" lay-event="toggleStatus" title="撤销完成状态"><i class="fa fa-undo"></i></a>
            {{# } }}
            {{# if(d.pinned == 1){ }}
                <a class="layui-btn layui-btn-xs layui-btn-warm" lay-event="togglePinned" title="取消置顶"><i class="fa fa-thumb-tack"></i></a>
            {{# } else { }}
                <a class="layui-btn layui-btn-xs layui-btn-primary" lay-event="togglePinned" title="置顶该便签"><i class="fa fa-thumb-tack"></i></a>
            {{# } }}
            <a class="layui-btn layui-btn-xs layui-btn-danger" lay-event="del">删除</a>
        </div>
@endverbatim
    </script>

    <script type="text/html" id="received-bar-tpl">
        <div class="layui-btn-container">
            <a class="layui-btn layui-btn-xs" lay-event="detail">查看</a>
        </div>
    </script>
</div>

<script src="{{ asset('CmsProUi/component/layui/layui.js') }}"></script>
<script src="{{ asset('CmsProUi/component/pear/pear.js') }}"></script>
<script>
layui.use(['table', 'form', 'layer', 'jquery'], function () {
    var table = layui.table, form = layui.form, layer = layui.layer, $ = layui.jquery;
    var currentTab = 'mine';
    @include('cmspro.notepad::Admin._js_base')

    // 解析URL参数，自动切换Tab和筛选
    var _urlParams = new URLSearchParams(window.location.search);
    if (_urlParams.get('tab') === 'received') {
        currentTab = 'received';
    }

    $.ajaxSetup({
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        statusCode: {
            401: function () {
                layer.msg('登录已过期，请重新登录', { icon: 2, time: 1500 }, function () {
                    top.location.href = '/admin/login';
                });
            }
        }
    });

    // 加载未读数量
    var _prevUnreadCount = 0;
    function loadUnreadCount() {
        $.get(NOTEPAD_BASE + '/api/notes/unread-count', function(res) {
            if (res.code === 0 && res.data.count > 0) {
                var count = res.data.count;
                var $badge = $('#unread-badge');
                $badge.text(count > 99 ? '99+' : count);
                $badge.show();
                // 数量增加时播放脉冲动画
                if (count > _prevUnreadCount) {
                    $badge.removeClass('pulse');
                    setTimeout(function() { $badge.addClass('pulse'); }, 10);
                }
                _prevUnreadCount = count;
            } else {
                $('#unread-badge').hide();
                _prevUnreadCount = 0;
            }
        });
    }
    loadUnreadCount();
    // 每30秒轮询未读数量
    setInterval(loadUnreadCount, 30000);

    // 我的便签列定义
    var mineCols = [[
        { type: 'checkbox', fixed: 'left' },
        { field: 'id', title: 'ID', align: 'center', width: 70, sort: true },
        { field: 'pinned', title: '置顶', align: 'center', width: 60, templet: '#pinned-tpl' },
        { field: 'color', title: '颜色', align: 'center', width: 70, templet: '#color-tpl' },
        { field: 'title', title: '标题', minWidth: 150 },
        { field: 'content', title: '内容', minWidth: 200 },
        { field: 'priority', title: '优先级', align: 'center', width: 90, templet: '#priority-tpl' },
        { field: 'status', title: '状态', align: 'center', width: 90, templet: '#status-tpl' },
        { field: 'create_time', title: '创建时间', align: 'center', width: 170 },
        { title: '操作', toolbar: '#bar-tpl', align: 'center', width: 250, fixed: 'right' }
    ]];

    // 收到的便签列定义
    var receivedCols = [[
        { field: 'id', title: 'ID', align: 'center', width: 70, sort: true },
        { field: 'title', title: '标题', minWidth: 150 },
        { field: 'content', title: '内容', minWidth: 150 },
        { field: 'from_user', title: '推送人', align: 'center', width: 100 },
        { field: 'message', title: '推送留言', minWidth: 120 },
        { field: 'is_read', title: '已读', align: 'center', width: 80, templet: '#is-read-tpl' },
        { field: 'create_time', title: '推送时间', align: 'center', width: 170 },
        { title: '操作', toolbar: '#received-bar-tpl', align: 'center', width: 80, fixed: 'right' }
    ]];

    /**
     * 空状态引导文案
     *
     * Layui table 的 text.none 仅接受字符串，故切 Tab 时随 reload 一并替换。
     *
     * @param {string} tab mine | received
     * @returns {string}
     */
    function emptyText(tab) {
        return tab === 'received'
            ? '暂无他人推送的便签，可切回「我的便签」创建后推送给同事'
            : '暂无便签，点击右上角「新建便签」开始记录';
    }

    var tableInst = table.render({
        elem: '#note-table',
        url: NOTEPAD_BASE + '/api/notes',
        where: currentTab === 'received' ? { tab: 'received', is_read: _urlParams.get('is_read') || '' } : { tab: 'mine' },
        text: { none: emptyText(currentTab) },
        page: {
            layout: ['count', 'prev', 'page', 'next', 'limit'],
            groups: 5,
            limit: 20,
            limits: [10, 20, 50, 100]
        },
        cols: currentTab === 'received' ? receivedCols : mineCols,
        skin: false,
        toolbar: '#note-toolbar',
        defaultToolbar: [{
            title: '刷新',
            layEvent: 'refresh',
            icon: 'layui-icon-refresh'
        }, 'filter', 'print', 'exports'],
        parseData: function(res) {
            return {
                "code": res.code === 0 ? 0 : 1,
                "msg": res.msg || "",
                "count": res.count || 0,
                "data": res.data || []
            };
        }
    });

    // URL参数自动初始化：切换Tab样式、搜索卡、筛选条件
    if (currentTab === 'received') {
        $('#tab-received').removeClass('layui-btn-primary');
        $('#tab-mine').addClass('layui-btn-primary');
        $('#search-card-mine').hide();
        $('#search-card-received').show();
        var _isReadVal = _urlParams.get('is_read');
        if (_isReadVal !== null && _isReadVal !== '') {
            $('select[name="is_read"]').val(_isReadVal);
        }
        form.render('select');
    }

    // Tab切换
    window.switchTab = function(tab) {
        currentTab = tab;
        if (tab === 'mine') {
            $('#tab-mine').removeClass('layui-btn-primary');
            $('#tab-received').addClass('layui-btn-primary');
            $('#search-card-mine').show();
            $('#search-card-received').hide();
        } else {
            $('#tab-received').removeClass('layui-btn-primary');
            $('#tab-mine').addClass('layui-btn-primary');
            $('#search-card-mine').hide();
            $('#search-card-received').show();
            // 显示后重新渲染Layui select
            form.render('select');
            loadUnreadCount();
        }
        tableInst.reload({
            where: { tab: tab },
            cols: tab === 'mine' ? mineCols : receivedCols,
            text: { none: emptyText(tab) },
            page: { curr: 1 }
        });
    };

    // 搜索
    form.on('submit(note-query)', function(data) {
        data.field.tab = currentTab;
        tableInst.reload({ where: data.field, page: { curr: 1 } });
        return false;
    });

    // 工具栏事件
    table.on('toolbar(note-table)', function(obj) {
        if (obj.event === 'refresh') {
            tableInst.reload();
        } else if (obj.event === 'add') {
            openNoteForm();
        } else if (obj.event === 'batchRemove') {
            batchRemove();
        }
    });

    // 行操作事件
    table.on('tool(note-table)', function(obj) {
        if (obj.event === 'detail') {
            viewDetail(obj);
        } else if (obj.event === 'edit') {
            openNoteForm(obj.data.id);
        } else if (obj.event === 'del') {
            removeNote(obj);
        } else if (obj.event === 'toggleStatus') {
            toggleStatusInRow(obj);
        } else if (obj.event === 'togglePinned') {
            togglePinnedInRow(obj);
        }
    });

    /**
     * 表单弹窗关闭前确认
     *
     * iframe 弹窗点右上角 X 不会触发子页 beforeunload，须由父页拦截：
     * 子页把「是否有未保存改动」暴露在 contentWindow.isFormDirty；
     * 子页尚未加载完成或跨域取值异常时视为无改动，不阻塞关闭。
     *
     * @param {number} index  弹窗索引
     * @param {Object} layero 弹窗 DOM 对象
     * @returns {boolean} false 表示阻止本次关闭
     */
    function confirmCloseForm(index, layero) {
        var frame = layero.find('iframe')[0];
        var win = frame && frame.contentWindow;
        var dirty = false;

        try {
            dirty = !!(win && typeof win.isFormDirty === 'function' && win.isFormDirty());
        } catch (e) {
            dirty = false;
        }
        if (!dirty) {
            return true;
        }

        layer.confirm('表单有未保存的改动，确定关闭？', { icon: 3, title: '提示' }, function (ci) {
            layer.close(ci);
            layer.close(index);
        });

        return false;
    }

    // 新建/编辑便签弹窗
    function openNoteForm(id) {
        var isEdit = !!id;
        var title = isEdit ? '编辑便签' : '新建便签';
        // 编辑走 /notes/{id}/edit（edit 权限），新建走 /notes/create（create 权限）
        var url = isEdit
            ? NOTEPAD_BASE + '/notes/' + id + '/edit'
            : NOTEPAD_BASE + '/notes/create';

        layer.open({
            type: 2,
            title: title,
            area: ['600px', '500px'],
            shade: [0.5, '#000'],
            shadeClose: 0,
            content: url,
            cancel: function (index, layero) {
                return confirmCloseForm(index, layero);
            }
            // 不在此处刷新：表单提交成功时子页已调用 parent.layui.table.reload，避免重复请求
        });
    }

    // 查看详情：弹窗由共享片段实现，此处只负责从表格行数据解析便签 ID
    function viewDetail(obj) {
        openNoteDetail(currentTab === 'received' ? obj.data.note_id : obj.data.id);
    }

    // 操作后的刷新回调（供详情弹窗片段调用）：局部重载表格并同步未读徽章
    function notepadRefresh() {
        tableInst.reload();
        loadUnreadCount();
    }

    // 详情弹窗 / 评论 / 切换状态（共享片段，与仪表盘面板同源，
    // 依赖本作用域的 $ / layer / escapeHtml / notepadRefresh）
    @include('cmspro.notepad::Admin.notes._note_detail')

    // 推送弹窗（共享片段，与仪表盘面板同源，依赖本作用域的 $ / form / layer / escapeHtml）
    @include('cmspro.notepad::Admin.notes._push_dialog', ['maxPushTargets' => $maxPushTargets])

    /**
     * 行内快捷切换完成状态
     *
     * 与详情弹窗内的 toggleNoteStatus 区分：行内操作无需关闭任何弹窗。
     */
    function toggleStatusInRow(obj) {
        patchNoteRow(NOTEPAD_BASE + '/api/notes/' + obj.data.id + '/toggle-status', 'PUT', null);
    }

    // 行内快捷切换置顶
    function togglePinnedInRow(obj) {
        var nextPinned = obj.data.pinned == 1 ? 0 : 1;
        patchNoteRow(NOTEPAD_BASE + '/api/notes/' + obj.data.id, 'PUT', { pinned: nextPinned });
    }

    // 行内快捷操作的统一请求处理：成功仅提示并刷新表格，失败展示后端 message（如非所有者 40301）
    function patchNoteRow(url, method, payload) {
        var loading = layer.load();
        $.ajax({
            url: url,
            type: method,
            contentType: 'application/json',
            data: payload ? JSON.stringify(payload) : undefined,
            success: function(res) {
                layer.close(loading);
                if (res.code === 0) {
                    layer.msg(res.message || '操作成功', { icon: 1, time: 1000 });
                    tableInst.reload();
                } else {
                    layer.msg(res.message || '操作失败', { icon: 2 });
                }
            },
            error: function() {
                layer.close(loading);
                layer.msg('请求失败', { icon: 2 });
            }
        });
    }

    // 删除便签
    function removeNote(obj) {
        layer.confirm('确定要删除该便签？删除后无法恢复！', {
            icon: 3, title: '操作提示', btn: ['确定', '取消']
        }, function(index) {
            layer.close(index);
            var loading = layer.load();
            $.ajax({
                url: NOTEPAD_BASE + '/api/notes/' + obj.data.id,
                type: 'DELETE',
                success: function(res) {
                    layer.close(loading);
                    if (res.code === 0) {
                        layer.msg('删除成功', { icon: 1, time: 1000 }, function() {
                            tableInst.reload();
                        });
                    } else {
                        layer.msg(res.message || '删除失败', { icon: 2 });
                    }
                },
                error: function() {
                    layer.close(loading);
                    layer.msg('请求失败', { icon: 2 });
                }
            });
        });
    }

    // 批量删除
    function batchRemove() {
        var checkData = table.checkStatus('note-table').data;
        if (checkData.length === 0) {
            layer.msg('未选中数据', { icon: 3, time: 1000 });
            return false;
        }
        var ids = checkData.map(function(item) { return item.id; });
        layer.confirm('确定要删除选中的 ' + ids.length + ' 条便签？删除后无法恢复！', {
            icon: 3, title: '操作提示', btn: ['确定', '取消']
        }, function(index) {
            layer.close(index);
            var loading = layer.load();
            $.ajax({
                url: NOTEPAD_BASE + '/api/notes/batch-delete',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ ids: ids }),
                success: function(res) {
                    layer.close(loading);
                    if (res.code === 0) {
                        layer.msg('删除成功', { icon: 1, time: 1000 }, function() {
                            tableInst.reload();
                        });
                    } else {
                        layer.msg(res.message || '删除失败', { icon: 2 });
                    }
                },
                error: function() {
                    layer.close(loading);
                    layer.msg('请求失败', { icon: 2 });
                }
            });
        });
    }

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
});
</script>
</body>
</html>

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

    <script type="text/html" id="is-read-tpl">
@verbatim
        {{# if(d.is_read === 0){ }}
            <span class="layui-badge layui-bg-orange">未读</span>
        {{# } else { }}
            <span class="layui-badge layui-bg-green">已读</span>
        {{# } }}
@endverbatim
    </script>

    <script type="text/html" id="bar-tpl">
        <div class="layui-btn-container">
            <a class="layui-btn layui-btn-xs" lay-event="detail">详情</a>
            <a class="layui-btn layui-btn-xs layui-btn-normal" lay-event="edit">编辑</a>
            <a class="layui-btn layui-btn-xs layui-btn-danger" lay-event="del">删除</a>
        </div>
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
        $.get('/admin/cmspro/notepad/api/notes/unread-count', function(res) {
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
        { field: 'title', title: '标题', minWidth: 150 },
        { field: 'content', title: '内容', minWidth: 200 },
        { field: 'priority', title: '优先级', align: 'center', width: 90, templet: '#priority-tpl' },
        { field: 'status', title: '状态', align: 'center', width: 90, templet: '#status-tpl' },
        { field: 'create_time', title: '创建时间', align: 'center', width: 170 },
        { title: '操作', toolbar: '#bar-tpl', align: 'center', width: 160, fixed: 'right' }
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

    var tableInst = table.render({
        elem: '#note-table',
        url: '/admin/cmspro/notepad/api/notes',
        where: currentTab === 'received' ? { tab: 'received', is_read: _urlParams.get('is_read') || '' } : { tab: 'mine' },
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
        }
    });

    // 新建/编辑便签弹窗
    function openNoteForm(id) {
        var isEdit = !!id;
        var title = isEdit ? '编辑便签' : '新建便签';

        layer.open({
            type: 2,
            title: title,
            area: ['600px', '500px'],
            shade: [0.5, '#000'],
            shadeClose: 0,
            content: '/admin/cmspro/notepad/notes/create' + (isEdit ? '?id=' + id : ''),
            end: function() {
                tableInst.reload();
            }
        });
    }

    // 查看详情
    function viewDetail(obj) {
        var noteId = currentTab === 'received' ? obj.data.note_id : obj.data.id;
        var loading = layer.load();
        $.get('/admin/cmspro/notepad/api/notes/' + noteId, function(res) {
            layer.close(loading);
            if (res.code !== 0 || !res.data) {
                layer.msg(res.message || '获取详情失败', { icon: 2 });
                return;
            }
            var n = res.data;
            var statusText = n.status === 1 ? '已完成' : '未完成';
            var priorityMap = { 0: '普通', 1: '重要', 2: '紧急' };
            var html = '<div style="padding:15px 20px;">';
            html += '<table class="layui-table" lay-skin="nob">';
            html += '<tr><td style="width:90px;font-weight:bold;">标题</td><td>' + escapeHtml(n.title) + '</td></tr>';
            html += '<tr><td style="font-weight:bold;">状态</td><td>' + statusText + '</td></tr>';
            html += '<tr><td style="font-weight:bold;">优先级</td><td>' + (priorityMap[n.priority] || '普通') + '</td></tr>';
            html += '<tr><td style="font-weight:bold;">内容</td><td style="white-space:pre-wrap;">' + escapeHtml(n.content || '') + '</td></tr>';
            html += '<tr><td style="font-weight:bold;">创建时间</td><td>' + (n.create_time || '-') + '</td></tr>';
            html += '</table>';

            // 评论区域
            if (n.comments && n.comments.length > 0) {
                html += '<hr><h4>评论 (' + n.comments.length + ')</h4>';
                html += '<div style="max-height:150px;overflow-y:auto;">';
                n.comments.forEach(function(c) {
                    var author = c.admin_user ? (c.admin_user.name || c.admin_user.username) : '未知';
                    html += '<div style="padding:5px 0;border-bottom:1px dashed #eee;">';
                    html += '<strong>' + escapeHtml(author) + '</strong> <span style="color:#999;font-size:12px;">' + (c.create_time || '') + '</span>';
                    html += '<div style="margin-top:3px;">' + escapeHtml(c.content) + '</div>';
                    html += '</div>';
                });
                html += '</div>';
            }

            // 推送记录
            if (n.pushes && n.pushes.length > 0) {
                html += '<hr><h4>推送记录 (' + n.pushes.length + ')</h4>';
                html += '<div style="max-height:100px;overflow-y:auto;">';
                n.pushes.forEach(function(p) {
                    var toUser = p.to_user ? (p.to_user.name || p.to_user.username) : '未知';
                    html += '<div style="padding:3px 0;"><span>' + escapeHtml(toUser) + '</span>';
                    if (p.message) html += ' <span style="color:#999;">- ' + escapeHtml(p.message) + '</span>';
                    html += ' <span style="color:#999;font-size:12px;">' + (p.create_time || '') + '</span></div>';
                });
                html += '</div>';
            }

            html += '<hr>';
            html += '<div class="layui-form" lay-filter="detailForm">';
            html += '<div class="layui-form-item">';
            html += '<div class="layui-input-block" style="margin-left:0;">';
            html += '<input type="text" id="comment-input" placeholder="输入评论内容..." class="layui-input" style="display:inline-block;width:calc(100% - 80px);">';
            html += '<button class="layui-btn layui-btn-sm" onclick="submitComment(' + n.id + ')" style="margin-left:5px;">评论</button>';
            html += '</div></div>';
            if (n.admin_user_id === {{ auth("admin")->id() }}) {
                html += '<div class="layui-form-item">';
                html += '<div class="layui-input-block" style="margin-left:0;">';
                html += '<button class="layui-btn layui-btn-sm layui-btn-normal" onclick="openPushDialog(' + n.id + ')"><i class="fa fa-paper-plane"></i> 推送</button>';
                html += '<button class="layui-btn layui-btn-sm" onclick="toggleNoteStatus(' + n.id + ')"><i class="fa fa-check"></i> 切换状态</button>';
                html += '</div></div>';
            }
            html += '</div></div>';

            layer.open({
                type: 1,
                title: '便签详情',
                area: ['650px', '90%'],
                shade: [0.5, '#000'],
                shadeClose: 0,
                content: html,
                end: function() {
                    tableInst.reload();
                    loadUnreadCount();
                }
            });
        });
    }

    // 提交评论
    window.submitComment = function(noteId) {
        var content = $('#comment-input').val().trim();
        if (!content) {
            layer.msg('请输入评论内容', { icon: 3 });
            return;
        }
        $.ajax({
            url: '/admin/cmspro/notepad/api/notes/' + noteId + '/comments',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ content: content }),
            success: function(res) {
                if (res.code === 0) {
                    layer.msg('评论成功', { icon: 1, time: 1000 });
                    setTimeout(function() { layer.closeAll(); tableInst.reload(); }, 800);
                } else {
                    layer.msg(res.message || '评论失败', { icon: 2 });
                }
            },
            error: function() { layer.msg('请求失败', { icon: 2 }); }
        });
    };

    // 推送弹窗
    window.openPushDialog = function(noteId) {
        var loading = layer.load();
        $.get('/admin/cmspro/notepad/api/admin-users', function(res) {
            layer.close(loading);
            if (res.code !== 0 || !res.data) {
                layer.msg('获取管理员列表失败', { icon: 2 });
                return;
            }
            var users = res.data;
            var html = '<div style="padding:15px 20px;">';
            html += '<form class="layui-form" lay-filter="pushForm">';
            html += '<div class="layui-form-item"><label class="layui-form-label">选择管理员</label>';
            html += '<div class="layui-input-block" style="max-height:150px;overflow-y:auto;border:1px solid #e6e6e6;padding:5px;">';
            users.forEach(function(u) {
                var displayName = u.name ? u.name + ' (' + u.username + ')' : u.username;
                html += '<input type="checkbox" name="target_users" value="' + u.id + '" title="' + escapeHtml(displayName) + '">';
            });
            html += '</div></div>';
            html += '<div class="layui-form-item"><label class="layui-form-label">推送留言</label>';
            html += '<div class="layui-input-block"><textarea id="push-message" class="layui-textarea" placeholder="可选推送留言（最多500字）" maxlength="500"></textarea></div></div>';
            html += '</form></div>';

            layer.open({
                type: 1,
                title: '推送便签',
                area: ['500px', '420px'],
                shade: [0.5, '#000'],
                shadeClose: 0,
                content: html,
                btn: ['推送', '取消'],
                success: function() { form.render('checkbox'); },
                btn1: function(index) {
                    var checked = [];
                    $('input[name=target_users]:checked').each(function() {
                        checked.push(parseInt($(this).val()));
                    });
                    if (checked.length === 0) {
                        layer.msg('请选择至少一位管理员', { icon: 3 });
                        return false;
                    }
                    var message = $('#push-message').val();
                    $.ajax({
                        url: '/admin/cmspro/notepad/api/notes/' + noteId + '/push',
                        type: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify({ target_user_ids: checked, message: message }),
                        success: function(res) {
                            if (res.code === 0) {
                                layer.msg(res.message || '推送成功', { icon: 1 });
                                layer.close(index);
                            } else {
                                layer.msg(res.message || '推送失败', { icon: 2 });
                            }
                        },
                        error: function() { layer.msg('请求失败', { icon: 2 }); }
                    });
                    return false;
                }
            });
        });
    };

    // 切换状态
    window.toggleNoteStatus = function(noteId) {
        $.ajax({
            url: '/admin/cmspro/notepad/api/notes/' + noteId + '/toggle-status',
            type: 'PUT',
            success: function(res) {
                if (res.code === 0) {
                    layer.msg(res.message || '操作成功', { icon: 1 });
                    setTimeout(function() { layer.closeAll(); tableInst.reload(); }, 800);
                } else {
                    layer.msg(res.message || '操作失败', { icon: 2 });
                }
            },
            error: function() { layer.msg('请求失败', { icon: 2 }); }
        });
    };

    // 删除便签
    function removeNote(obj) {
        layer.confirm('确定要删除该便签？删除后无法恢复！', {
            icon: 3, title: '操作提示', btn: ['确定', '取消']
        }, function(index) {
            layer.close(index);
            var loading = layer.load();
            $.ajax({
                url: '/admin/cmspro/notepad/api/notes/' + obj.data.id,
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
                url: '/admin/cmspro/notepad/api/notes/batch-delete',
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

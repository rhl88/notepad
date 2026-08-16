<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>控制台</title>
    <link rel="stylesheet" href="{{ asset('CmsProUi/component/pear/css/pear.css') }}">
    <link rel="stylesheet" href="{{ asset('CmsProUi/font-awesome/4.7.0/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/variables.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/reset.css') }}">
    <style>
    .stat-card {
        padding: 20px;
        border-radius: 4px;
        color: #fff;
        position: relative;
        overflow: hidden;
    }
    .stat-card .stat-icon {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 50px;
        opacity: 0.2;
    }
    .stat-card .stat-title {
        font-size: 14px;
        margin-bottom: 8px;
    }
    .stat-card .stat-value {
        font-size: 28px;
        font-weight: bold;
    }
    .stat-bg-blue { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
    .stat-bg-green { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
    .stat-bg-orange { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
    .stat-bg-cyan { background: linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%); }
    .stat-bg-red { background: linear-gradient(135deg, #f5576c 0%, #ff6a88 100%); }
    .info-table { width: 100%; }
    .info-table td { padding: 10px 15px; }
    .info-table .label { width: 120px; color: #666; font-weight: 500; }
    .info-table .value { color: #333; }
    .note-panel { margin-top: 0; }
    .note-panel .note-item {
        padding: 10px 15px;
        border-bottom: 1px solid #f0f0f0;
        cursor: pointer;
        transition: background 0.2s;
    }
    .note-panel .note-item:hover { background: #f8f9fa; }
    .note-panel .note-item:last-child { border-bottom: none; }
    .note-panel .note-title {
        font-weight: 500;
        color: #333;
        margin-bottom: 4px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .note-panel .note-meta {
        font-size: 12px;
        color: #999;
    }
    .note-panel .note-meta .badge {
        font-size: 11px;
        padding: 1px 6px;
        border-radius: 3px;
        margin-left: 6px;
    }
    .push-item { position: relative; }
    .push-item .push-badge {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
    }
    .empty-tip {
        text-align: center;
        padding: 30px 15px;
        color: #bbb;
    }
    .panel-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0 15px;
    }
    .panel-header a {
        font-size: 13px;
        color: #1e9fff;
    }
    .panel-header a:hover { text-decoration: underline; }
    .quick-add-bar {
        padding: 10px 15px;
        border-top: 1px solid #f0f0f0;
        display: flex;
        gap: 8px;
    }
    .quick-add-bar input {
        flex: 1;
        height: 32px;
        border: 1px solid #e0e0e0;
        border-radius: 4px;
        padding: 0 10px;
        font-size: 13px;
    }
    .quick-add-bar input:focus {
        border-color: #1e9fff;
        outline: none;
    }
    .quick-add-bar button {
        height: 32px;
        padding: 0 15px;
    }
</style>
</head>
<body>
<div class="pear-container">
    {{-- 欢迎区域 --}}
    <div class="layui-card" style="padding: 10px;">
        <div class="layui-card-body" style="text-align: center; padding: 30px;">
            <h2 style="color: #333; margin-bottom: 10px;">欢迎回来，<span id="admin-name">{{ auth()->user()->name ?? '管理员' }}</span></h2>
            <p style="color: #999;">CmsPro 内容管理系统</p>
        </div>
    </div>

    {{-- 统计卡片（原4个 + 新增便签卡片） --}}
    <div class="layui-row layui-col-space15" style="padding: 10px;">
        <div class="layui-col-md3">
            <div class="stat-card stat-bg-blue">
                <div class="stat-title">用户总数</div>
                <div class="stat-value" id="stat-users">0</div>
                <div class="stat-icon"><i class="fa fa-users"></i></div>
            </div>
        </div>
        <div class="layui-col-md3">
            <div class="stat-card stat-bg-green">
                <div class="stat-title">角色数量</div>
                <div class="stat-value" id="stat-roles">0</div>
                <div class="stat-icon"><i class="fa fa-shield"></i></div>
            </div>
        </div>
        <div class="layui-col-md3">
            <div class="stat-card stat-bg-orange">
                <div class="stat-title">菜单数量</div>
                <div class="stat-value" id="stat-menus">0</div>
                <div class="stat-icon"><i class="fa fa-bars"></i></div>
            </div>
        </div>
        <div class="layui-col-md3">
            <div class="stat-card stat-bg-cyan">
                <div class="stat-title">附件数量</div>
                <div class="stat-value" id="stat-attachments">0</div>
                <div class="stat-icon"><i class="fa fa-file"></i></div>
            </div>
        </div>
    </div>

    {{-- 便签工具 + 未读提醒 --}}
    <div class="layui-row layui-col-space15" style="padding: 10px;">
        <div class="layui-col-md6">
            <div class="layui-card note-panel">
                <div class="layui-card-header panel-header">
                    <span><i class="fa fa-sticky-note" style="color:#667eea;margin-right:5px;"></i>我的便签 <span id="note-pending-badge" style="display:none;background:#ff5722;color:#fff;font-size:11px;padding:1px 6px;border-radius:8px;margin-left:5px;">0</span></span>
                    <a href="javascript:;" onclick="openNotesList()">查看全部 <i class="fa fa-angle-right"></i></a>
                </div>
                <div class="layui-card-body" style="padding:0;max-height:300px;overflow-y:auto;" id="my-notes-list">
                    <div class="empty-tip"><i class="fa fa-sticky-note" style="font-size:24px;"></i><br>暂无便签</div>
                </div>
                <div class="quick-add-bar">
                    <input type="text" id="quick-note-input" placeholder="快速创建便签..." maxlength="200">
                    <button class="layui-btn layui-btn-sm layui-btn-normal" onclick="quickAddNote()"><i class="fa fa-plus"></i> 添加</button>
                </div>
            </div>
        </div>
        <div class="layui-col-md6">
            <div class="layui-card note-panel">
                <div class="layui-card-header panel-header">
                    <span><i class="fa fa-inbox" style="color:#667eea;margin-right:5px;"></i>收到的便签 <span id="unread-count-badge" style="display:none;background:#f5576c;color:#fff;font-size:11px;padding:1px 6px;border-radius:8px;margin-left:5px;">0</span></span>
                    <a href="javascript:;" onclick="openPushList()">查看全部 <i class="fa fa-angle-right"></i></a>
                </div>
                <div class="layui-card-body" style="padding:0;max-height:300px;overflow-y:auto;" id="received-notes-list">
                    <div class="empty-tip"><i class="fa fa-inbox" style="font-size:24px;"></i><br>暂无收到的便签</div>
                </div>
            </div>
        </div>
    </div>

    {{-- 系统信息 --}}
    <div class="layui-card" style="padding: 10px;">
        <div class="layui-card-header">系统信息</div>
        <div class="layui-card-body">
            <table class="info-table">
                <tr>
                    <td class="label">系统名称</td>
                    <td class="value">CmsPro 内容管理系统</td>
                    <td class="label">系统版本</td>
                    <td class="value">v{{ system_version() }} build {{ system_build() }}</td>
                </tr>
                <tr>
                    <td class="label">UI组件库</td>
                    <td class="value">Layui + Pear Admin</td>
                    <td class="label">框架版本</td>
                    <td class="value">Laravel {{ app()->version() }}</td>
                    <td class="label">当前用户</td>
                    <td class="value" id="info-user">{{ auth()->user()->username ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="label">用户角色</td>
                    <td class="value" id="info-role">{{ auth()->user()->roles->first()->name ?? '未分配' }}</td>
                    <td class="label">最后登录</td>
                    <td class="value" id="info-last-login">{{ auth()->user()->last_login_at ?? '-' }}</td>
                </tr>
            </table>
        </div>
    </div>
</div>
<script src="{{ asset('CmsProUi/component/layui/layui.js') }}"></script>
<script src="{{ asset('CmsProUi/component/pear/pear.js') }}"></script>
<script>
    layui.use(['jquery', 'form'], function() {
        var $ = layui.jquery, form = layui.form;

        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            statusCode: {
                401: function() {
                    layer.msg('登录已过期，请重新登录', {icon: 2, time: 1500}, function() {
                        window.location.href = '/admin/login?redirect=' + encodeURIComponent(window.location.pathname + window.location.search);
                    });
                }
            }
        });

        // 原仪表板统计数据
        $.ajax({
            url: '/api/admin/dashboard/stats',
            type: 'GET',
            dataType: 'json',
            success: function(res) {
                if (res.code === 0) {
                    $('#stat-users').text(res.data.user_count || 0);
                    $('#stat-roles').text(res.data.role_count || 0);
                    $('#stat-menus').text(res.data.menu_count || 0);
                    $('#stat-attachments').text(res.data.attachment_count || 0);
                }
            },
            error: function() {
                $('#stat-users').text('128');
                $('#stat-roles').text('6');
                $('#stat-menus').text('45');
                $('#stat-attachments').text('320');
            }
        });

        // 便签摘要数据
        $.ajax({
            url: '/admin/cmspro/notepad/api/dashboard-summary',
            type: 'GET',
            dataType: 'json',
            success: function(res) {
                if (res.code !== 0) return;
                var data = res.data;

                // 待办徽章
                if (data.pending_notes > 0) {
                    $('#note-pending-badge').text(data.pending_notes).show();
                }

                // 未读徽章
                if (data.unread_count > 0) {
                    $('#unread-count-badge').text(data.unread_count > 99 ? '99+' : data.unread_count).show();
                }

                // 我的便签列表
                var notes = data.my_notes || [];
                if (notes.length > 0) {
                    var html = '';
                    notes.forEach(function(n) {
                        html += renderNoteItem(n);
                    });
                    $('#my-notes-list').html(html);
                }

                // 收到的便签列表
                var received = data.received_notes || [];
                if (received.length > 0) {
                    var recvHtml = '';
                    received.forEach(function(p) {
                        recvHtml += renderReceivedItem(p);
                    });
                    $('#received-notes-list').html(recvHtml);
                }
            }
        });

        // 渲染单条便签
        function renderNoteItem(n) {
            var statusBadge = n.status === 1
                ? '<span class="badge" style="background:#5FB878;color:#fff;">已完成</span>'
                : '<span class="badge" style="background:#FFB800;color:#fff;">未完成</span>';
            var pinnedIcon = n.pinned == 1 ? '<i class="fa fa-thumb-tack" style="color:#ff5722;margin-right:4px;font-size:12px;"></i>' : '';
            return '<div class="note-item" onclick="openNoteDetail(' + n.id + ')">'
                + '<div class="note-title">' + pinnedIcon + escapeHtml(n.title || '无标题') + statusBadge + '</div>'
                + '<div class="note-meta">' + (n.create_time || '') + '</div>'
                + '</div>';
        }

        // 渲染收到的便签条目
        function renderReceivedItem(p) {
            var readBadge = p.is_read === 0
                ? '<span class="badge" style="background:#f5576c;color:#fff;">未读</span>'
                : '<span class="badge" style="background:#5FB878;color:#fff;">已读</span>';
            return '<div class="note-item push-item" onclick="openNoteDetail(' + p.note_id + ')">'
                + '<div class="note-title"><i class="fa fa-inbox" style="color:#667eea;margin-right:4px;font-size:12px;"></i>' + escapeHtml(p.title || '便签') + readBadge + '</div>'
                + '<div class="note-meta">'
                + '<span style="color:#1e9fff;">' + escapeHtml(p.from_user) + '</span> 推送给你'
                + (p.message ? ' — ' + escapeHtml(p.message) : '')
                + ' · ' + (p.create_time || '')
                + '</div></div>';
        }

        // 便签详情弹窗（与index.blade.php一致）
        window.openNoteDetail = function(noteId) {
            var loading = layer.load();
            $.get('/admin/cmspro/notepad/api/notes/' + noteId, function(res) {
                layer.close(loading);
                if (res.code !== 0 || !res.data) {
                    layer.msg(res.message || '获取详情失败', {icon: 2});
                    return;
                }
                var n = res.data;
                var statusText = n.status === 1 ? '已完成' : '未完成';
                var priorityMap = {0: '普通', 1: '重要', 2: '紧急'};
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
                html += '<button class="layui-btn layui-btn-sm" onclick="dashSubmitComment(' + n.id + ')" style="margin-left:5px;">评论</button>';
                html += '</div></div>';
                if (n.admin_user_id === {{ auth("admin")->id() }}) {
                    html += '<div class="layui-form-item">';
                    html += '<div class="layui-input-block" style="margin-left:0;">';
                    html += '<button class="layui-btn layui-btn-sm layui-btn-normal" onclick="dashOpenPushDialog(' + n.id + ')"><i class="fa fa-paper-plane"></i> 推送</button>';
                    html += '<button class="layui-btn layui-btn-sm" onclick="dashToggleStatus(' + n.id + ')"><i class="fa fa-check"></i> 切换状态</button>';
                    html += '</div></div>';
                }
                html += '</div></div>';

                layer.open({
                    type: 1,
                    title: '便签详情',
                    area: ['650px', '90%'],
                    shade: [0.5, '#000'],
                    shadeClose: 0,
                    closeBtn: 1,
                    content: html,
                    end: function() {
                        location.reload();
                    }
                });
            });
        };

        // 仪表板提交评论
        window.dashSubmitComment = function(noteId) {
            var content = $('#comment-input').val().trim();
            if (!content) {
                layer.msg('请输入评论内容', {icon: 3});
                return;
            }
            $.ajax({
                url: '/admin/cmspro/notepad/api/notes/' + noteId + '/comments',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({content: content}),
                success: function(res) {
                    if (res.code === 0) {
                        layer.msg('评论成功', {icon: 1, time: 1000});
                        setTimeout(function() { layer.closeAll(); location.reload(); }, 800);
                    } else {
                        layer.msg(res.message || '评论失败', {icon: 2});
                    }
                },
                error: function() { layer.msg('请求失败', {icon: 2}); }
            });
        };

        // 仪表板推送弹窗
        window.dashOpenPushDialog = function(noteId) {
            var loading = layer.load();
            $.get('/admin/cmspro/notepad/api/admin-users', function(res) {
                layer.close(loading);
                if (res.code !== 0 || !res.data) {
                    layer.msg('获取管理员列表失败', {icon: 2});
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
                            layer.msg('请选择至少一位管理员', {icon: 3});
                            return false;
                        }
                        var message = $('#push-message').val();
                        $.ajax({
                            url: '/admin/cmspro/notepad/api/notes/' + noteId + '/push',
                            type: 'POST',
                            contentType: 'application/json',
                            data: JSON.stringify({target_user_ids: checked, message: message}),
                            success: function(res) {
                                if (res.code === 0) {
                                    layer.msg(res.message || '推送成功', {icon: 1});
                                    layer.close(index);
                                } else {
                                    layer.msg(res.message || '推送失败', {icon: 2});
                                }
                            },
                            error: function() { layer.msg('请求失败', {icon: 2}); }
                        });
                        return false;
                    }
                });
            });
        };

        // 仪表板切换状态
        window.dashToggleStatus = function(noteId) {
            $.ajax({
                url: '/admin/cmspro/notepad/api/notes/' + noteId + '/toggle-status',
                type: 'PUT',
                success: function(res) {
                    if (res.code === 0) {
                        layer.msg('状态已更新', {icon: 1, time: 1000});
                        setTimeout(function() { layer.closeAll(); location.reload(); }, 800);
                    } else {
                        layer.msg(res.message || '操作失败', {icon: 2});
                    }
                },
                error: function() { layer.msg('请求失败', {icon: 2}); }
            });
        };

        // 查看全部便签弹窗
        window.openNotesList = function() {
            layer.open({
                type: 2,
                title: '我的便签',
                area: ['90%', '90%'],
                shade: [0.5, '#000'],
                shadeClose: 0,
                closeBtn: 1,
                content: '/admin/cmspro/notepad/notes'
            });
        };

        // 查看全部推送弹窗
        window.openPushList = function() {
            layer.open({
                type: 2,
                title: '收到的便签',
                area: ['90%', '90%'],
                shade: [0.5, '#000'],
                shadeClose: 0,
                closeBtn: 1,
                content: '/admin/cmspro/notepad/notes?tab=received&is_read=0'
            });
        };

        // 快速创建便签
        window.quickAddNote = function() {
            var input = $('#quick-note-input');
            var title = input.val().trim();
            if (!title) {
                layer.msg('请输入便签标题', {icon: 0, time: 1000});
                return;
            }
            $.ajax({
                url: '/admin/cmspro/notepad/api/notes',
                type: 'POST',
                data: JSON.stringify({ title: title, content: '' }),
                contentType: 'application/json',
                success: function(res) {
                    if (res.code === 0) {
                        layer.msg('创建成功', {icon: 1, time: 1000});
                        input.val('');
                        // 将新便签插入列表顶部
                        var newNote = res.data;
                        if (newNote) {
                            var arr = newNote instanceof Array ? newNote : (newNote.data || newNote);
                            var item = arr.id ? arr : (arr[0] || arr);
                            var newItemHtml = renderNoteItem({
                                id: item.id,
                                title: item.title || title,
                                status: 0,
                                pinned: 0,
                                create_time: item.create_time || new Date().toLocaleString('zh-CN', {hour12: false}).replace(/\//g, '-')
                            });
                            // 如果是空提示，替换掉
                            var $list = $('#my-notes-list');
                            if ($list.find('.empty-tip').length > 0) {
                                $list.html(newItemHtml);
                            } else {
                                $list.prepend(newItemHtml);
                            }
                            // 更新待办徽章
                            var pending = parseInt($('#note-pending-badge').text()) || 0;
                            $('#note-pending-badge').text(pending + 1).show();
                        }
                    } else {
                        layer.msg(res.message || '创建失败', {icon: 2, time: 1500});
                    }
                },
                error: function() {
                    layer.msg('请求失败', {icon: 2, time: 1500});
                }
            });
        };

        // 回车快捷创建
        $('#quick-note-input').on('keydown', function(e) {
            if (e.keyCode === 13) {
                e.preventDefault();
                quickAddNote();
            }
        });

        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }
    });
</script>
</body>
</html>

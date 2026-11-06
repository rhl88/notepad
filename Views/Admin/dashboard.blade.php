{{-- 记事本仪表盘扩展片段（非完整页面） --}}
{{-- 由 DashboardController::index() 渲染后插入框架 admin.dashboard： --}}
{{-- <script> 之前的样式与 HTML 插到"系统信息"卡片之前；脚本插到 </body> 之前（确保 layui.js/pear.js 已加载） --}}
{{-- 框架已提供：页面骨架、静态资源引入、CSRF/401 处理、统计卡片与统计 AJAX，此处不再重复 --}}
<style>
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

<script>
    layui.use(['jquery', 'form', 'layer'], function() {
        var $ = layui.jquery, form = layui.form, layer = layui.layer;
        @include('cmspro.notepad::Admin._js_base')

        // 空状态兜底 HTML，与面板初始标记保持一致，摘要为空时回写避免残留上一次的条目
        var EMPTY_MY_NOTES = '<div class="empty-tip">'
            + '<i class="fa fa-sticky-note" style="font-size:24px;"></i><br>暂无便签</div>';
        var EMPTY_RECEIVED = '<div class="empty-tip">'
            + '<i class="fa fa-inbox" style="font-size:24px;"></i><br>暂无收到的便签</div>';

        // 加载便签摘要：面板首次渲染与操作后的局部刷新都走这里，不做整页刷新
        function loadSummary() {
            $.ajax({
                url: NOTEPAD_BASE + '/api/dashboard-summary',
                type: 'GET',
                dataType: 'json',
                success: function(res) {
                    if (res.code !== 0) return;
                    var myNotes = res.data.my_notes || [];
                    var received = res.data.received_notes || [];
                    renderBadges(res.data);
                    renderNoteList('#my-notes-list', myNotes, renderNoteItem, EMPTY_MY_NOTES);
                    renderNoteList('#received-notes-list', received, renderReceivedItem, EMPTY_RECEIVED);
                },
                error: function(xhr) {
                    layer.msg(notepadAjaxErrorText(xhr, '便签摘要加载失败'), {icon: 2, time: 1500});
                }
            });
        }

        // 渲染徽章：数量归零时必须隐藏，否则会一直显示上一次的旧值
        function renderBadges(data) {
            var pending = parseInt(data.pending_notes, 10) || 0;
            var unread = parseInt(data.unread_count, 10) || 0;
            if (pending > 0) {
                $('#note-pending-badge').text(pending).show();
            } else {
                $('#note-pending-badge').text(0).hide();
            }
            if (unread > 0) {
                $('#unread-count-badge').text(unread > 99 ? '99+' : unread).show();
            } else {
                $('#unread-count-badge').text(0).hide();
            }
        }

        // 渲染列表：无数据时回写空状态兜底
        function renderNoteList(selector, rows, renderItem, emptyHtml) {
            if (!rows.length) {
                $(selector).html(emptyHtml);
                return;
            }
            var html = '';
            rows.forEach(function(row) {
                html += renderItem(row);
            });
            $(selector).html(html);
        }

        loadSummary();

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

        // 操作后的刷新回调（供详情弹窗片段调用）：局部重拉摘要，避免整页刷新导致闪烁
        function notepadRefresh() {
            loadSummary();
        }

        // 详情弹窗 / 评论 / 切换状态（共享片段，与便签列表页同源，
        // 依赖本作用域的 $ / layer / escapeHtml / NOTEPAD_BASE / notepadRefresh）
        @include('cmspro.notepad::Admin.notes._note_detail')

        // 推送弹窗（共享片段，与便签列表页同源，详情弹窗内的「推送」按钮调用 openPushDialog）
        @include('cmspro.notepad::Admin.notes._push_dialog', ['maxPushTargets' => $maxPushTargets])

        // 查看全部便签弹窗（子页面可能改动数据，关闭后局部重拉摘要）
        window.openNotesList = function() {
            layer.open({
                type: 2,
                title: '我的便签',
                area: ['90%', '90%'],
                shade: [0.5, '#000'],
                shadeClose: 0,
                closeBtn: 1,
                content: NOTEPAD_BASE + '/notes',
                end: function() { loadSummary(); }
            });
        };

        // 查看全部推送弹窗（同上，关闭后同步未读徽章与列表）
        window.openPushList = function() {
            layer.open({
                type: 2,
                title: '收到的便签',
                area: ['90%', '90%'],
                shade: [0.5, '#000'],
                shadeClose: 0,
                closeBtn: 1,
                content: NOTEPAD_BASE + '/notes?tab=received&is_read=0',
                end: function() { loadSummary(); }
            });
        };

        // 快速创建便签的进行中标记：避免连点按钮或连按回车重复创建同一标题的便签
        var quickAdding = false;

        // 快速创建便签
        window.quickAddNote = function() {
            if (quickAdding) {
                return;
            }

            var input = $('#quick-note-input');
            var title = input.val().trim();
            if (!title) {
                layer.msg('请输入便签标题', {icon: 0, time: 1000});
                return;
            }

            quickAdding = true;
            $.ajax({
                url: NOTEPAD_BASE + '/api/notes',
                type: 'POST',
                data: JSON.stringify({ title: title, content: '' }),
                contentType: 'application/json',
                success: function(res) {
                    if (res.code !== 0) {
                        layer.msg(res.message || '创建失败', {icon: 2, time: 1500});
                        return;
                    }
                    layer.msg('创建成功', {icon: 1, time: 1000});
                    input.val('');
                    // 统一走摘要接口重渲染：列表条目、时间格式与待办徽章均由后端下发，
                    // 不再前端拼 DOM 与本地时间，避免日期格式偏离 Y-m-d H:i:s 规范
                    loadSummary();
                },
                error: function(xhr) {
                    layer.msg(notepadAjaxErrorText(xhr, '创建失败'), {icon: 2, time: 1500});
                },
                complete: function() {
                    quickAdding = false;
                    // 焦点交回输入框，便于连续录入下一条
                    input.trigger('focus');
                }
            });
        };

        // 回车快捷创建（以 e.key 判断，e.keyCode 已被标准废弃）
        $('#quick-note-input').on('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                quickAddNote();
            }
        });

        // escapeHtml 由 _js_base 共享片段统一提供，此处不再重复定义
        // （原本地实现为弱版本：数字 0 会被吞成空串，且缺单引号转义）
    });
</script>

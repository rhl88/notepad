{{--
    便签详情弹窗及其配套操作（纯 JS 片段）

    由 notes/index.blade.php 与 dashboard.blade.php 在各自 layui.use 回调内 @include。
    依赖宿主作用域的 $ / layer / escapeHtml / NOTEPAD_BASE / notepadAjaxErrorText，以及宿主在
    include 之前定义的 notepadRefresh()（详情弹窗关闭、评论成功、切换状态后调用，用于刷新列表或面板）。

    对外暴露的全局函数（供弹窗内联 onclick 调用）：
        openNoteDetail(noteId)    打开详情弹窗
        submitComment(noteId)     提交评论
        toggleNoteStatus(noteId)  切换完成状态

    注意：详情弹窗的操作按钮依赖宿主同样引入的 _push_dialog 片段（openPushDialog）。
--}}
    // 当前登录管理员 ID：仅本人便签才显示「推送 / 切换状态」按钮
    var CURRENT_ADMIN_ID = parseInt('{{ auth("admin")->id() }}', 10);
    var PRIORITY_TEXT = { 0: '普通', 1: '重要', 2: '紧急' };

    /**
     * 标题单元格：颜色标记 + 置顶图标 + 标题文本
     *
     * 与列表页的颜色圆点、置顶图钉语义保持一致，但全部用内联样式，
     * 避免依赖宿主页面的 CSS（仪表盘面板没有这些类）。
     *
     * @param {Object} n 便签详情数据
     * @returns {string}
     */
    function buildNoteTitleCell(n) {
        var dot = n.color
            ? '<span title="标记颜色 ' + escapeHtml(n.color) + '" style="display:inline-block;'
                + 'width:10px;height:10px;border-radius:50%;margin-right:6px;'
                + 'background:' + escapeHtml(n.color) + ';"></span>'
            : '';
        var pinned = n.pinned == 1
            ? '<i class="fa fa-thumb-tack" title="已置顶" '
                + 'style="color:#ff5722;margin-right:4px;font-size:12px;"></i>'
            : '';

        return dot + pinned + escapeHtml(n.title);
    }

    /**
     * 便签基础信息表格
     *
     * @param {Object} n 便签详情数据
     * @returns {string}
     */
    function buildNoteInfoTable(n) {
        var label = '<td style="width:90px;font-weight:bold;">';
        var content = '<td><div style="white-space:pre-wrap;max-height:220px;overflow-y:auto;">';
        var html = '<table class="layui-table" lay-skin="nob">';
        html += '<tr>' + label + '标题</td><td>' + buildNoteTitleCell(n) + '</td></tr>';
        html += '<tr>' + label + '状态</td><td>'
            + (n.status === 1 ? '已完成' : '未完成') + '</td></tr>';
        html += '<tr>' + label + '优先级</td><td>'
            + (PRIORITY_TEXT[n.priority] || '普通') + '</td></tr>';
        html += '<tr>' + label + '内容</td>' + content
            + escapeHtml(n.content || '') + '</div></td></tr>';
        html += '<tr>' + label + '创建时间</td><td>' + (n.create_time || '-') + '</td></tr>';
        html += '<tr>' + label + '更新时间</td><td>' + (n.update_time || '-') + '</td></tr>';

        return html + '</table>';
    }

    /**
     * 评论列表区块（无评论时不渲染，避免空标题）
     *
     * @param {Object} n 便签详情数据
     * @returns {string}
     */
    function buildCommentBlock(n) {
        if (!n.comments || n.comments.length === 0) {
            return '';
        }
        var html = '<hr><h4>评论 (' + n.comments.length + ')</h4>';
        html += '<div style="max-height:150px;overflow-y:auto;">';
        n.comments.forEach(function (c) {
            var author = c.admin_user ? (c.admin_user.name || c.admin_user.username) : '未知';
            html += '<div style="padding:5px 0;border-bottom:1px dashed #eee;">';
            html += '<strong>' + escapeHtml(author) + '</strong> '
                + '<span style="color:#999;font-size:12px;">' + (c.create_time || '') + '</span>';
            html += '<div style="margin-top:3px;">' + escapeHtml(c.content) + '</div>';
            html += '</div>';
        });

        return html + '</div>';
    }

    /**
     * 推送记录区块（无记录时不渲染）
     *
     * @param {Object} n 便签详情数据
     * @returns {string}
     */
    function buildPushBlock(n) {
        if (!n.pushes || n.pushes.length === 0) {
            return '';
        }
        var html = '<hr><h4>推送记录 (' + n.pushes.length + ')</h4>';
        html += '<div style="max-height:100px;overflow-y:auto;">';
        n.pushes.forEach(function (p) {
            var toUser = p.to_user ? (p.to_user.name || p.to_user.username) : '未知';
            html += '<div style="padding:3px 0;"><span>' + escapeHtml(toUser) + '</span>';
            if (p.message) {
                html += ' <span style="color:#999;">- ' + escapeHtml(p.message) + '</span>';
            }
            html += ' <span style="color:#999;font-size:12px;">' + (p.create_time || '') + '</span></div>';
        });

        return html + '</div>';
    }

    /**
     * 评论输入框与操作按钮区块
     *
     * @param {Object} n 便签详情数据
     * @returns {string}
     */
    function buildNoteActionBar(n) {
        var html = '<hr><div class="layui-form" lay-filter="detailForm">';
        html += '<div class="layui-form-item"><div class="layui-input-block" style="margin-left:0;">';
        // maxlength 与 NoteController::COMMENT_MAX_LENGTH 同源；本片段被详情页与仪表盘两个宿主共用，
        // 宿主控制器不下发该常量，故此处按字面量书写，调整上限时须与后端验证规则一并修改
        html += '<input type="text" id="comment-input" placeholder="输入评论内容..." '
            + 'class="layui-input" maxlength="1000" style="display:inline-block;width:calc(100% - 80px);">';
        html += '<button class="layui-btn layui-btn-sm" onclick="submitComment(' + n.id + ')" '
            + 'style="margin-left:5px;">评论</button>';
        html += '</div></div>';
        if (n.admin_user_id === CURRENT_ADMIN_ID) {
            html += '<div class="layui-form-item"><div class="layui-input-block" style="margin-left:0;">';
            html += '<button class="layui-btn layui-btn-sm layui-btn-normal" '
                + 'onclick="openPushDialog(' + n.id + ')"><i class="fa fa-paper-plane"></i> 推送</button>';
            html += '<button class="layui-btn layui-btn-sm" onclick="toggleNoteStatus(' + n.id + ')">'
                + '<i class="fa fa-check"></i> 切换状态</button>';
            html += '</div></div>';
        }

        return html + '</div>';
    }

    /**
     * 组装完整的详情弹窗内容
     *
     * @param {Object} n 便签详情数据
     * @returns {string}
     */
    function buildNoteDetailHtml(n) {
        return '<div style="padding:15px 20px;">'
            + buildNoteInfoTable(n)
            + buildCommentBlock(n)
            + buildPushBlock(n)
            + buildNoteActionBar(n)
            + '</div>';
    }

    /**
     * 打开便签详情弹窗
     *
     * @param {number} noteId 便签 ID
     */
    window.openNoteDetail = function (noteId) {
        var loading = layer.load();

        // 遮罩统一在 always 中关闭：请求失败（如无权限 403、网络中断）时同样能收起，避免遮罩卡死
        $.get(NOTEPAD_BASE + '/api/notes/' + noteId)
            .done(function (res) {
                if (res.code !== 0 || !res.data) {
                    layer.msg(res.message || '获取详情失败', { icon: 2 });
                    return;
                }
                layer.open({
                    type: 1,
                    title: '便签详情',
                    area: ['650px', '90%'],
                    shade: [0.5, '#000'],
                    shadeClose: 0,
                    content: buildNoteDetailHtml(res.data),
                    end: function () {
                        notepadRefresh();
                    }
                });
            })
            .fail(function (xhr) {
                layer.msg(notepadAjaxErrorText(xhr, '获取详情失败'), { icon: 2 });
            })
            .always(function () {
                layer.close(loading);
            });
    };

    /**
     * 提交评论
     *
     * @param {number} noteId 便签 ID
     */
    window.submitComment = function (noteId) {
        var content = $('#comment-input').val().trim();
        if (!content) {
            layer.msg('请输入评论内容', { icon: 3 });
            return;
        }
        $.ajax({
            url: NOTEPAD_BASE + '/api/notes/' + noteId + '/comments',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ content: content }),
            success: function (res) {
                if (res.code !== 0) {
                    layer.msg(res.message || '评论失败', { icon: 2 });
                    return;
                }
                layer.msg('评论成功', { icon: 1, time: 1000 });
                setTimeout(function () { layer.closeAll(); notepadRefresh(); }, 800);
            },
            error: function (xhr) {
                layer.msg(notepadAjaxErrorText(xhr, '评论失败'), { icon: 2 });
            }
        });
    };

    /**
     * 切换便签完成状态
     *
     * @param {number} noteId 便签 ID
     */
    window.toggleNoteStatus = function (noteId) {
        $.ajax({
            url: NOTEPAD_BASE + '/api/notes/' + noteId + '/toggle-status',
            type: 'PUT',
            success: function (res) {
                if (res.code !== 0) {
                    layer.msg(res.message || '操作失败', { icon: 2 });
                    return;
                }
                layer.msg(res.message || '状态已更新', { icon: 1, time: 1000 });
                setTimeout(function () { layer.closeAll(); notepadRefresh(); }, 800);
            },
            error: function (xhr) {
                layer.msg(notepadAjaxErrorText(xhr, '操作失败'), { icon: 2 });
            }
        });
    };

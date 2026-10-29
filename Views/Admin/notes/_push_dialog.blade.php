{{--
    推送便签弹窗（共享片段）

    由 notes/index.blade.php 与 dashboard.blade.php 在各自 layui.use 回调内 @include，
    依赖宿主作用域中的 $ / form / layer / escapeHtml，并由宿主控制器下发 $maxPushTargets。
    调用方式：openPushDialog(noteId)
--}}
    // 单次推送人数上限，与后端 NoteService::pushNote 校验同源（配置项 max_push_targets）
    var MAX_PUSH_TARGETS = parseInt('{{ $maxPushTargets }}', 10);

    /**
     * 刷新推送弹窗「已选 / 上限」计数
     *
     * Layui 复选框的勾选状态由框架接管，只能经 form.on('checkbox(...)') 感知变化，
     * 因此这里以 DOM 实时统计为准，弹窗未打开时直接跳过。
     */
    function updatePushSelectedCount() {
        var $count = $('#push-selected-count');
        if ($count.length === 0) {
            return;
        }
        var selected = $('input[name="target_users"]:checked').length;
        var over = selected > MAX_PUSH_TARGETS;
        $count.text('已选 ' + selected + ' / ' + MAX_PUSH_TARGETS + ' 人');
        // 片段样式全部内联（宿主 dashboard 按首个 <script 拆分页面，外挂 CSS 易被错位），超限态用 css 切换
        $count.css({ color: over ? '#ff5722' : '#666', fontWeight: over ? 'bold' : 'normal' });
    }

    form.on('checkbox(pushUser)', updatePushSelectedCount);

    // 推送对象搜索：管理员较多时按姓名 / 账号前端过滤，隐藏项的勾选状态保持不变
    $(document).on('input', '#push-user-search', function () {
        var keyword = $(this).val().trim().toLowerCase();
        $('.push-user-item').each(function () {
            var text = String($(this).attr('data-keyword') || '');
            $(this).toggle(keyword === '' || text.indexOf(keyword) !== -1);
        });
    });

    /**
     * 打开推送弹窗
     *
     * @param {number} noteId 待推送的便签 ID
     */
    window.openPushDialog = function (noteId) {
        var loading = layer.load();

        $.get(NOTEPAD_BASE + '/api/admin-users', function (res) {
            layer.close(loading);
            if (res.code !== 0 || !res.data) {
                layer.msg('获取管理员列表失败', { icon: 2 });
                return;
            }
            layer.open({
                type: 1,
                title: '推送便签',
                area: ['500px', '470px'],
                shade: [0.5, '#000'],
                shadeClose: 0,
                content: buildPushDialogHtml(res.data),
                btn: ['推送', '取消'],
                success: function () {
                    form.render('checkbox');
                    updatePushSelectedCount();
                },
                btn1: function (index) {
                    return submitPush(index, noteId);
                }
            });
        });
    };

    /**
     * 拼装推送弹窗内容
     *
     * @param {Array} users 可选管理员列表
     * @returns {string} 弹窗 HTML
     */
    function buildPushDialogHtml(users) {
        var html = '<div style="padding:15px 20px;">';
        html += '<form class="layui-form" lay-filter="pushForm">';
        html += '<div class="layui-form-item">';
        html += '<label class="layui-form-label">搜索</label>';
        html += '<div class="layui-input-block">';
        html += '<input type="text" id="push-user-search" class="layui-input" '
            + 'placeholder="按姓名或账号筛选" autocomplete="off">';
        html += '</div></div>';
        html += '<div class="layui-form-item">';
        html += '<label class="layui-form-label">选择管理员</label>';
        html += '<div class="layui-input-block">';
        html += '<span id="push-selected-count" style="display:inline-block;font-size:12px;color:#666;'
            + 'line-height:20px;">已选 0 / ' + MAX_PUSH_TARGETS + ' 人</span>';
        html += '<div class="push-user-list" style="max-height:150px;overflow-y:auto;'
            + 'border:1px solid #e6e6e6;padding:5px;">';
        users.forEach(function (u) {
            var displayName = u.name ? u.name + ' (' + u.username + ')' : u.username;
            var keyword = ((u.name || '') + ' ' + (u.username || '')).toLowerCase();
            html += '<div class="push-user-item" data-keyword="' + escapeHtml(keyword) + '">';
            html += '<input type="checkbox" name="target_users" lay-filter="pushUser" value="' + u.id
                + '" title="' + escapeHtml(displayName) + '">';
            html += '</div>';
        });
        if (users.length === 0) {
            html += '<div style="padding:12px;text-align:center;color:#999;font-size:13px;">暂无可推送的管理员</div>';
        }
        html += '</div></div></div>';
        html += '<div class="layui-form-item"><label class="layui-form-label">推送留言</label>';
        html += '<div class="layui-input-block">';
        html += '<textarea id="push-message" class="layui-textarea" '
            + 'placeholder="可选推送留言（最多500字）" maxlength="500"></textarea>';
        html += '</div></div>';
        html += '</form></div>';

        return html;
    }

    /**
     * 提交推送
     *
     * @param {number} index  弹窗索引
     * @param {number} noteId 便签 ID
     * @returns {boolean} 恒为 false，阻止 layer 自动关闭弹窗（成功后由回调显式关闭）
     */
    function submitPush(index, noteId) {
        var checked = [];
        $('input[name="target_users"]:checked').each(function () {
            checked.push(parseInt($(this).val(), 10));
        });

        if (checked.length === 0) {
            layer.msg('请选择至少一位管理员', { icon: 3 });
            return false;
        }
        if (checked.length > MAX_PUSH_TARGETS) {
            layer.msg('最多推送 ' + MAX_PUSH_TARGETS + ' 人，请减少选择', { icon: 2 });
            return false;
        }

        $.ajax({
            url: NOTEPAD_BASE + '/api/notes/' + noteId + '/push',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ target_user_ids: checked, message: $('#push-message').val() }),
            success: function (res) {
                if (res.code === 0) {
                    layer.msg(res.message || '推送成功', { icon: 1 });
                    layer.close(index);
                } else {
                    layer.msg(res.message || '推送失败', { icon: 2 });
                }
            },
            error: function () { layer.msg('请求失败', { icon: 2 }); }
        });

        return false;
    }

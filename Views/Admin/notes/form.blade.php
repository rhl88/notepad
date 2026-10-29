<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>便签表单</title>
    <link rel="stylesheet" href="{{ asset('CmsProUi/component/pear/css/pear.css') }}">
    <link rel="stylesheet" href="{{ asset('CmsProUi/font-awesome/4.7.0/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/variables.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/reset.css') }}">
    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            overflow: hidden;
        }
        *, *::before, *::after {
            box-sizing: border-box;
        }
        .layui-form {
            height: 100%;
        }
        /* 内容区：总高度减去底部按钮栏高度，内容超出时自身滚动 */
        .layui-form > .layui-row {
            height: calc(100vh - 52px);
            overflow-y: auto;
            padding: 15px;
            margin: 0;
        }
        /* 底部按钮栏：固定 52px 高度，不加 padding，内边距由 .button-container 控制 */
        .bottom {
            height: 52px;
            background: #fff;
            border-top: 1px solid #e6e6e6;
            text-align: right;
        }
        .bottom .button-container {
            display: inline-block;
            line-height: 52px;
            padding-right: 15px;
        }
        .bottom .button-container button {
            margin-left: 10px;
        }
        /* 表单标签单行显示，防止长标签换行；超长内容向左溢出，不覆盖 Layui 默认 width */
        .layui-form-label {
            white-space: nowrap;
        }
        .color-picker {
            display: inline-block;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            cursor: pointer;
            margin-right: 6px;
            border: 2px solid transparent;
            vertical-align: middle;
        }
        .color-picker.active {
            border-color: #333;
        }
        /* 字数统计：右对齐显示在输入框下方，达到上限时高亮提醒 */
        .char-count {
            display: block;
            text-align: right;
            font-size: 12px;
            line-height: 18px;
            color: #999;
        }
        .char-count.over {
            color: #ff5722;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <form class="layui-form" lay-filter="noteForm">
        <input type="hidden" id="id" name="id" value="">
        {{-- 滚动内容区 --}}
        <div class="layui-row">
            <div class="layui-col-md12">
                <div class="layui-card-body layui-row">
                    <div class="layui-col-md12">
                        <div class="layui-form-item">
                            <label class="layui-form-label">标题</label>
                            <div class="layui-input-block">
                                <input type="text" id="title" name="title" lay-verify="required" lay-reqtext="请输入标题" placeholder="请输入便签标题" class="layui-input" maxlength="200" autocomplete="off">
                                <span class="char-count" id="title-count">0 / 200</span>
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">优先级</label>
                            <div class="layui-input-block">
                                <select id="priority" name="priority" lay-filter="priority">
                                    <option value="0">普通</option>
                                    <option value="1">重要</option>
                                    <option value="2">紧急</option>
                                </select>
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">标记颜色</label>
                            <div class="layui-input-block">
                                <input type="hidden" id="color" name="color" value="">
                                <span class="color-picker active" style="background:#e6e6e6;" data-color="" onclick="selectColor(this)"></span>
                                <span class="color-picker" style="background:#ff5722;" data-color="#ff5722" onclick="selectColor(this)"></span>
                                <span class="color-picker" style="background:#ff9800;" data-color="#ff9800" onclick="selectColor(this)"></span>
                                <span class="color-picker" style="background:#4caf50;" data-color="#4caf50" onclick="selectColor(this)"></span>
                                <span class="color-picker" style="background:#2196f3;" data-color="#2196f3" onclick="selectColor(this)"></span>
                                <span class="color-picker" style="background:#9c27b0;" data-color="#9c27b0" onclick="selectColor(this)"></span>
                            </div>
                        </div>
                        <div class="layui-form-item">
                            <label class="layui-form-label">置顶</label>
                            <div class="layui-input-block">
                                <input type="checkbox" id="pinned" name="pinned" lay-skin="switch" lay-text="是|否" value="1">
                            </div>
                        </div>
                        <div class="layui-form-item layui-form-text">
                            <label class="layui-form-label">内容</label>
                            <div class="layui-input-block">
                                <textarea id="content" name="content" placeholder="请输入便签内容" class="layui-textarea" style="height:180px;" maxlength="{{ $maxContentLength }}"></textarea>
                                <span class="char-count" id="content-count">0 / {{ $maxContentLength }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        {{-- 固定底部按钮栏 --}}
        <div class="bottom">
            <div class="button-container">
                <button type="submit" id="btn-submit" class="layui-btn layui-btn-normal layui-btn-sm" lay-submit lay-filter="save">
                    <i class="layui-icon layui-icon-ok"></i> 提交
                </button>
                <button type="button" id="btn-reset" class="layui-btn layui-btn-primary layui-btn-sm">
                    <i class="layui-icon layui-icon-refresh"></i> 清空
                </button>
            </div>
        </div>
    </form>

    <script src="{{ asset('CmsProUi/component/layui/layui.js') }}"></script>
    <script src="{{ asset('CmsProUi/component/pear/pear.js') }}"></script>
    <script>
    layui.use(['form', 'jquery', 'layer'], function () {
        var form = layui.form, $ = layui.jquery, layer = layui.layer;
        @include('cmspro.notepad::Admin._js_base')

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

        /**
         * 解析待编辑的便签 ID
         *
         * 规范入口为 /notes/{id}/edit（挂 edit 权限），同时兼容 ?id= 形式，避免旧入口失效。
         */
        function resolveEditId() {
            var matched = window.location.pathname.match(/\/notes\/(\d+)\/edit$/);
            if (matched) {
                return matched[1];
            }
            return new URLSearchParams(window.location.search).get('id');
        }

        var editId = resolveEditId();
        var isEdit = !!editId;

        // 标题上限与后端 max:200 校验一致；内容上限由后端按配置项 max_content_length 下发
        var TITLE_MAX = 200;
        var CONTENT_MAX = parseInt('{{ $maxContentLength }}', 10);

        /**
         * 绑定字数统计
         *
         * @returns {Function} 计数刷新函数，供数据回填、清空后重新计数
         */
        function bindCharCount(inputSelector, countSelector, max) {
            var $input = $(inputSelector);
            var $count = $(countSelector);

            function refresh() {
                var len = $input.val().length;
                $count.text(len + ' / ' + max);
                $count.toggleClass('over', len >= max);
            }

            $input.on('input propertychange', refresh);
            refresh();

            return refresh;
        }

        var refreshTitleCount = bindCharCount('#title', '#title-count', TITLE_MAX);
        var refreshContentCount = bindCharCount('#content', '#content-count', CONTENT_MAX);

        // 表单字段快照，用于判断是否存在未保存改动
        function fieldSnapshot() {
            return [$('#title').val(), $('#content').val(), $('#color').val(),
                $('#priority').val(), $('#pinned').prop('checked') ? '1' : '0'].join('|');
        }

        var savedSnapshot = fieldSnapshot();

        // 暴露给父页：iframe 被 layer 关闭时不触发 beforeunload，须由父页关闭前主动查询
        window.isFormDirty = function () {
            return fieldSnapshot() !== savedSnapshot;
        };

        // 提交按钮禁用态：Layui 按钮需附加 disabled 类才有置灰视觉
        function setSubmitDisabled(disabled) {
            $('#btn-submit').prop('disabled', disabled).toggleClass('layui-btn-disabled', disabled);
        }

        /**
         * 将便签数据写入表单
         *
         * Layui 的 select / switch 需先设值再调用 form.render() 才会同步渲染态。
         */
        function applyNoteData(d) {
            $('#id').val(d.id);
            $('#title').val(d.title || '');
            $('#content').val(d.content || '');
            $('#color').val(d.color || '');
            $('#priority').val(d.priority !== undefined ? String(d.priority) : '0');
            $('#pinned').prop('checked', d.pinned == 1);
            $('.color-picker').removeClass('active');
            $('.color-picker[data-color="' + (d.color || '') + '"]').addClass('active');
            form.render();
            refreshTitleCount();
            refreshContentCount();
            // 回填结果即为「已保存」基准，此后与基准不同才算未保存改动
            savedSnapshot = fieldSnapshot();
        }

        /**
         * 拉取并回填待编辑便签
         *
         * 回填期间遮罩页面并禁用提交，避免用户抢先输入的内容被回填数据覆盖。
         */
        function loadNoteForEdit() {
            var loading = layer.load(1, { shade: [0.3, '#000'] });
            setSubmitDisabled(true);

            $.ajax({
                url: NOTEPAD_BASE + '/api/notes/' + editId,
                type: 'GET',
                dataType: 'json',
                success: function (res) {
                    if (res.code === 0 && res.data) {
                        applyNoteData(res.data);
                    } else {
                        layer.msg(res.message || '获取数据失败', { icon: 2, time: 1500 });
                    }
                },
                error: function () { layer.msg('请求失败', { icon: 2, time: 1500 }); },
                complete: function () {
                    layer.close(loading);
                    setSubmitDisabled(false);
                }
            });
        }

        // 编辑模式下「清空」会丢弃已回填数据，且与直接关闭弹窗语义重叠，故隐藏
        if (isEdit) {
            $('#btn-reset').hide();
            loadNoteForEdit();
        }

        // 颜色选择
        window.selectColor = function(el) {
            document.querySelectorAll('.color-picker').forEach(function(e) { e.classList.remove('active'); });
            el.classList.add('active');
            document.getElementById('color').value = el.dataset.color;
        };

        // 清空表单：原生 reset 无法恢复 Layui 渲染后的 select/switch 状态，改为手动清值再重渲染
        $('#btn-reset').on('click', function () {
            $('#id').val('');
            $('#title').val('');
            $('#content').val('');
            $('#color').val('');
            $('#priority').val('0');
            $('#pinned').prop('checked', false);
            $('.color-picker').removeClass('active');
            $('.color-picker[data-color=""]').addClass('active');
            form.render();
            refreshTitleCount();
            refreshContentCount();
        });

        // 提交
        form.on('submit(save)', function (data) {
            var url = isEdit ? NOTEPAD_BASE + '/api/notes/' + editId : NOTEPAD_BASE + '/api/notes';
            var method = isEdit ? 'PUT' : 'POST';
            data.field.pinned = data.field.pinned ? 1 : 0;
            $.ajax({
                url: url,
                type: method,
                dataType: 'json',
                contentType: 'application/json',
                data: JSON.stringify(data.field),
                success: function (res) {
                    if (res.code === 0) {
                        // 已保存成功，同步基准值，避免提示期间用户点关闭仍被追问「未保存改动」
                        savedSnapshot = fieldSnapshot();
                        layer.msg(isEdit ? '编辑成功' : '新增成功', { icon: 1, time: 1000 }, function () {
                            var index = parent.layer.getFrameIndex(window.name);
                            parent.layer.close(index);
                            // 父页 layui 模块未暴露为全局变量，须经 parent.layui 取 table 后刷新列表
                            parent.layui.table.reload('note-table');
                        });
                    } else {
                        layer.msg(res.message || '操作失败', { icon: 2, time: 1500 });
                    }
                },
                error: function () { layer.msg('请求失败', { icon: 2, time: 1500 }); }
            });
            return false;
        });
    });
    </script>
</body>
</html>

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
                                <textarea id="content" name="content" placeholder="请输入便签内容" class="layui-textarea" style="height:180px;"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        {{-- 固定底部按钮栏 --}}
        <div class="bottom">
            <div class="button-container">
                <button type="submit" class="layui-btn layui-btn-normal layui-btn-sm" lay-submit lay-filter="save">
                    <i class="layui-icon layui-icon-ok"></i> 提交
                </button>
                <button type="reset" class="layui-btn layui-btn-primary layui-btn-sm">
                    <i class="layui-icon layui-icon-refresh"></i> 清空
                </button>
            </div>
        </div>
    </form>

    <script src="{{ asset('CmsProUi/component/layui/layui.js') }}"></script>
    <script src="{{ asset('CmsProUi/component/pear/pear.js') }}"></script>
    <script>
    layui.use(['form', 'jquery'], function () {
        var form = layui.form, $ = layui.jquery;

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

        var editId = new URLSearchParams(window.location.search).get('id');
        var isEdit = !!editId;

        // 编辑：回填数据
        if (isEdit) {
            $.ajax({
                url: '/admin/cmspro/notepad/api/notes/' + editId,
                type: 'GET',
                dataType: 'json',
                success: function (res) {
                    if (res.code === 0 && res.data) {
                        var d = res.data;
                        $('#id').val(d.id);
                        $('#title').val(d.title || '');
                        $('#content').val(d.content || '');
                        $('#color').val(d.color || '');
                        // Layui select 回填：先设值再渲染
                        $('#priority').val(d.priority !== undefined ? String(d.priority) : '0');
                        if (d.pinned == 1) {
                            $('#pinned').prop('checked', true);
                        }
                        // 回填颜色选择器
                        if (d.color) {
                            $('.color-picker').removeClass('active');
                            $('.color-picker[data-color="' + d.color + '"]').addClass('active');
                        }
                        form.render();
                    } else {
                        layer.msg(res.message || '获取数据失败', { icon: 2, time: 1500 });
                    }
                },
                error: function () { layer.msg('请求失败', { icon: 2, time: 1500 }); }
            });
        }

        // 颜色选择
        window.selectColor = function(el) {
            document.querySelectorAll('.color-picker').forEach(function(e) { e.classList.remove('active'); });
            el.classList.add('active');
            document.getElementById('color').value = el.dataset.color;
        };

        // 提交
        form.on('submit(save)', function (data) {
            var url = isEdit ? '/admin/cmspro/notepad/api/notes/' + editId : '/admin/cmspro/notepad/api/notes';
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
                        layer.msg(isEdit ? '编辑成功' : '新增成功', { icon: 1, time: 1000 }, function () {
                            var index = parent.layer.getFrameIndex(window.name);
                            parent.layer.close(index);
                            parent.table.reload('note-table');
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

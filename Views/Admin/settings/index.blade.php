<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>记事本设置</title>
    <link rel="stylesheet" href="{{ asset('CmsProUi/component/pear/css/pear.css') }}">
    <link rel="stylesheet" href="{{ asset('CmsProUi/font-awesome/4.7.0/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/variables.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/reset.css') }}">
</head>
<style>
    .layui-form-label {
        width: unset;
    }
</style>
<body>
<div class="pear-container">
    <div class="layui-card">
        <div class="layui-card-body">
            <form class="layui-form" lay-filter="settingForm" id="setting-form">
                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                <div id="setting-fields"></div>
                <div class="layui-form-item" style="margin-top:20px;">
                    <div class="layui-input-block">
                        <button class="layui-btn" lay-submit lay-filter="saveSettings"><i class="fa fa-save"></i> 保存设置</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="{{ asset('CmsProUi/component/layui/layui.js') }}"></script>
<script src="{{ asset('CmsProUi/component/pear/pear.js') }}"></script>
<script>
layui.use(['form', 'jquery', 'layer'], function() {
    var form = layui.form;
    var $ = layui.jquery;
    var layer = layui.layer;
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

    // 加载设置
    $.get(NOTEPAD_BASE + '/api/settings', function(res) {
        if (res.code !== 0 || !res.data) {
            layer.msg('获取设置失败', { icon: 2 });
            return;
        }

        var groups = res.data;
        var html = '';
        groups.forEach(function(group) {
            html += '<fieldset class="layui-elem-field"><legend>' + escapeHtml(group.name) + '</legend><div class="layui-field-box" style="padding:10px 15px;">';
            if (group.items) {
                group.items.forEach(function(item) {
                    html += renderField(item);
                });
            }
            html += '</div></fieldset>';
        });
        $('#setting-fields').html(html);
        form.render();
    });

    function renderField(item) {
        var html = '<div class="layui-form-item">';
        html += '<label class="layui-form-label">' + escapeHtml(item.name) + '</label>';
        html += '<div class="layui-input-block">';

        if (item.type === 'switch') {
            html += '<input type="checkbox" name="' + escapeHtml(item.code) + '" lay-skin="switch" lay-text="开|关" ' + (item.value == '1' ? 'checked' : '') + ' value="1">';
        } else if (item.type === 'number') {
            html += '<input type="number" name="' + escapeHtml(item.code) + '" value="' + escapeHtml(item.value || '') + '" class="layui-input" style="max-width:200px;">';
        } else if (item.type === 'textarea') {
            html += '<textarea name="' + escapeHtml(item.code) + '" class="layui-textarea">' + escapeHtml(item.value || '') + '</textarea>';
        } else {
            html += '<input type="text" name="' + escapeHtml(item.code) + '" value="' + escapeHtml(item.value || '') + '" class="layui-input">';
        }

        if (item.tips) {
            html += '<div class="layui-form-mid layui-word-aux" style="margin-left:10px;">' + escapeHtml(item.tips) + '</div>';
        }
        html += '</div></div>';
        return html;
    }

    // 保存设置
    form.on('submit(saveSettings)', function(data) {
        var formData = {};
        $(data.form).find('input[type!=checkbox],textarea,select').each(function() {
            var name = $(this).attr('name');
            if (name && name !== '_token') {
                formData[name] = $(this).val();
            }
        });
        // 处理switch
        $(data.form).find('input[type=checkbox][lay-skin=switch]').each(function() {
            var name = $(this).attr('name');
            if (name && name !== '_token') {
                formData[name] = $(this).prop('checked') ? '1' : '0';
            }
        });

        // 构建items对象：去掉前缀，只保留配置项name部分
        var items = {};
        for (var key in formData) {
            if (formData.hasOwnProperty(key)) {
                // key格式: app_cmspro_notepad_xxx，提取xxx
                var shortKey = key.replace('app_cmspro_notepad_', '');
                items[shortKey] = formData[key];
            }
        }

        var loading = layer.load();
        $.ajax({
            url: NOTEPAD_BASE + '/api/settings',
            type: 'PUT',
            contentType: 'application/json',
            data: JSON.stringify({ items: items }),
            success: function(res) {
                layer.close(loading);
                if (res.code === 0) {
                    layer.msg('保存成功', { icon: 1 });
                } else {
                    layer.msg(res.message || '保存失败', { icon: 2 });
                }
            },
            error: function() {
                layer.close(loading);
                layer.msg('请求失败', { icon: 2 });
            }
        });
        return false;
    });

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
});
</script>
</body>
</html>

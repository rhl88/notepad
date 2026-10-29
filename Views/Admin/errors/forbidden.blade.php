<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>无访问权限</title>
    <link rel="stylesheet" href="{{ asset('CmsProUi/component/pear/css/pear.css') }}">
    <link rel="stylesheet" href="{{ asset('CmsProUi/font-awesome/4.7.0/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/admin.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/variables.css') }}">
    <link rel="stylesheet" href="{{ asset('Admin/css/reset.css') }}">
    <style>
        .notepad-forbidden-container {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 400px;
            padding: 40px 20px;
        }
        .notepad-forbidden-card {
            text-align: center;
            max-width: 420px;
        }
        .notepad-forbidden-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #FF5722 0%, #E64A19 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }
        .notepad-forbidden-icon i {
            font-size: 36px;
            color: #fff;
        }
        .notepad-forbidden-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            margin-bottom: 12px;
        }
        .notepad-forbidden-desc {
            font-size: 14px;
            color: #999;
            line-height: 1.6;
            margin-bottom: 24px;
        }
        .notepad-forbidden-actions a {
            margin: 0 6px;
        }
    </style>
</head>
<body>
<div class="pear-container">
    <div class="layui-card" style="padding: 10px;">
        <div class="layui-card-body">
            <div class="notepad-forbidden-container">
                <div class="notepad-forbidden-card">
                    <div class="notepad-forbidden-icon">
                        <i class="fa fa-lock"></i>
                    </div>
                    <div class="notepad-forbidden-title">无访问权限</div>
                    <div class="notepad-forbidden-desc">{{ $message }}</div>
                    <div class="notepad-forbidden-actions">
                        <a href="{{ url('/admin/dashboard') }}" class="layui-btn layui-btn-primary">
                            <i class="fa fa-home"></i> 返回后台首页
                        </a>
                        <a href="javascript:history.back();" class="layui-btn layui-btn-normal">
                            <i class="fa fa-reply"></i> 返回上一页
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>

<?php
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/CSRF.php';
require_once __DIR__ . '/../../lang/zh.php';
require_once __DIR__ . '/../../ui/css.php';
require_once __DIR__ . '/../../ui/js.php';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>修改密码 - <?php echo Lang::get('title'); ?></title>
    <?php echo getCSS(); ?>
    <style>
        td input {
            width: 260px;
            border: 1px solid #ccc;
            padding: 4px;
        }
        .alert-required {
            border: 2px solid #f90;
            background: #fffae5;
            color: #660;
            padding: 10px;
            margin: 10px 0;
            font-weight: bold;
        }
    </style>
</head>
<body>
<div class="header">
    <div class="wrap">
        <table>
            <tr>
                <td><strong><?php echo Lang::get('title'); ?></strong></td>
                <td style="text-align: right;">
                    <?php if (!$required): ?>
                        <a href="?action=dashboard" class="btn"><?php echo Lang::get('dashboard'); ?></a>
                    <?php endif; ?>
                    <a href="?action=logout" class="btn btn-danger"><?php echo Lang::get('logout'); ?></a>
                </td>
            </tr>
        </table>
    </div>
</div>
<div class="wrap">
    <form method="POST" class="form">
        <?php echo CSRF::field(); ?>
        <table>
            <thead>
                <tr>
                    <th colspan="2">修改密码</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($required): ?>
                <tr>
                    <td colspan="2">
                        <div class="alert-required">
                            ⚠️ 您正在使用默认密码，为了安全起见，请立即修改密码！
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <tr>
                    <td colspan="2">
                        <div class="alert"><?php echo htmlspecialchars($error); ?></div>
                    </td>
                </tr>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <tr>
                    <td colspan="2">
                        <div class="alert-success">密码修改成功！</div>
                    </td>
                </tr>
                <?php endif; ?>
                
                <?php if (!empty($_SESSION['flash'])): ?>
                    <?php $flash = $_SESSION['flash']; unset($_SESSION['flash']); ?>
                    <tr>
                        <td colspan="2">
                            <div class="alert<?php echo $flash['type'] === 'success' ? ' alert-success' : ''; ?>">
                                <?php echo htmlspecialchars($flash['message']); ?>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                
                <?php if ($required): ?>
                <tr>
                    <th>当前密码（默认密码）:</th>
                    <td>
                        <input type="password" name="old_password" placeholder="请输入默认密码: admin" required autofocus>
                        <div class="note">首次登录请使用默认密码: admin</div>
                    </td>
                </tr>
                <?php else: ?>
                <tr>
                    <th>当前密码:</th>
                    <td>
                        <input type="password" name="old_password" placeholder="请输入当前密码" required autofocus>
                    </td>
                </tr>
                <?php endif; ?>
                
                <tr>
                    <th>新密码:</th>
                    <td>
                        <input type="password" name="new_password" placeholder="请输入新密码（至少8个字符）" required>
                        <div class="note">密码长度至少需要 8 个字符</div>
                    </td>
                </tr>
                
                <tr>
                    <th>确认新密码:</th>
                    <td>
                        <input type="password" name="confirm_password" placeholder="请再次输入新密码" required>
                    </td>
                </tr>
                
                <tr>
                    <td></td>
                    <td>
                        <button type="submit" class="btn">保存</button>
                        <?php if (!$required): ?>
                            <a href="?action=dashboard" class="btn">取消</a>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </form>
</div>
<?php echo getJS(); ?>
</body>
</html>


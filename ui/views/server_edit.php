<?php
require_once __DIR__ . '/../css.php';
require_once __DIR__ . '/../js.php';
require_once __DIR__ . '/../../lang/zh.php';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $server ? '编辑服务器' : '添加服务器'; ?> - <?php echo Lang::get('title'); ?></title>
    <?php echo getCSS(); ?>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo $server ? '编辑服务器' : '添加服务器'; ?></h1>
            <div class="nav">
                <a href="?action=servers" class="btn"><?php echo Lang::get('servers'); ?></a>
                <a href="?action=dashboard" class="btn"><?php echo Lang::get('dashboard'); ?></a>
                <a href="?action=logout" class="btn btn-danger"><?php echo Lang::get('logout'); ?></a>
            </div>
        </div>

        <div class="card">
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label><?php echo Lang::get('server_name'); ?></label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($server['name'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label><?php echo Lang::get('host'); ?></label>
                    <input type="text" name="host" value="<?php echo htmlspecialchars($server['host'] ?? ''); ?>" 
                           placeholder="192.168.1.100" required>
                </div>

                <div class="form-group">
                    <label><?php echo Lang::get('port'); ?></label>
                    <input type="number" name="port" value="<?php echo htmlspecialchars($server['port'] ?? '22'); ?>" required>
                </div>

                <div class="form-group">
                    <label><?php echo Lang::get('username'); ?></label>
                    <input type="text" name="username" value="<?php echo htmlspecialchars($server['username'] ?? ''); ?>" 
                           placeholder="root" required>
                </div>

                <div class="form-group">
                    <label><?php echo Lang::get('key_path'); ?></label>
                    <input type="text" name="key_path" value="<?php echo htmlspecialchars($server['key_path'] ?? ''); ?>" 
                           placeholder="/home/user/.ssh/id_rsa">
                    <small style="color: #777;">或上传密钥文件</small>
                </div>

                <div class="form-group">
                    <label>上传 SSH 密钥文件（可选）</label>
                    <input type="file" name="key_file" accept="*/*">
                    <small style="color: #777;">如果填写了密钥路径，则优先使用路径</small>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-success"><?php echo Lang::get('save'); ?></button>
                    <a href="?action=servers" class="btn"><?php echo Lang::get('cancel'); ?></a>
                </div>
            </form>
        </div>
    </div>
    <?php echo getJS(); ?>
</body>
</html>


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
    <title><?php echo Lang::get('servers'); ?> - <?php echo Lang::get('title'); ?></title>
    <?php echo getCSS(); ?>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo Lang::get('servers'); ?></h1>
            <div class="nav">
                <a href="?action=dashboard" class="btn"><?php echo Lang::get('dashboard'); ?></a>
                <a href="?action=projects" class="btn"><?php echo Lang::get('projects'); ?></a>
                <a href="?action=servers" class="btn"><?php echo Lang::get('servers'); ?></a>
                <a href="?action=deployments" class="btn"><?php echo Lang::get('deployments'); ?></a>
                <a href="?action=logout" class="btn btn-danger"><?php echo Lang::get('logout'); ?></a>
            </div>
        </div>

        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2><?php echo Lang::get('servers'); ?></h2>
                <a href="?action=server_edit" class="btn btn-success">添加服务器</a>
            </div>

            <?php if (empty($servers)): ?>
                <p>暂无服务器，<a href="?action=server_edit">添加服务器</a></p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th><?php echo Lang::get('server_name'); ?></th>
                            <th><?php echo Lang::get('host'); ?></th>
                            <th><?php echo Lang::get('port'); ?></th>
                            <th><?php echo Lang::get('username'); ?></th>
                            <th><?php echo Lang::get('actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($servers as $server): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($server['name']); ?></td>
                                <td><?php echo htmlspecialchars($server['host']); ?></td>
                                <td><?php echo htmlspecialchars($server['port']); ?></td>
                                <td><?php echo htmlspecialchars($server['username']); ?></td>
                                <td>
                                    <div class="actions">
                                        <a href="?action=server_edit&id=<?php echo $server['id']; ?>" 
                                           class="btn btn-small"><?php echo Lang::get('edit'); ?></a>
                                        <a href="?action=server_delete&id=<?php echo $server['id']; ?>" 
                                           class="btn btn-danger btn-small"
                                           onclick="return confirmDelete('确定要删除此服务器吗？')"><?php echo Lang::get('delete'); ?></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <?php echo getJS(); ?>
</body>
</html>


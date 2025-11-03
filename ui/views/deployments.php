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
    <title><?php echo Lang::get('deployments'); ?> - <?php echo Lang::get('title'); ?></title>
    <?php echo getCSS(); ?>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo Lang::get('deployments'); ?></h1>
            <div class="nav">
                <a href="?action=dashboard" class="btn"><?php echo Lang::get('dashboard'); ?></a>
                <a href="?action=projects" class="btn"><?php echo Lang::get('projects'); ?></a>
                <a href="?action=servers" class="btn"><?php echo Lang::get('servers'); ?></a>
                <a href="?action=deployments" class="btn"><?php echo Lang::get('deployments'); ?></a>
                <a href="?action=logout" class="btn btn-danger"><?php echo Lang::get('logout'); ?></a>
            </div>
        </div>

        <div class="card">
            <h2><?php echo Lang::get('deployments'); ?></h2>

            <?php if (empty($deployments)): ?>
                <p>暂无部署记录</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th><?php echo Lang::get('project_name'); ?></th>
                            <th><?php echo Lang::get('branch'); ?></th>
                            <th><?php echo Lang::get('commit_hash'); ?></th>
                            <th><?php echo Lang::get('status'); ?></th>
                            <th><?php echo Lang::get('started_at'); ?></th>
                            <th><?php echo Lang::get('finished_at'); ?></th>
                            <th><?php echo Lang::get('actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deployments as $deployment): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($deployment['project_name']); ?></td>
                                <td><?php echo htmlspecialchars($deployment['branch']); ?></td>
                                <td>
                                    <code style="font-size: 11px;">
                                        <?php echo htmlspecialchars(substr($deployment['commit_hash'] ?? '', 0, 7)); ?>
                                    </code>
                                </td>
                                <td>
                                    <span class="badge badge-<?php 
                                        echo $deployment['status'] === 'success' ? 'success' : 
                                            ($deployment['status'] === 'failed' ? 'danger' : 'warning'); 
                                    ?>">
                                        <?php echo Lang::get($deployment['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($deployment['started_at']); ?></td>
                                <td><?php echo htmlspecialchars($deployment['finished_at'] ?? '-'); ?></td>
                                <td>
                                    <button class="btn btn-small" 
                                            onclick="showLog(<?php echo $deployment['id']; ?>)">
                                        查看日志
                                    </button>
                                </td>
                            </tr>
                            <tr id="log-row-<?php echo $deployment['id']; ?>" style="display: none;">
                                <td colspan="7">
                                    <div class="log-output" id="log-content-<?php echo $deployment['id']; ?>">
                                        加载中...
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
    <script>
    function showLog(deploymentId) {
        const row = document.getElementById('log-row-' + deploymentId);
        const content = document.getElementById('log-content-' + deploymentId);
        
        if (row.style.display === 'none') {
            row.style.display = '';
            fetch('?action=api&endpoint=deploy_log&deployment_id=' + deploymentId)
                .then(res => res.json())
                .then(data => {
                    content.textContent = data.output || data.error || '暂无日志';
                });
        } else {
            row.style.display = 'none';
        }
    }
    </script>
</body>
</html>


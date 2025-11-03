<?php
require_once __DIR__ . '/../css.php';
require_once __DIR__ . '/../js.php';
require_once __DIR__ . '/../../lang/zh.php';
require_once __DIR__ . '/../../core/Auth.php';

$auth = new Auth();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo Lang::get('dashboard'); ?> - <?php echo Lang::get('title'); ?></title>
    <?php echo getCSS(); ?>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo Lang::get('title'); ?></h1>
            <div class="nav">
                <a href="?action=dashboard" class="btn"><?php echo Lang::get('dashboard'); ?></a>
                <a href="?action=projects" class="btn"><?php echo Lang::get('projects'); ?></a>
                <a href="?action=servers" class="btn"><?php echo Lang::get('servers'); ?></a>
                <a href="?action=deployments" class="btn"><?php echo Lang::get('deployments'); ?></a>
                <a href="?action=logout" class="btn btn-danger"><?php echo Lang::get('logout'); ?></a>
            </div>
        </div>

        <div class="stats">
            <div class="stat-card">
                <h3>总项目数</h3>
                <div class="value"><?php echo count($projects); ?></div>
            </div>
            <div class="stat-card">
                <h3>最近部署</h3>
                <div class="value"><?php echo count($deployments); ?></div>
            </div>
        </div>

        <div class="card">
            <h2>最近项目</h2>
            <?php if (empty($projects)): ?>
                <p>暂无项目，<a href="?action=project_edit">添加项目</a></p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th><?php echo Lang::get('project_name'); ?></th>
                            <th><?php echo Lang::get('branch'); ?></th>
                            <th><?php echo Lang::get('actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projects as $project): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($project['name']); ?></td>
                                <td><?php echo htmlspecialchars($project['branch']); ?></td>
                                <td>
                                    <div class="actions">
                                        <a href="?action=deploy&id=<?php echo $project['id']; ?>&branch=<?php echo urlencode($project['branch']); ?>" 
                                           class="btn btn-success btn-small"
                                           onclick="return confirm('确定要部署吗？')"><?php echo Lang::get('deploy'); ?></a>
                                        <a href="?action=project_edit&id=<?php echo $project['id']; ?>" 
                                           class="btn btn-small"><?php echo Lang::get('edit'); ?></a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>最近部署</h2>
            <?php if (empty($deployments)): ?>
                <p>暂无部署记录</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th><?php echo Lang::get('project_name'); ?></th>
                            <th><?php echo Lang::get('branch'); ?></th>
                            <th><?php echo Lang::get('status'); ?></th>
                            <th><?php echo Lang::get('started_at'); ?></th>
                            <th><?php echo Lang::get('actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deployments as $deployment): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($deployment['project_name']); ?></td>
                                <td><?php echo htmlspecialchars($deployment['branch']); ?></td>
                                <td>
                                    <span class="badge badge-<?php 
                                        echo $deployment['status'] === 'success' ? 'success' : 
                                            ($deployment['status'] === 'failed' ? 'danger' : 'warning'); 
                                    ?>">
                                        <?php echo Lang::get($deployment['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($deployment['started_at']); ?></td>
                                <td>
                                    <a href="?action=deployments&project_id=<?php echo $deployment['project_id']; ?>" 
                                       class="btn btn-small"><?php echo Lang::get('view'); ?></a>
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


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
    <title><?php echo $project ? Lang::get('edit_project') : Lang::get('add_project'); ?> - <?php echo Lang::get('title'); ?></title>
    <?php echo getCSS(); ?>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo $project ? Lang::get('edit_project') : Lang::get('add_project'); ?></h1>
            <div class="nav">
                <a href="?action=projects" class="btn"><?php echo Lang::get('projects'); ?></a>
                <a href="?action=dashboard" class="btn"><?php echo Lang::get('dashboard'); ?></a>
                <a href="?action=logout" class="btn btn-danger"><?php echo Lang::get('logout'); ?></a>
            </div>
        </div>

        <div class="card">
            <form method="POST">
                <div class="form-group">
                    <label><?php echo Lang::get('project_name'); ?></label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($project['name'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label><?php echo Lang::get('repo_url'); ?></label>
                    <input type="text" name="repo_url" value="<?php echo htmlspecialchars($project['repo_url'] ?? ''); ?>" 
                           placeholder="https://github.com/user/repo.git" required>
                </div>

                <div class="form-group">
                    <label><?php echo Lang::get('branch'); ?></label>
                    <input type="text" name="branch" value="<?php echo htmlspecialchars($project['branch'] ?? 'master'); ?>" required>
                </div>

                <div class="form-group">
                    <label><?php echo Lang::get('deploy_path'); ?></label>
                    <input type="text" name="deploy_path" value="<?php echo htmlspecialchars($project['deploy_path'] ?? ''); ?>" 
                           placeholder="/var/www/project" required>
                </div>

                <div class="form-group">
                    <label><?php echo Lang::get('server'); ?></label>
                    <select name="server_id" required>
                        <option value="">请选择服务器</option>
                        <?php foreach ($servers as $server): ?>
                            <option value="<?php echo $server['id']; ?>" 
                                    <?php echo ($project['server_id'] ?? '') == $server['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($server['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>部署前脚本（可选）</label>
                    <textarea name="pre_deploy_script" placeholder="部署前执行的命令，例如：echo 'Starting deployment'"><?php echo htmlspecialchars($project['pre_deploy_script'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label>部署后脚本（可选）</label>
                    <textarea name="post_deploy_script" placeholder="部署后执行的命令，例如：composer install"><?php echo htmlspecialchars($project['post_deploy_script'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="webhook_enabled" value="1" 
                               <?php echo ($project['webhook_enabled'] ?? 0) ? 'checked' : ''; ?>>
                        启用 Webhook 自动部署
                    </label>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-success"><?php echo Lang::get('save'); ?></button>
                    <a href="?action=projects" class="btn"><?php echo Lang::get('cancel'); ?></a>
                </div>
            </form>
        </div>
    </div>
    <?php echo getJS(); ?>
</body>
</html>


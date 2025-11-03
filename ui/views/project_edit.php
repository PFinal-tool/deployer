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
    <style>td input,td select,td textarea{width:420px;border:1px solid #ccc;padding:4px} textarea{min-height:90px}</style>
</head>
<body>
<div class="header"><div class="wrap">
  <table><tr>
    <td><strong><?php echo $project ? Lang::get('edit_project') : Lang::get('add_project'); ?></strong></td>
    <td style="text-align:right">
      <a href="?action=projects" class="btn"><?php echo Lang::get('projects'); ?></a>
      <a href="?action=dashboard" class="btn"><?php echo Lang::get('dashboard'); ?></a>
      <a href="?action=logout" class="btn btn-danger"><?php echo Lang::get('logout'); ?></a>
    </td>
  </tr></table>
</div></div>

<div class="wrap">
  <form method="POST" class="form">
    <table>
      <thead><tr><th colspan="2"><?php echo $project ? Lang::get('edit_project') : Lang::get('add_project'); ?></th></tr></thead>
      <tbody>
        <tr><th><?php echo Lang::get('project_name'); ?></th><td><input type="text" name="name" value="<?php echo htmlspecialchars($project['name'] ?? ''); ?>" required></td></tr>
        <tr><th><?php echo Lang::get('repo_url'); ?></th><td><input type="text" name="repo_url" value="<?php echo htmlspecialchars($project['repo_url'] ?? ''); ?>" placeholder="https://github.com/user/repo.git" required></td></tr>
        <tr><th><?php echo Lang::get('branch'); ?></th><td><input type="text" name="branch" value="<?php echo htmlspecialchars($project['branch'] ?? 'master'); ?>" required></td></tr>
        <tr><th><?php echo Lang::get('deploy_path'); ?></th><td><input type="text" name="deploy_path" value="<?php echo htmlspecialchars($project['deploy_path'] ?? ''); ?>" placeholder="/var/www/project" required></td></tr>
        <tr>
          <th><?php echo Lang::get('server'); ?></th>
          <td>
            <select name="server_id" required>
              <option value="">请选择服务器</option>
              <?php foreach ($servers as $server): ?>
                <option value="<?php echo $server['id']; ?>" <?php echo ($project['server_id'] ?? '') == $server['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($server['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </td>
        </tr>
        <tr><th>部署前脚本（可选）</th><td><textarea name="pre_deploy_script" placeholder="部署前执行的命令，例如：echo 'Starting deployment'"><?php echo htmlspecialchars($project['pre_deploy_script'] ?? ''); ?></textarea></td></tr>
        <tr><th>部署后脚本（可选）</th><td><textarea name="post_deploy_script" placeholder="部署后执行的命令，例如：composer install"><?php echo htmlspecialchars($project['post_deploy_script'] ?? ''); ?></textarea></td></tr>
        <tr><th>Webhook</th><td><label><input type="checkbox" name="webhook_enabled" value="1" <?php echo ($project['webhook_enabled'] ?? 0) ? 'checked' : ''; ?>> 启用 Webhook 自动部署</label></td></tr>
        <tr><td></td><td><button type="submit" class="btn btn-small"><?php echo Lang::get('save'); ?></button> <a href="?action=projects" class="btn btn-small"><?php echo Lang::get('cancel'); ?></a></td></tr>
      </tbody>
    </table>
  </form>
</div>

<?php echo getJS(); ?>
</body>
</html>


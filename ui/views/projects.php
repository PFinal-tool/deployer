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
    <title><?php echo Lang::get('projects'); ?> - <?php echo Lang::get('title'); ?></title>
    <?php echo getCSS(); ?>
</head>
<body>
<div class="header"><div class="wrap">
  <table><tr>
    <td><strong><?php echo Lang::get('projects'); ?></strong></td>
    <td style="text-align:right">
      <a href="?action=dashboard" class="btn"><?php echo Lang::get('dashboard'); ?></a>
      <a href="?action=projects" class="btn"><?php echo Lang::get('projects'); ?></a>
      <a href="?action=servers" class="btn"><?php echo Lang::get('servers'); ?></a>
      <a href="?action=deployments" class="btn"><?php echo Lang::get('deployments'); ?></a>
      <a href="?action=logout" class="btn btn-danger"><?php echo Lang::get('logout'); ?></a>
    </td>
  </tr></table>
</div></div>

<div class="wrap">
  <table>
    <thead>
      <tr>
        <th><?php echo Lang::get('projects'); ?></th>
        <th style="text-align:right"><a href="?action=project_edit" class="btn btn-small"><?php echo Lang::get('add_project'); ?></a></th>
      </tr>
    </thead>
  </table>

  <table>
    <thead>
      <tr>
        <th><?php echo Lang::get('project_name'); ?></th>
        <th><?php echo Lang::get('repo_url'); ?></th>
        <th><?php echo Lang::get('branch'); ?></th>
        <th><?php echo Lang::get('server'); ?></th>
        <th><?php echo Lang::get('actions'); ?></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($projects)): ?>
        <tr><td colspan="5">暂无项目，<a href="?action=project_edit">添加项目</a></td></tr>
      <?php else: foreach ($projects as $project): ?>
        <tr>
          <td><?php echo htmlspecialchars($project['name']); ?></td>
          <td><?php echo htmlspecialchars($project['repo_url']); ?></td>
          <td><?php echo htmlspecialchars($project['branch']); ?></td>
          <td><?php echo htmlspecialchars($project['server_name'] ?? '-'); ?></td>
          <td>
            <a href="?action=deploy&id=<?php echo $project['id']; ?>&branch=<?php echo urlencode($project['branch']); ?>" class="btn btn-small" onclick="return confirm('确定要部署吗？')"><?php echo Lang::get('deploy'); ?></a>
            <a href="?action=project_edit&id=<?php echo $project['id']; ?>" class="btn btn-small"><?php echo Lang::get('edit'); ?></a>
            <a href="?action=project_delete&id=<?php echo $project['id']; ?>" class="btn btn-small btn-danger" onclick="return confirmDelete('确定要删除此项目吗？')"><?php echo Lang::get('delete'); ?></a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php echo getJS(); ?>
</body>
</html>


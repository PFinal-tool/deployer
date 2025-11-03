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
<div class="header"><div class="wrap">
  <table><tr>
    <td><strong><?php echo Lang::get('servers'); ?></strong></td>
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
  <?php if (!empty($_SESSION['flash'])): $f=$_SESSION['flash']; unset($_SESSION['flash']); ?>
    <div class="alert<?php echo ($f['type'] ?? '') === 'success' ? ' alert-success' : ''; ?>"><?php echo htmlspecialchars($f['message'] ?? ''); ?></div>
  <?php endif; ?>

  <table>
    <thead>
      <tr>
        <th><?php echo Lang::get('servers'); ?></th>
        <th style="text-align:right"><a href="?action=server_edit" class="btn btn-small">添加服务器</a></th>
      </tr>
    </thead>
  </table>

  <table>
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
      <?php if (empty($servers)): ?>
        <tr><td colspan="5">暂无服务器，<a href="?action=server_edit">添加服务器</a></td></tr>
      <?php else: foreach ($servers as $server): ?>
        <tr>
          <td><?php echo htmlspecialchars($server['name']); ?></td>
          <td><?php echo htmlspecialchars($server['host']); ?></td>
          <td><?php echo htmlspecialchars($server['port']); ?></td>
          <td><?php echo htmlspecialchars($server['username']); ?></td>
          <td>
            <a href="?action=server_test&id=<?php echo $server['id']; ?>" class="btn btn-small">测试连接</a>
            <a href="?action=server_edit&id=<?php echo $server['id']; ?>" class="btn btn-small"><?php echo Lang::get('edit'); ?></a>
            <a href="?action=server_delete&id=<?php echo $server['id']; ?>" class="btn btn-small btn-danger" onclick="return confirmDelete('确定要删除此服务器吗？')"><?php echo Lang::get('delete'); ?></a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php echo getJS(); ?>
</body>
</html>


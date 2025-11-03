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
    <style>td input{width:360px;border:1px solid #ccc;padding:4px}</style>
</head>
<body>
<div class="header"><div class="wrap">
  <table><tr>
    <td><strong><?php echo $server ? '编辑服务器' : '添加服务器'; ?></strong></td>
    <td style="text-align:right">
      <a href="?action=servers" class="btn"><?php echo Lang::get('servers'); ?></a>
      <a href="?action=dashboard" class="btn"><?php echo Lang::get('dashboard'); ?></a>
      <a href="?action=logout" class="btn btn-danger"><?php echo Lang::get('logout'); ?></a>
    </td>
  </tr></table>
</div></div>

<div class="wrap">
  <?php if (!empty($_SESSION['flash'])): $f=$_SESSION['flash']; unset($_SESSION['flash']); ?>
    <div class="alert<?php echo ($f['type'] ?? '') === 'success' ? ' alert-success' : ''; ?>"><?php echo htmlspecialchars($f['message'] ?? ''); ?></div>
  <?php endif; ?>
  <form method="POST" enctype="multipart/form-data" class="form">
    <table>
      <thead><tr><th colspan="2"><?php echo $server ? '编辑服务器' : '添加服务器'; ?></th></tr></thead>
      <tbody>
        <tr><th><?php echo Lang::get('server_name'); ?></th><td><input type="text" name="name" value="<?php echo htmlspecialchars($server['name'] ?? ''); ?>" required></td></tr>
        <tr><th><?php echo Lang::get('host'); ?></th><td><input type="text" name="host" value="<?php echo htmlspecialchars($server['host'] ?? ''); ?>" placeholder="192.168.1.100" required></td></tr>
        <tr><th><?php echo Lang::get('port'); ?></th><td><input type="number" name="port" value="<?php echo htmlspecialchars($server['port'] ?? '22'); ?>" required></td></tr>
        <tr><th><?php echo Lang::get('username'); ?></th><td><input type="text" name="username" value="<?php echo htmlspecialchars($server['username'] ?? ''); ?>" placeholder="root" required></td></tr>
        <tr><th><?php echo Lang::get('key_path'); ?></th><td><input type="text" name="key_path" value="<?php echo htmlspecialchars($server['key_path'] ?? ''); ?>" placeholder="/home/user/.ssh/id_rsa"><div class="note">方式一：使用 SSH 密钥认证（推荐，更安全）</div></td></tr>
        <tr><th>上传 SSH 密钥文件（可选）</th><td><input type="file" name="key_file" accept="*/*"><div class="note">如果填写了密钥路径或上传密钥文件，将使用密钥认证</div></td></tr>
        <tr><th>密码（可选）</th><td><input type="password" name="password" value="" placeholder="不填写则保持不变"><div class="note">方式二：使用账号密码认证（如果填写了密码，将忽略密钥，使用密码认证。需要安装 sshpass：Ubuntu/Debian: apt-get install sshpass, CentOS: yum install sshpass, macOS: brew install sshpass）</div></td></tr>
        <tr><td></td><td>
          <button type="submit" class="btn btn-small"><?php echo Lang::get('save'); ?></button>
          <a href="?action=servers" class="btn btn-small"><?php echo Lang::get('cancel'); ?></a>
          <?php if (!empty($server['id'])): ?>
            <a href="?action=server_test&id=<?php echo (int)$server['id']; ?>" class="btn btn-small">测试连接</a>
          <?php endif; ?>
        </td></tr>
      </tbody>
    </table>
  </form>
</div>

<?php echo getJS(); ?>
</body>
</html>


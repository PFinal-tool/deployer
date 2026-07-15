<?php
$title = $server ? '编辑服务器' : '添加服务器';
ui_page_open($title, 'servers');
ui_sshpass_alert();
ui_flash();
ui_panel_open($title);
?>
<form method="POST" enctype="multipart/form-data">
<?php echo CSRF::field(); ?>
<table class="tbl form-tbl" width="100%" cellpadding="0" cellspacing="0">
  <tr><th><?php echo h(Lang::get('server_name')); ?></th><td><input type="text" name="name" value="<?php echo h($server['name'] ?? ''); ?>" required></td></tr>
  <tr><th><?php echo h(Lang::get('host')); ?></th><td><input type="text" name="host" value="<?php echo h($server['host'] ?? ''); ?>" placeholder="192.168.1.100" required></td></tr>
  <tr><th><?php echo h(Lang::get('port')); ?></th><td><input type="number" name="port" value="<?php echo h($server['port'] ?? '22'); ?>" required></td></tr>
  <tr><th><?php echo h(Lang::get('username')); ?></th><td><input type="text" name="username" value="<?php echo h($server['username'] ?? ''); ?>" placeholder="root" required></td></tr>
  <tr><th><?php echo h(Lang::get('key_path')); ?></th><td><input type="text" name="key_path" value="<?php echo h($server['key_path'] ?? ''); ?>" placeholder="/home/user/.ssh/id_rsa"><div class="note">推荐：SSH 密钥路径</div></td></tr>
  <tr><th>上传密钥</th><td><input type="file" name="key_file"><div class="note">或直接上传私钥文件</div></td></tr>
  <tr><th>密码</th><td><input type="password" name="password" placeholder="留空保持不变"><div class="note">填写密码后将使用密码认证（需本机安装 sshpass）。未安装时可设置环境变量 DEPLOYER_SSHPASS_PATH</div></td></tr>
  <tr>
    <td></td>
    <td>
      <button type="submit" class="btn btn-primary"><?php echo h(Lang::get('save')); ?></button>
      <a class="btn" href="?action=servers"><?php echo h(Lang::get('cancel')); ?></a>
      <?php if (!empty($server['id'])): ?>
      <?php ui_post_action('server_test', (int)$server['id'], '测试连接'); ?>
      <?php endif; ?>
    </td>
  </tr>
</table>
</form>
<?php ui_panel_close(); ui_page_close(); ?>

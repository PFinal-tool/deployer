<?php
$title = $project ? Lang::get('edit_project') : Lang::get('add_project');
ui_page_open($title, 'projects');
ui_flash();
ui_panel_open($title);
?>
<form method="POST">
<?php echo CSRF::field(); ?>
<table class="tbl form-tbl" width="100%" cellpadding="0" cellspacing="0">
  <tr><th><?php echo h(Lang::get('project_name')); ?></th><td><input type="text" name="name" value="<?php echo h($project['name'] ?? ''); ?>" required></td></tr>
  <tr><th><?php echo h(Lang::get('repo_url')); ?></th><td><input type="text" name="repo_url" value="<?php echo h($project['repo_url'] ?? ''); ?>" placeholder="https://github.com/user/repo.git" required></td></tr>
  <tr><th>Git 用户名</th><td><input type="text" name="git_username" value="<?php echo h($project['git_username'] ?? ''); ?>"><div class="note">HTTPS 私有仓库认证用户名（可选）</div></td></tr>
  <tr><th>Git 密码</th><td><input type="password" name="git_password" placeholder="<?php echo $project ? '留空保留原密码' : ''; ?>"><div class="note">HTTPS 私有仓库密码或 Token</div></td></tr>
  <tr><th><?php echo h(Lang::get('branch')); ?></th><td><input type="text" name="branch" value="<?php echo h($project['branch'] ?? 'master'); ?>" required></td></tr>
  <tr><th><?php echo h(Lang::get('deploy_path')); ?></th><td><input type="text" name="deploy_path" value="<?php echo h($project['deploy_path'] ?? ''); ?>" placeholder="/var/www/project" required></td></tr>
  <tr>
    <th><?php echo h(Lang::get('server')); ?></th>
    <td>
      <select name="server_id" required>
        <option value="">请选择服务器</option>
        <?php foreach ($servers as $s): ?>
        <option value="<?php echo (int)$s['id']; ?>" <?php echo ($project['server_id'] ?? '') == $s['id'] ? 'selected' : ''; ?>><?php echo h($s['name']); ?></option>
        <?php endforeach; ?>
      </select>
    </td>
  </tr>
  <tr><th>部署前脚本</th><td><textarea name="pre_deploy_script" placeholder="部署前执行的 shell 命令"><?php echo h($project['pre_deploy_script'] ?? ''); ?></textarea></td></tr>
  <tr><th>部署后脚本</th><td><textarea name="post_deploy_script" placeholder="部署后执行的 shell 命令"><?php echo h($project['post_deploy_script'] ?? ''); ?></textarea></td></tr>
  <tr><th>Webhook</th><td>
    <label><input type="checkbox" name="webhook_enabled" value="1" <?php echo ($project['webhook_enabled'] ?? 0) ? 'checked' : ''; ?>> 启用 Webhook 自动部署</label>
    <?php if (!empty($webhookUrl)): ?>
    <div class="note" style="margin-top:8px">Webhook URL（配置到 Git 平台，需签名或 Token 头）：<br>
      <input type="text" readonly class="mono" style="width:100%;max-width:520px;margin-top:4px" value="<?php echo h($webhookUrl); ?>" onclick="this.select()">
    </div>
    <?php endif; ?>
  </td></tr>
  <tr>
    <td></td>
    <td>
      <button type="submit" class="btn btn-primary"><?php echo h(Lang::get('save')); ?></button>
      <a class="btn" href="?action=projects"><?php echo h(Lang::get('cancel')); ?></a>
    </td>
  </tr>
</table>
</form>
<?php ui_panel_close(); ui_page_close(); ?>

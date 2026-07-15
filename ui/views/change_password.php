<?php ui_page_open('修改密码', ''); ui_flash(); ?>
<?php ui_panel_open('修改密码'); ?>
<form method="POST">
<?php echo CSRF::field(); ?>
<table class="tbl form-tbl" width="100%" cellpadding="0" cellspacing="0">
  <?php if (!empty($required)): ?>
  <tr><td colspan="2"><div class="flash flash-err">您正在使用默认密码，请立即修改。</div></td></tr>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
  <tr><td colspan="2"><div class="flash flash-err"><?php echo h($error); ?></div></td></tr>
  <?php endif; ?>
  <?php if (!empty($success)): ?>
  <tr><td colspan="2"><div class="flash flash-ok">密码修改成功</div></td></tr>
  <?php endif; ?>
  <tr>
    <th>当前密码</th>
    <td><input type="password" name="old_password" required<?php echo (fn_is_dev() && !empty($required)) ? ' placeholder="admin"' : ''; ?>></td>
  </tr>
  <tr>
    <th>新密码</th>
    <td><input type="password" name="new_password" required><div class="note">至少 8 个字符</div></td>
  </tr>
  <tr>
    <th>确认密码</th>
    <td><input type="password" name="confirm_password" required></td>
  </tr>
  <tr>
    <td></td>
    <td><button type="submit" class="btn btn-primary">保存</button></td>
  </tr>
</table>
</form>
<?php ui_panel_close(); ui_page_close(); ?>

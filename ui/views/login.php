<?php ui_login_page_open(); ?>
<form method="POST">
<?php echo CSRF::field(); ?>
<table class="panel form-tbl tbl" width="100%" cellpadding="0" cellspacing="0">
  <tr><th colspan="2" style="text-align:center;background:#fff;border-bottom:1px solid #e2e8f0"><?php echo h(Lang::get('login')); ?></th></tr>
  <?php if (!empty($error)): ?>
  <tr><td colspan="2"><div class="flash flash-err"><?php echo h($error); ?></div></td></tr>
  <?php endif; ?>
  <tr>
    <th><?php echo h(Lang::get('username')); ?></th>
    <td><input type="text" name="username" required autofocus></td>
  </tr>
  <tr>
    <th><?php echo h(Lang::get('password')); ?></th>
    <td><input type="password" name="password" required></td>
  </tr>
  <tr>
    <td></td>
    <td><button type="submit" class="btn btn-primary"><?php echo h(Lang::get('login')); ?></button></td>
  </tr>
</table>
</form>
<?php ui_login_page_close(); ?>

<?php
ui_page_open(Lang::get('servers'), 'servers');
ui_sshpass_alert();
ui_flash();
ui_panel_open(Lang::get('servers'), '?action=server_edit', '添加服务器');
?>
<table class="tbl" width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <th><?php echo h(Lang::get('server_name')); ?></th>
    <th><?php echo h(Lang::get('host')); ?></th>
    <th><?php echo h(Lang::get('port')); ?></th>
    <th><?php echo h(Lang::get('username')); ?></th>
    <th class="actions"><?php echo h(Lang::get('actions')); ?></th>
  </tr>
  <?php if (empty($servers)): ?>
  <tr><td colspan="5" class="empty">暂无服务器，<a href="?action=server_edit">添加服务器</a></td></tr>
  <?php else: foreach ($servers as $s): ?>
  <tr>
    <td><strong><?php echo h($s['name']); ?></strong></td>
    <td class="mono"><?php echo h($s['host']); ?></td>
    <td><?php echo h($s['port']); ?></td>
    <td><?php echo h($s['username']); ?></td>
    <td class="actions">
      <?php ui_post_action('server_test', (int)$s['id'], '测试'); ?>
      <?php ui_btn('?action=server_edit&id=' . (int)$s['id'], Lang::get('edit')); ?>
      <?php ui_post_action('server_delete', (int)$s['id'], Lang::get('delete'), 'danger', '确定删除此服务器?'); ?>
    </td>
  </tr>
  <?php endforeach; endif; ?>
</table>
<?php ui_panel_close(); ui_page_close(); ?>

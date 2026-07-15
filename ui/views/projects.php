<?php
ui_page_open(Lang::get('projects'), 'projects');
ui_flash();
ui_panel_open(Lang::get('projects'), '?action=project_edit', Lang::get('add_project'));
?>
<table class="tbl" width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <th><?php echo h(Lang::get('project_name')); ?></th>
    <th><?php echo h(Lang::get('repo_url')); ?></th>
    <th><?php echo h(Lang::get('branch')); ?></th>
    <th><?php echo h(Lang::get('server')); ?></th>
    <th class="actions"><?php echo h(Lang::get('actions')); ?></th>
  </tr>
  <?php if (empty($projects)): ?>
  <tr><td colspan="5" class="empty">暂无项目，<a href="?action=project_edit">添加项目</a></td></tr>
  <?php else: foreach ($projects as $p): ?>
  <tr>
    <td><strong><?php echo h($p['name']); ?></strong></td>
    <td class="mono"><?php echo h($p['repo_url']); ?></td>
    <td class="mono"><?php echo h($p['branch']); ?></td>
    <td><?php echo h($p['server_name'] ?? '-'); ?></td>
    <td class="actions">
      <?php ui_deploy_form((int)$p['id'], $p['branch']); ?>
      <?php ui_btn('?action=project_edit&id=' . (int)$p['id'], Lang::get('edit')); ?>
      <?php ui_post_action('project_delete', (int)$p['id'], Lang::get('delete'), 'danger', '确定删除此项目?'); ?>
    </td>
  </tr>
  <?php endforeach; endif; ?>
</table>
<?php ui_panel_close(); ui_page_close(); ?>

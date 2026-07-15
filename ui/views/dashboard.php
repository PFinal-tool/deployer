<?php
ui_page_open(Lang::get('dashboard'), 'dashboard');
ui_sshpass_alert();
ui_flash();
?>
<table width="100%" cellpadding="0" cellspacing="0" class="stats"><tr>
  <td width="33%">
    <div class="num"><?php echo count($projects); ?></div>
    <div class="lbl">项目总数</div>
  </td>
  <td width="33%">
    <div class="num"><?php echo count($deployments); ?></div>
    <div class="lbl">最近部署记录</div>
  </td>
  <td width="34%">
    <div class="num"><?php echo $envSummary['issues'] ? $envSummary['issues'] : 'OK'; ?></div>
    <div class="lbl">环境<?php echo $envSummary['issues'] ? '待处理项' : '正常'; ?></div>
  </td>
</tr></table>

<table width="100%" cellpadding="0" cellspacing="0"><tr>
  <td width="58%" valign="top" style="padding-right:10px">
    <?php ui_panel_open('最近项目', '?action=projects', '查看全部'); ?>
    <table class="tbl" width="100%" cellpadding="0" cellspacing="0">
      <tr>
        <th><?php echo h(Lang::get('project_name')); ?></th>
        <th><?php echo h(Lang::get('branch')); ?></th>
        <th class="actions"><?php echo h(Lang::get('actions')); ?></th>
      </tr>
      <?php if (empty($projects)): ?>
      <tr><td colspan="3" class="empty">暂无项目，<a href="?action=project_edit">添加项目</a></td></tr>
      <?php else: foreach (array_slice($projects, 0, 8) as $p): ?>
      <tr>
        <td>
          <strong><?php echo h($p['name']); ?></strong><br>
          <span class="note mono"><?php echo h($p['repo_url']); ?></span>
        </td>
        <td><span class="mono"><?php echo h($p['branch']); ?></span></td>
        <td class="actions">
          <?php ui_deploy_form((int)$p['id'], $p['branch']); ?>
          <?php ui_btn('?action=project_edit&id=' . (int)$p['id'], Lang::get('edit')); ?>
        </td>
      </tr>
      <?php endforeach; endif; ?>
    </table>
    <?php ui_panel_close(); ?>
  </td>
  <td width="42%" valign="top" style="padding-left:10px">
    <?php ui_panel_open('最近部署', '?action=deployments', '查看全部'); ?>
    <table class="tbl" width="100%" cellpadding="0" cellspacing="0">
      <tr><th>项目</th><th>状态</th><th>时间</th></tr>
      <?php if (empty($deployments)): ?>
      <tr><td colspan="3" class="empty">暂无部署记录</td></tr>
      <?php else: foreach ($deployments as $d): ?>
      <tr>
        <td>
          <strong><?php echo h($d['project_name']); ?></strong><br>
          <span class="note mono"><?php echo h($d['branch']); ?></span>
        </td>
        <td><?php ui_status_badge($d['status']); ?></td>
        <td class="note"><?php echo h($d['started_at']); ?></td>
      </tr>
      <?php endforeach; endif; ?>
    </table>
    <?php ui_panel_close(); ?>
  </td>
</tr></table>

<?php ui_panel_open('环境检测'); ?>
<table class="tbl" width="100%" cellpadding="0" cellspacing="0">
  <tr><th>检测项</th><th>当前值</th><th>状态</th><th>说明</th></tr>
  <?php foreach ($envCheck as $c): ?>
  <tr>
    <td><?php echo h($c['name']); ?></td>
    <td class="mono"><?php echo h($c['value']); ?></td>
    <td><?php ui_status_badge($c['status']); ?></td>
    <td class="note"><?php echo h($c['message']); ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php ui_panel_close(); ?>
<?php ui_page_close(); ?>

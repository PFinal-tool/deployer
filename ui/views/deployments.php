<?php
ui_page_open(Lang::get('deployments'), 'deployments');
ui_flash();
ui_panel_open(Lang::get('deployments'));
$highlightId = $highlightId ?? null;
?>
<table class="tbl" width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <th><?php echo h(Lang::get('project_name')); ?></th>
    <th><?php echo h(Lang::get('branch')); ?></th>
    <th><?php echo h(Lang::get('commit_hash')); ?></th>
    <th><?php echo h(Lang::get('status')); ?></th>
    <th><?php echo h(Lang::get('started_at')); ?></th>
    <th><?php echo h(Lang::get('finished_at')); ?></th>
    <th class="actions">操作</th>
  </tr>
  <?php if (empty($deployments)): ?>
  <tr><td colspan="7" class="empty">暂无部署记录</td></tr>
  <?php else: foreach ($deployments as $d): ?>
  <tr>
    <td><a href="?action=deployments&amp;project_id=<?php echo (int)$d['project_id']; ?>"><strong><?php echo h($d['project_name']); ?></strong></a></td>
    <td class="mono"><?php echo h($d['branch']); ?></td>
    <td class="mono"><?php echo h(substr($d['commit_hash'] ?? '', 0, 7) ?: '-'); ?></td>
    <td><?php ui_status_badge($d['status']); ?></td>
    <td class="note"><?php echo h($d['started_at']); ?></td>
    <td class="note"><?php echo h($d['finished_at'] ?? '-'); ?></td>
    <td class="actions">
      <button type="button" class="btn btn-sm" onclick="toggleLog(<?php echo (int)$d['id']; ?>)">日志</button>
      <?php if ($d['status'] === 'success' && !empty($d['commit_hash'])) {
          ui_rollback_form((int)$d['project_id'], $d['commit_hash']);
      } ?>
    </td>
  </tr>
  <tr id="log-row-<?php echo (int)$d['id']; ?>" style="display:none">
    <td colspan="7"><div class="log" id="log-<?php echo (int)$d['id']; ?>">点击「日志」加载...</div></td>
  </tr>
  <?php endforeach; endif; ?>
</table>
<?php if ($highlightId): ?>
<script>document.addEventListener('DOMContentLoaded',function(){toggleLog(<?php echo (int)$highlightId; ?>);});</script>
<?php endif; ?>
<?php ui_panel_close(); ui_page_close(); ?>

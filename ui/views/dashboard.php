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
    <title><?php echo Lang::get('dashboard'); ?> - <?php echo Lang::get('title'); ?></title>
    <?php echo getCSS(); ?>
</head>
<body>
<div class="header"><div class="wrap">
  <table><tr>
    <td><strong><?php echo Lang::get('title'); ?></strong></td>
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
  <table>
    <thead><tr><th colspan="2"><?php echo Lang::get('dashboard'); ?></th></tr></thead>
    <tbody>
      <tr><th>总项目数</th><td><?php echo count($projects); ?></td></tr>
      <tr><th>最近部署</th><td><?php echo count($deployments); ?></td></tr>
    </tbody>
  </table>

  <br>
  <table>
    <thead>
      <tr><th colspan="4">环境检测</th></tr>
      <tr>
        <th>检测项</th>
        <th>状态</th>
        <th>值</th>
        <th>说明</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!empty($envCheck)): foreach ($envCheck as $check): ?>
        <tr>
          <td><?php echo htmlspecialchars($check['name']); ?></td>
          <td>
            <?php if ($check['status'] === 'ok'): ?>
              <span style="color:green">✓ 正常</span>
            <?php elseif ($check['status'] === 'warning'): ?>
              <span style="color:orange">⚠ 警告</span>
            <?php else: ?>
              <span style="color:red">✗ 错误</span>
            <?php endif; ?>
          </td>
          <td><?php echo htmlspecialchars($check['value']); ?></td>
          <td><?php echo htmlspecialchars($check['message']); ?></td>
        </tr>
      <?php endforeach; else: ?>
        <tr><td colspan="4">环境检测中...</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <br>
  <table>
    <thead>
      <tr><th colspan="3">最近项目</th></tr>
      <tr>
        <th><?php echo Lang::get('project_name'); ?></th>
        <th><?php echo Lang::get('branch'); ?></th>
        <th><?php echo Lang::get('actions'); ?></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($projects)): ?>
        <tr><td colspan="3">暂无项目，<a href="?action=project_edit">添加项目</a></td></tr>
      <?php else: foreach ($projects as $project): ?>
        <tr>
          <td><?php echo htmlspecialchars($project['name']); ?></td>
          <td><?php echo htmlspecialchars($project['branch']); ?></td>
          <td>
            <a href="#" class="btn btn-small" onclick="showDeployDialog(<?php echo $project['id']; ?>, '<?php echo htmlspecialchars($project['branch'], ENT_QUOTES); ?>'); return false;"><?php echo Lang::get('deploy'); ?></a>
            <a href="?action=project_edit&id=<?php echo $project['id']; ?>" class="btn btn-small"><?php echo Lang::get('edit'); ?></a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>

  <br>
  <table>
    <thead>
      <tr><th colspan="5">最近部署</th></tr>
      <tr>
        <th><?php echo Lang::get('project_name'); ?></th>
        <th><?php echo Lang::get('branch'); ?></th>
        <th><?php echo Lang::get('status'); ?></th>
        <th><?php echo Lang::get('started_at'); ?></th>
        <th><?php echo Lang::get('actions'); ?></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($deployments)): ?>
        <tr><td colspan="5">暂无部署记录</td></tr>
      <?php else: foreach ($deployments as $deployment): ?>
        <tr>
          <td><?php echo htmlspecialchars($deployment['project_name']); ?></td>
          <td><?php echo htmlspecialchars($deployment['branch']); ?></td>
          <td><span class="badge badge-<?php echo $deployment['status'] === 'success' ? 'success' : ($deployment['status'] === 'failed' ? 'danger' : 'warning'); ?>"><?php echo Lang::get($deployment['status']); ?></span></td>
          <td><?php echo htmlspecialchars($deployment['started_at']); ?></td>
          <td><a href="?action=deployments&project_id=<?php echo $deployment['project_id']; ?>" class="btn btn-small"><?php echo Lang::get('view'); ?></a></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<!-- 部署对话框 -->
<div id="deploy-dialog" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000;">
  <div style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); background:white; padding:20px; border-radius:5px; min-width:400px;">
    <h3 style="margin-top:0;">部署项目</h3>
    <form id="deploy-form" method="GET">
      <input type="hidden" name="action" value="deploy">
      <input type="hidden" name="id" id="deploy-project-id">
      
      <div style="margin-bottom:15px;">
        <label style="display:block; margin-bottom:5px;">
          <input type="radio" name="deploy_type" value="default" checked onchange="toggleCustomRef()">
          使用默认分支/标签: <code id="default-branch"></code>
        </label>
      </div>
      
      <div style="margin-bottom:15px;">
        <label style="display:block; margin-bottom:5px;">
          <input type="radio" name="deploy_type" value="custom" onchange="toggleCustomRef()">
          指定分支/标签:
        </label>
        <input type="text" name="branch" id="custom-branch" placeholder="输入分支名或标签名，如: master, v1.0.0" style="width:100%; padding:5px; margin-top:5px;" disabled>
        <div style="font-size:12px; color:#666; margin-top:5px;">
          提示：可以输入分支名（如 master）或标签名（如 v1.0.0），系统会自动识别
        </div>
      </div>
      
      <div style="text-align:right; margin-top:20px;">
        <button type="button" class="btn btn-small" onclick="hideDeployDialog()">取消</button>
        <button type="submit" class="btn btn-small" onclick="return confirm('确定要部署吗？')">开始部署</button>
      </div>
    </form>
  </div>
</div>

<?php echo getJS(); ?>
<script>
function showDeployDialog(projectId, defaultBranch) {
  document.getElementById('deploy-project-id').value = projectId;
  document.getElementById('default-branch').textContent = defaultBranch;
  document.getElementById('custom-branch').value = '';
  document.getElementById('deploy-dialog').style.display = 'block';
}

function hideDeployDialog() {
  document.getElementById('deploy-dialog').style.display = 'none';
}

function toggleCustomRef() {
  const customRef = document.getElementById('custom-branch');
  const deployType = document.querySelector('input[name="deploy_type"]:checked').value;
  customRef.disabled = deployType !== 'custom';
  if (deployType === 'custom') {
    customRef.focus();
  } else {
    customRef.value = '';
  }
}

// 表单提交前处理：如果选择默认分支，移除 branch 参数
document.getElementById('deploy-form').addEventListener('submit', function(e) {
  const deployType = document.querySelector('input[name="deploy_type"]:checked').value;
  if (deployType === 'default') {
    // 移除 branch 输入框，让后端使用默认分支
    const branchInput = document.getElementById('custom-branch');
    if (branchInput) {
      branchInput.disabled = true;
      branchInput.name = '';
    }
  }
});

// 点击对话框外部关闭
document.getElementById('deploy-dialog').addEventListener('click', function(e) {
  if (e.target === this) {
    hideDeployDialog();
  }
});
</script>
</body>
</html>


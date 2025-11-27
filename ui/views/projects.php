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
    <title><?php echo Lang::get('projects'); ?> - <?php echo Lang::get('title'); ?></title>
    <?php echo getCSS(); ?>
</head>
<body>
<div class="header"><div class="wrap">
  <table><tr>
    <td><strong><?php echo Lang::get('projects'); ?></strong></td>
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
  <table style="width:100%">
    <thead>
      <tr>
        <th><?php echo Lang::get('projects'); ?></th>
        <th style="text-align:right"><a href="?action=project_edit" class="btn btn-small"><?php echo Lang::get('add_project'); ?></a></th>
      </tr>
    </thead>
  </table>

  <table class="data-table">
    <thead>
      <tr>
        <th><?php echo Lang::get('project_name'); ?></th>
        <th><?php echo Lang::get('repo_url'); ?></th>
        <th><?php echo Lang::get('branch'); ?></th>
        <th><?php echo Lang::get('server'); ?></th>
        <th><?php echo Lang::get('actions'); ?></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($projects)): ?>
        <tr><td colspan="5">暂无项目，<a href="?action=project_edit">添加项目</a></td></tr>
      <?php else: foreach ($projects as $project): ?>
        <tr>
          <td><?php echo htmlspecialchars($project['name']); ?></td>
          <td><?php echo htmlspecialchars($project['repo_url']); ?></td>
          <td><?php echo htmlspecialchars($project['branch']); ?></td>
          <td><?php echo htmlspecialchars($project['server_name'] ?? '-'); ?></td>
          <td>
            <a href="#" class="btn btn-small" onclick="showDeployDialog(<?php echo $project['id']; ?>, '<?php echo htmlspecialchars($project['branch'], ENT_QUOTES); ?>'); return false;"><?php echo Lang::get('deploy'); ?></a>
            <a href="?action=project_edit&id=<?php echo $project['id']; ?>" class="btn btn-small"><?php echo Lang::get('edit'); ?></a>
            <a href="?action=project_delete&id=<?php echo $project['id']; ?>" class="btn btn-small btn-danger" onclick="return confirmDelete('确定要删除此项目吗？')"><?php echo Lang::get('delete'); ?></a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<!-- 部署对话框 -->
<div id="deploy-dialog" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; opacity: 0; transition: opacity 0.2s ease-in-out;">
  <div style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%) scale(0.95); background:white; padding:25px; border-radius:8px; min-width:420px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); transition: transform 0.2s ease-in-out; overflow: hidden;">
    <!-- Loading 遮罩 -->
    <div id="deploy-loading" style="display:none; position:absolute; top:0; left:0; width:100%; height:100%; background:rgba(255,255,255,0.9); z-index:10; flex-direction:column; justify-content:center; align-items:center;">
        <div style="font-size:40px; margin-bottom:20px; animation: bounce 1s infinite;">🚀</div>
        <div style="font-size:16px; color:#333; font-weight:600;">正在部署...</div>
        <div style="font-size:13px; color:#666; margin-top:10px;">请耐心等待，不要关闭页面</div>
    </div>

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom:1px solid #eee; padding-bottom:15px;">
      <h3 style="margin:0; font-size:18px; color:#333;">部署项目</h3>
      <span style="cursor:pointer; font-size:20px; color:#999; line-height:1;" onclick="hideDeployDialog()">&times;</span>
    </div>
    <form id="deploy-form" method="GET">
      <input type="hidden" name="action" value="deploy">
      <input type="hidden" name="id" id="deploy-project-id">
      
      <div style="margin-bottom:15px; padding:10px; background:#f9f9f9; border-radius:4px; border:1px solid #eee;">
        <label style="display:flex; align-items:center; cursor:pointer;">
          <input type="radio" name="deploy_type" value="default" checked onchange="toggleCustomRef()" style="margin-right:8px;">
          <span style="font-weight:500;">使用默认分支/标签</span>
          <code id="default-branch" style="margin-left:10px; background:#e0e0e0; padding:2px 6px; border-radius:3px; font-size:12px;"></code>
        </label>
      </div>
      
      <div style="margin-bottom:20px; padding:10px; border-radius:4px; border:1px solid #eee; transition: all 0.2s;" id="custom-branch-container">
        <label style="display:flex; align-items:center; margin-bottom:10px; cursor:pointer;">
          <input type="radio" name="deploy_type" value="custom" onchange="toggleCustomRef()" style="margin-right:8px;">
          <span style="font-weight:500;">指定分支/标签</span>
        </label>
        <input type="text" name="branch" id="custom-branch" placeholder="输入分支名或标签名 (如: v1.0.0)" style="width:100%; padding:8px; margin-top:5px; border:1px solid #ddd; border-radius:4px; box-sizing:border-box; outline:none; transition:border-color 0.2s;" disabled>
        <div style="font-size:12px; color:#666; margin-top:8px; display:flex; align-items:center;">
          <span style="margin-right:5px; font-size:14px;">💡</span> 系统会自动识别分支或 Tag
        </div>
      </div>
      
      <div style="text-align:right; margin-top:25px; display:flex; justify-content:flex-end; gap:10px;">
        <button type="button" class="btn" onclick="hideDeployDialog()" style="border:none; background:#f5f5f5; color:#666; padding:8px 16px; border-radius:4px; cursor:pointer;">取消</button>
        <button type="submit" class="btn" style="border:none; background:#007bff; color:white; padding:8px 20px; border-radius:4px; cursor:pointer; font-weight:500; box-shadow: 0 2px 5px rgba(0,123,255,0.3);">开始部署</button>
      </div>
    </form>
  </div>
</div>

<style>
@keyframes bounce {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-10px); }
}
</style>

<?php echo getJS(); ?>
<script>
function showDeployDialog(projectId, defaultBranch) {
  document.getElementById('deploy-project-id').value = projectId;
  document.getElementById('default-branch').textContent = defaultBranch;
  document.getElementById('custom-branch').value = '';
  
  // 恢复默认状态
  const defaultRadio = document.querySelector('input[name="deploy_type"][value="default"]');
  if(defaultRadio) {
      defaultRadio.checked = true;
      toggleCustomRef(); // 触发状态更新
  }

  // 隐藏 Loading
  document.getElementById('deploy-loading').style.display = 'none';

  const dialog = document.getElementById('deploy-dialog');
  const content = dialog.querySelector('div');
  
  dialog.style.display = 'block';
  // 强制重绘以触发 transition
  dialog.offsetHeight;
  
  dialog.style.opacity = '1';
  content.style.transform = 'translate(-50%, -50%) scale(1)';
}

function hideDeployDialog() {
  const dialog = document.getElementById('deploy-dialog');
  const content = dialog.querySelector('div');
  
  dialog.style.opacity = '0';
  content.style.transform = 'translate(-50%, -50%) scale(0.95)';
  
  setTimeout(() => {
    dialog.style.display = 'none';
  }, 200);
}

function toggleCustomRef() {
  const customRef = document.getElementById('custom-branch');
  const container = document.getElementById('custom-branch-container');
  const deployType = document.querySelector('input[name="deploy_type"]:checked').value;
  
  customRef.disabled = deployType !== 'custom';
  
  if (deployType === 'custom') {
    container.style.background = '#f0f7ff';
    container.style.borderColor = '#cce5ff';
    customRef.style.background = '#fff';
    customRef.focus();
  } else {
    container.style.background = 'transparent';
    container.style.borderColor = '#eee';
    customRef.style.background = '#f5f5f5';
    customRef.value = '';
    // 移除可能的 invalid 状态
    customRef.style.borderColor = '#ddd';
  }
}

// 表单提交前处理
document.getElementById('deploy-form').addEventListener('submit', function(e) {
  // 确认对话框
  if(!confirm('确定要部署吗？')) {
    e.preventDefault();
    return false;
  }

  const deployType = document.querySelector('input[name="deploy_type"]:checked').value;
  if (deployType === 'default') {
    const branchInput = document.getElementById('custom-branch');
    if (branchInput) {
      branchInput.disabled = true;
      branchInput.name = '';
    }
  }
  
  // 显示 Loading
  const loading = document.getElementById('deploy-loading');
  loading.style.display = 'flex';
  
  // 防止重复提交
  const submitBtn = this.querySelector('button[type="submit"]');
  if(submitBtn) submitBtn.disabled = true;
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


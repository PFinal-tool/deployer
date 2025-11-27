<?php
/**
 * JavaScript（内嵌）
 */
function getJS() {
    return <<<'JS'
<script>
function confirmDelete(message) {
    return confirm(message || '确定要删除吗？此操作不可恢复。');
}

function deployProject(projectId, branch) {
    if (!confirm('确定要部署此项目吗？')) {
        return;
    }
    
    window.location.href = '?action=deploy&id=' + projectId + '&branch=' + encodeURIComponent(branch);
}

function rollbackProject(projectId, commitHash) {
    if (!confirm('确定要回滚到此提交吗？')) {
        return;
    }
    
    // 这里应该调用 API
    fetch('?action=api&endpoint=rollback&project_id=' + projectId + '&commit_hash=' + encodeURIComponent(commitHash), {
        method: 'POST'
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('回滚成功');
            location.reload();
        } else {
            alert('回滚失败: ' + (data.error || '未知错误'));
        }
    });
}

function refreshDeploymentStatus(deploymentId) {
    fetch('?action=api&endpoint=deploy_status&deployment_id=' + deploymentId)
    .then(res => res.json())
    .then(data => {
        if (data.status === 'running') {
            setTimeout(() => refreshDeploymentStatus(deploymentId), 2000);
        }
        updateDeploymentUI(data);
    });
}

function updateDeploymentUI(data) {
    const statusEl = document.getElementById('deployment-status-' + data.id);
    if (statusEl) {
        statusEl.className = 'badge badge-' + (data.status === 'success' ? 'success' : data.status === 'failed' ? 'danger' : 'warning');
        statusEl.textContent = data.status;
    }
}

// 实时日志（如果需要）
function loadDeploymentLog(deploymentId) {
    fetch('?action=api&endpoint=deploy_log&deployment_id=' + deploymentId)
    .then(res => res.json())
    .then(data => {
        const logEl = document.getElementById('deployment-log-' + deploymentId);
        if (logEl) {
            const isScrolledToBottom = logEl.scrollHeight - logEl.clientHeight <= logEl.scrollTop + 1;
            
            logEl.textContent = data.output || data.error || '暂无日志';
            
            // 如果原本就在底部，或者内容刚初始化，自动滚动到底部
            if (isScrolledToBottom || !logEl.getAttribute('data-init')) {
                logEl.scrollTop = logEl.scrollHeight;
                logEl.setAttribute('data-init', '1');
            }
        }
    });
}
</script>
JS;
}


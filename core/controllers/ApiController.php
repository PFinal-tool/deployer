<?php
/**
 * API 控制器
 */
class ApiController extends BaseController {
    
    /**
     * 处理 API 请求
     */
    public function handle() {
        $this->requireLogin();
        
        $endpoint = $_GET['endpoint'] ?? '';
        
        switch ($endpoint) {
            case 'deploy_status': $this->deployStatus(); break;
            case 'deploy_log': $this->deployLog(); break;
            default: $this->renderJson(['error' => 'Invalid endpoint'], 404);
        }
    }
    
    /**
     * 获取部署状态
     */
    private function deployStatus() {
        try {
            $deploymentId = Validator::validateDeploymentId($_GET['deployment_id'] ?? null);
            $deployment = $this->db->fetchOne("SELECT * FROM deployments WHERE id = ?", [$deploymentId]);
            
            $this->renderJson($deployment ?: ['error' => 'Not found'], $deployment ? 200 : 404);
        } catch (InvalidArgumentException $e) {
            Logger::warning("API deploy status validation failed: " . $e->getMessage());
            $this->renderJson(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * 获取部署日志
     */
    private function deployLog() {
        try {
            $deploymentId = Validator::validateDeploymentId($_GET['deployment_id'] ?? null);
            $deployment = $this->db->fetchOne("SELECT output, error, status FROM deployments WHERE id = ?", [$deploymentId]);
            if (!$deployment) { $this->renderJson(['error' => 'Not found'], 404); return; }
            $logContent = '';
            if (!empty($deployment['output'])) { $logContent .= $deployment['output']; }
            if (!empty($deployment['error'])) { if (!empty($logContent)) { $logContent .= "\n\n"; } $logContent .= "错误信息: " . $deployment['error']; }
            if (empty($logContent)) { $logContent = '暂无日志'; }
            
            $this->renderJson([
                'output' => $logContent,
                'error' => $deployment['error'] ?? null,
                'status' => $deployment['status'] ?? 'unknown'
            ]);
        } catch (InvalidArgumentException $e) {
            Logger::warning("API deploy log validation failed: " . $e->getMessage());
            $this->renderJson(['error' => $e->getMessage()], 400);
        }
    }
}

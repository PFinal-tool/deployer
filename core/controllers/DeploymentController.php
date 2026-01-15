<?php
/**
 * 部署控制器
 */
class DeploymentController extends BaseController {
    
    /**
     * 仪表板
     */
    public function dashboard() {
        $this->requireLogin();
        if ($this->auth->isDefaultPassword()) { $this->redirect('change_password', ['required' => 1]); }
        
        $projects = $this->db->fetchAll("SELECT * FROM projects ORDER BY id DESC LIMIT 10");
        $recentDeployments = $this->db->fetchAll("SELECT d.*, p.name as project_name FROM deployments d JOIN projects p ON d.project_id = p.id ORDER BY d.started_at DESC LIMIT 10");
        
        $envCheck = $this->checkEnvironment();
        
        $this->renderDashboard($projects, $recentDeployments, $envCheck);
    }
    
    /**
     * 部署列表
     */
    public function index() {
        $this->requireLogin();
        
        $projectId = $_GET['project_id'] ?? null;
        
        $sql = "SELECT d.*, p.name as project_name FROM deployments d JOIN projects p ON d.project_id = p.id";
        $params = [];
        if ($projectId) { $sql .= " WHERE d.project_id = ?"; $params[] = $projectId; }
        $sql .= " ORDER BY d.started_at DESC LIMIT 50";
        $deployments = $this->db->fetchAll($sql, $params);
        if (class_exists('SecurityOutput')) { $deployments = SecurityOutput::escapeArray($deployments); }
        
        $this->renderDeployments($deployments);
    }
    
    /**
     * 执行部署
     */
    public function deploy() {
        $this->requireLogin();
        
        // 设置执行时间限制
        set_time_limit(600); // 10 分钟
        
        try {
            try { if (class_exists('RateLimiter')) { RateLimiter::check('deploy'); } } catch (Exception $e) { $this->flash('error', $e->getMessage()); $this->redirect('projects'); }
            $projectId = Validator::validateProjectId($_GET['id'] ?? null);
            $branch = null;
            if (isset($_GET['branch']) && !empty(trim($_GET['branch']))) { $branch = Validator::validateBranch(trim($_GET['branch'])); } else { $project = $this->db->fetchOne("SELECT branch FROM projects WHERE id = ?", [$projectId]); if ($project) { $branch = $project['branch']; } else { throw new InvalidArgumentException("项目不存在"); } }
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && !CSRF::validate()) { Logger::warning("CSRF validation failed on deploy"); $this->flash('error', '安全验证失败，请重新提交'); $this->redirect('projects'); }
            if (class_exists('AuditLogger')) { AuditLogger::logDeployment('started', null, $projectId, $branch); }
            $deployer = new Deployer();
            $result = $deployer->deploy($projectId, $branch);
            if (class_exists('AuditLogger') && isset($result['deployment_id'])) { $status = $result['success'] ? 'completed' : 'failed'; AuditLogger::logDeployment($status, $result['deployment_id'], $projectId, $branch); }
            if ($result['success']) { $this->flash('success', '部署成功'); } else { $this->flash('error', '部署失败: ' . ($result['error'] ?? '未知错误')); }
        } catch (InvalidArgumentException $e) {
            Logger::warning("Deploy validation failed: " . $e->getMessage());
            $this->flash('error', '参数错误: ' . $e->getMessage());
            $this->redirect('projects');
        } catch (Exception $e) {
            Logger::error("Deployment handler exception: " . $e->getMessage());
            $this->flash('error', '部署失败: ' . $e->getMessage());
        }
        
        $this->redirect('deployments', ['project_id' => $projectId ?? '']);
    }
    
    /**
     * Webhook 处理
     */
    public function webhook() {
        $projectId = $_GET['project_id'] ?? null;
        $token = $_GET['token'] ?? '';
        if (!$projectId || !$token) { http_response_code(400); $this->renderJson(['error' => 'Missing parameters: project_id and token are required']); return; }
        try {
            $validatedProjectId = Validator::validateProjectId($projectId);
            $project = $this->db->fetchOne("SELECT * FROM projects WHERE id = ? AND webhook_enabled = 1", [$validatedProjectId]);
            if (!$project) { Logger::warning("Webhook failed: project not found or webhook disabled, project_id={$validatedProjectId}"); http_response_code(404); $this->renderJson(['error' => 'Project not found or webhook disabled']); return; }
            if ($project['webhook_token'] !== $token) { Logger::warning("Webhook failed: invalid token, project_id={$validatedProjectId}"); http_response_code(403); $this->renderJson(['error' => 'Invalid token']); return; }
            if (!$this->validateWebhookSignature($project)) { Logger::warning("Webhook failed: invalid signature, project_id={$validatedProjectId}"); http_response_code(403); $this->renderJson(['error' => 'Invalid signature']); return; }
            $branch = $this->extractBranchFromWebhook();
            if (!$branch) { $branch = $project['branch']; }
            Logger::info("Webhook triggered: project_id={$validatedProjectId}, branch={$branch}");
            if (class_exists('AuditLogger')) { AuditLogger::log('webhook_triggered', ['project_id' => $validatedProjectId, 'project_name' => $project['name'], 'branch' => $branch, 'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']); }
            try {
                $deployer = new Deployer();
                $result = $deployer->deploy($validatedProjectId, $branch);
                if ($result['success']) { Logger::info("Webhook deployment successful: project_id={$validatedProjectId}, deployment_id={$result['deployment_id']}"); $this->renderJson(['status' => 'ok', 'message' => 'Deployment triggered successfully', 'deployment_id' => $result['deployment_id']]); } else { Logger::error("Webhook deployment failed: project_id={$validatedProjectId}, error=" . ($result['error'] ?? 'unknown')); http_response_code(500); $this->renderJson(['status' => 'error', 'message' => 'Deployment failed: ' . ($result['error'] ?? 'unknown error')]); }
            } catch (Exception $e) {
                Logger::error("Webhook deployment exception: project_id={$validatedProjectId}, error=" . $e->getMessage());
                http_response_code(500);
                $this->renderJson(['status' => 'error', 'message' => 'Deployment failed: ' . $e->getMessage()]);
            }
        } catch (InvalidArgumentException $e) {
            Logger::warning("Webhook validation failed: " . $e->getMessage());
            http_response_code(400);
            $this->renderJson(['error' => $e->getMessage()]);
        } catch (Exception $e) {
            Logger::error("Webhook handler exception: " . $e->getMessage());
            http_response_code(500);
            $this->renderJson(['error' => 'Internal server error']);
        }
    }
    
    /**
     * 验证 Webhook 签名
     */
    private function validateWebhookSignature($project) {
        $githubSignature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
        if ($githubSignature) { $payload = file_get_contents('php://input'); $expected = 'sha256=' . hash_hmac('sha256', $payload, $project['webhook_token']); return hash_equals($expected, $githubSignature); }
        $gitlabToken = $_SERVER['HTTP_X_GITLAB_TOKEN'] ?? '';
        if ($gitlabToken) { return hash_equals($project['webhook_token'], $gitlabToken); }
        $giteeToken = $_SERVER['HTTP_X_GITEE_TOKEN'] ?? '';
        if ($giteeToken) { return hash_equals($project['webhook_token'], $giteeToken); }
        return true;
    }
    
    /**
     * 从 Webhook payload 中提取分支信息
     */
    private function extractBranchFromWebhook() {
        $payload = file_get_contents('php://input');
        if (empty($payload)) { return null; }
        $data = json_decode($payload, true);
        if (!$data) { return null; }
        if (isset($data['ref'])) { if (preg_match('/^refs\/heads\/(.+)$/', $data['ref'], $matches)) { return $matches[1]; } }
        if (isset($data['ref'])) { if (strpos($data['ref'], 'refs/heads/') === 0) { return substr($data['ref'], 11); } return $data['ref']; }
        if (isset($data['ref'])) { return $data['ref']; }
        return null;
    }
    
    /**
     * 检查环境
     */
    private function checkEnvironment() {
        $checks = [];
        
        // PHP 版本
        $phpVersion = PHP_VERSION;
        $phpVersionOk = version_compare($phpVersion, '7.4.0', '>=');
        $checks[] = [
            'name' => 'PHP 版本',
            'value' => $phpVersion,
            'status' => $phpVersionOk ? 'ok' : 'error',
            'message' => $phpVersionOk ? '版本符合要求' : '需要 PHP 7.4 或更高版本'
        ];
        
        // PDO 扩展
        $pdoExists = extension_loaded('pdo');
        $checks[] = [
            'name' => 'PDO 扩展',
            'value' => $pdoExists ? '已安装' : '未安装',
            'status' => $pdoExists ? 'ok' : 'error',
            'message' => $pdoExists ? '' : '需要安装 PDO 扩展'
        ];
        
        // SQLite PDO 驱动
        $pdoSqliteExists = extension_loaded('pdo_sqlite');
        $checks[] = [
            'name' => 'PDO SQLite 驱动',
            'value' => $pdoSqliteExists ? '已安装' : '未安装',
            'status' => $pdoSqliteExists ? 'ok' : 'error',
            'message' => $pdoSqliteExists ? '' : '需要安装 pdo_sqlite 扩展'
        ];
        
        // sshpass 工具
        $sshpassPath = $this->checkSshpassForEnv();
        $sshpassExists = $sshpassPath !== '';
        $checks[] = [
            'name' => 'sshpass 工具',
            'value' => $sshpassExists ? '已安装' : '未安装',
            'status' => $sshpassExists ? 'ok' : 'warning',
            'message' => $sshpassExists ? ($sshpassPath !== 'sshpass' ? "路径: {$sshpassPath}" : '') : '如需使用密码认证，请安装 sshpass'
        ];
        
        // Git 工具
        $gitPath = trim(shell_exec('command -v git') ?? '');
        $gitExists = $gitPath !== '';
        $checks[] = [
            'name' => 'Git 工具',
            'value' => $gitExists ? '已安装' : '未安装',
            'status' => $gitExists ? 'ok' : 'warning',
            'message' => $gitExists ? '' : '需要 Git 工具用于代码部署'
        ];
        
        // SSH 客户端
        $sshPath = trim(shell_exec('command -v ssh') ?? '');
        $sshExists = $sshPath !== '';
        $checks[] = [
            'name' => 'SSH 客户端',
            'value' => $sshExists ? '已安装' : '未安装',
            'status' => $sshExists ? 'ok' : 'error',
            'message' => $sshExists ? '' : '需要 SSH 客户端'
        ];
        
        // 存储目录权限
        $storageDir = __DIR__ . '/../../storage';
        $logsDir = $storageDir . '/logs';
        $storageWritable = is_writable($storageDir) || (is_dir($storageDir) && is_writable($storageDir));
        $logsWritable = is_dir($logsDir) ? is_writable($logsDir) : true;
        $checks[] = [
            'name' => '存储目录权限',
            'value' => $storageWritable ? '可写' : '不可写',
            'status' => $storageWritable ? 'ok' : 'error',
            'message' => $storageWritable ? '' : 'storage 目录需要可写权限'
        ];
        
        // 数据库连接
        try {
            $this->db->query("SELECT 1");
            $dbOk = true;
            $dbMessage = '';
        } catch (Exception $e) {
            $dbOk = false;
            $dbMessage = $e->getMessage();
        }
        $checks[] = [
            'name' => '数据库连接',
            'value' => $dbOk ? '正常' : '失败',
            'status' => $dbOk ? 'ok' : 'error',
            'message' => $dbMessage
        ];
        
        // 时区设置
        $timezone = date_default_timezone_get();
        $checks[] = [
            'name' => '时区设置',
            'value' => $timezone,
            'status' => 'ok',
            'message' => ''
        ];
        
        return $checks;
    }
    
    /**
     * 检查 sshpass 是否可用（用于环境检测）
     */
    private function checkSshpassForEnv() {
        $path = trim(shell_exec('command -v sshpass 2>/dev/null') ?? '');
        if ($path !== '' && file_exists($path)) { return $path; }
        $path = trim(shell_exec('which sshpass 2>/dev/null') ?? '');
        if ($path !== '' && file_exists($path)) { return $path; }
        $commonPaths = ['/usr/bin/sshpass', '/usr/local/bin/sshpass', '/bin/sshpass'];
        foreach ($commonPaths as $commonPath) {
            if (file_exists($commonPath) && is_executable($commonPath)) { return $commonPath; }
        }
        $output = @shell_exec('sshpass -V 2>&1');
        if ($output !== null && strpos($output, 'sshpass') !== false) { $envPath = getenv('PATH'); if ($envPath) { $paths = explode(':', $envPath); foreach ($paths as $p) { $fullPath = rtrim($p, '/') . '/sshpass'; if (file_exists($fullPath) && is_executable($fullPath)) { return $fullPath; } } } return 'sshpass'; }
        return '';
    }
    
    /**
     * 渲染仪表板
     */
    private function renderDashboard($projects, $deployments, $envCheck = []) {
        if (class_exists('ViewRenderer')) { ViewRenderer::render('dashboard', ['projects' => $projects, 'deployments' => $deployments, 'envCheck' => $envCheck]); } else { $viewPath = __DIR__ . '/../../ui/views/dashboard.php'; if (file_exists($viewPath)) { include $viewPath; } else { echo "View file not found: dashboard.php"; } }
    }
    
    /**
     * 渲染部署列表
     */
    private function renderDeployments($deployments) {
        if (class_exists('ViewRenderer')) { ViewRenderer::render('deployments', ['deployments' => $deployments]); } else { $viewPath = __DIR__ . '/../../ui/views/deployments.php'; if (file_exists($viewPath)) { include $viewPath; } else { echo "View file not found: deployments.php"; } }
    }
}

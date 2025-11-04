<?php
/**
 * 简单路由处理类
 */
class Router {
    private $auth;
    
    public function __construct() {
        $this->auth = new Auth();
    }
    
    public function handle() {
        $action = $_GET['action'] ?? 'dashboard';
        
        // 公开路由（无需登录）
        $publicActions = ['login', 'logout', 'api'];
        
        // 需要登录的路由
        if (!in_array($action, $publicActions) && $action !== 'login') {
            $this->auth->requireLogin();
        }
        
        switch ($action) {
            case 'login':
                $this->handleLogin();
                break;
            case 'logout':
                $this->handleLogout();
                break;
            case 'dashboard':
                $this->handleDashboard();
                break;
            case 'projects':
                $this->handleProjects();
                break;
            case 'project_edit':
                $this->handleProjectEdit();
                break;
            case 'project_delete':
                $this->handleProjectDelete();
                break;
            case 'servers':
                $this->handleServers();
                break;
            case 'server_edit':
                $this->handleServerEdit();
                break;
            case 'server_delete':
                $this->handleServerDelete();
                break;
            case 'server_test':
                $this->handleServerTest();
                break;
            case 'deploy':
                $this->handleDeploy();
                break;
            case 'deployments':
                $this->handleDeployments();
                break;
            case 'webhook':
                $this->handleWebhook();
                break;
            case 'api':
                $this->handleApi();
                break;
            default:
                $this->handleDashboard();
        }
    }
    
    private function handleLogin() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            
            Logger::info("Login attempt: username={$username}");
            if ($this->auth->login($username, $password)) {
                Logger::info("Login successful: username={$username}");
                header('Location: ?action=dashboard');
                exit;
            } else {
                Logger::warning("Login failed: username={$username}");
                $error = '用户名或密码错误';
            }
        }
        
        $this->renderLogin($error ?? null);
    }
    
    private function handleLogout() {
        Logger::info("User logout");
        $this->auth->logout();
        header('Location: ?action=login');
        exit;
    }
    
    private function handleDashboard() {
        $db = Database::getInstance();
        $projects = $db->fetchAll("SELECT * FROM projects ORDER BY id DESC LIMIT 10");
        $recentDeployments = $db->fetchAll("
            SELECT d.*, p.name as project_name 
            FROM deployments d 
            JOIN projects p ON d.project_id = p.id 
            ORDER BY d.started_at DESC 
            LIMIT 10
        ");
        
        // 环境检测
        $envCheck = $this->checkEnvironment();
        
        $this->renderDashboard($projects, $recentDeployments, $envCheck);
    }
    
    /**
     * 检查 sshpass 是否可用（用于环境检测）
     */
    private function checkSshpassForEnv() {
        // 方法1: 使用 command -v（优先使用完整路径）
        $path = trim(shell_exec('command -v sshpass 2>/dev/null') ?? '');
        if ($path !== '' && file_exists($path)) {
            return $path;
        }
        
        // 方法2: 使用 which
        $path = trim(shell_exec('which sshpass 2>/dev/null') ?? '');
        if ($path !== '' && file_exists($path)) {
            return $path;
        }
        
        // 方法3: 尝试常见路径
        $commonPaths = ['/usr/bin/sshpass', '/usr/local/bin/sshpass', '/bin/sshpass'];
        foreach ($commonPaths as $commonPath) {
            if (file_exists($commonPath) && is_executable($commonPath)) {
                return $commonPath;
            }
        }
        
        // 方法4: 尝试直接执行 sshpass -V 来验证（最后的手段）
        $output = @shell_exec('sshpass -V 2>&1');
        if ($output !== null && strpos($output, 'sshpass') !== false) {
            // 如果能执行，尝试找到完整路径
            $envPath = getenv('PATH');
            if ($envPath) {
                $paths = explode(':', $envPath);
                foreach ($paths as $p) {
                    $fullPath = rtrim($p, '/') . '/sshpass';
                    if (file_exists($fullPath) && is_executable($fullPath)) {
                        return $fullPath;
                    }
                }
            }
            return 'sshpass';
        }
        
        return '';
    }
    
    /**
     * 环境检测
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
        $storageDir = __DIR__ . '/../storage';
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
            $db = Database::getInstance();
            $db->query("SELECT 1");
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
    
    private function handleProjects() {
        $db = Database::getInstance();
        $projects = $db->fetchAll("
            SELECT p.*, s.name as server_name 
            FROM projects p 
            LEFT JOIN servers s ON p.server_id = s.id 
            ORDER BY p.id DESC
        ");
        
        $this->renderProjects($projects);
    }
    
    private function handleProjectEdit() {
        $db = Database::getInstance();
        $id = $_GET['id'] ?? null;
        $project = null;
        
        if ($id) {
            $project = $db->fetchOne("SELECT * FROM projects WHERE id = ?", [$id]);
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'name' => $_POST['name'] ?? '',
                'repo_url' => $_POST['repo_url'] ?? '',
                'branch' => $_POST['branch'] ?? 'master',
                'deploy_path' => $_POST['deploy_path'] ?? '',
                'server_id' => $_POST['server_id'] ?? 0,
                'git_username' => trim($_POST['git_username'] ?? '') ?: null,
                'pre_deploy_script' => $_POST['pre_deploy_script'] ?? '',
                'post_deploy_script' => $_POST['post_deploy_script'] ?? '',
                'webhook_enabled' => isset($_POST['webhook_enabled']) ? 1 : 0,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // 处理 Git 密码：编辑时，如果密码为空则保留旧密码；新增时，如果为空则保存 null
            $gitPassword = trim($_POST['git_password'] ?? '');
            if ($id && $project) {
                // 编辑模式：如果密码字段为空，保留旧密码
                if ($gitPassword === '') {
                    $data['git_password'] = $project['git_password'] ?? null;
                    Logger::debug("Project edit: keeping old git_password (empty field provided)");
                } else {
                    $data['git_password'] = $gitPassword;
                    Logger::debug("Project edit: updating git_password (new password provided, length=" . strlen($gitPassword) . ")");
                }
            } else {
                // 新增模式：如果密码为空，保存 null
                $data['git_password'] = $gitPassword ?: null;
                Logger::debug("Project create: git_password=" . ($gitPassword ? "provided (length=" . strlen($gitPassword) . ")" : "null"));
            }
            
            Logger::debug("Project save: git_username=" . ($data['git_username'] ?? 'null') . ", git_password=" . (isset($data['git_password']) && $data['git_password'] ? 'provided' : 'null'));
            
            if ($id) {
                $db->update('projects', $data, 'id = ?', [$id]);
                Logger::info("Project updated: id={$id}, name={$data['name']}, git_username=" . ($data['git_username'] ?? 'null'));
            } else {
                $data['created_at'] = date('Y-m-d H:i:s');
                $id = $db->insert('projects', $data);
                Logger::info("Project created: id={$id}, name={$data['name']}, git_username=" . ($data['git_username'] ?? 'null'));
            }
            
            header('Location: ?action=projects');
            exit;
        }
        
        $servers = $db->fetchAll("SELECT * FROM servers ORDER BY id DESC");
        $this->renderProjectEdit($project, $servers);
    }
    
    private function handleProjectDelete() {
        $id = $_GET['id'] ?? null;
        if ($id) {
            $db = Database::getInstance();
            $db->delete('projects', 'id = ?', [$id]);
        }
        header('Location: ?action=projects');
        exit;
    }
    
    private function handleServers() {
        $db = Database::getInstance();
        $servers = $db->fetchAll("SELECT * FROM servers ORDER BY id DESC");
        $this->renderServers($servers);
    }
    
    private function handleServerEdit() {
        $db = Database::getInstance();
        $id = $_GET['id'] ?? null;
        $server = null;
        
        if ($id) {
            $server = $db->fetchOne("SELECT * FROM servers WHERE id = ?", [$id]);
            if ($server && isset($server['key_content'])) {
                unset($server['key_content']); // 安全：不显示密钥内容
            }
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'name' => $_POST['name'] ?? '',
                'host' => $_POST['host'] ?? '',
                'port' => intval($_POST['port'] ?? 22),
                'username' => $_POST['username'] ?? '',
                'key_path' => $_POST['key_path'] ?? '',
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // 处理密码：编辑时如果密码字段为空则保留旧密码，新增时如果为空则保存 null
            $hasNewPassword = false;
            if ($id && $server) {
                // 编辑模式：如果密码字段为空字符串，保留旧密码；如果不为空，更新为新密码
                $password = trim($_POST['password'] ?? '');
                if ($password !== '') {
                    $data['password'] = $password;
                    $hasNewPassword = true;
                    Logger::debug("Password field updated, length=" . strlen($password));
                } else {
                    // 保留原有密码
                    $data['password'] = $server['password'] ?? null;
                    $hasNewPassword = isset($server['password']) && $server['password'] !== null && $server['password'] !== '';
                    Logger::debug("Password field empty, keeping old password, old_password_length=" . (isset($server['password']) && $server['password'] !== null ? strlen($server['password']) : 0));
                }
            } else {
                // 新增模式：如果密码字段不为空，保存密码；否则保存 null
                $password = trim($_POST['password'] ?? '');
                $data['password'] = $password !== '' ? $password : null;
                $hasNewPassword = $password !== '';
                Logger::debug("New server password: " . ($password !== '' ? 'has_value, length=' . strlen($password) : 'null'));
            }
            
            // 如果有密码，清空密钥（密码认证优先）
            if ($hasNewPassword) {
                $data['key_path'] = '';
                $data['key_content'] = null;
                Logger::info("Password provided, clearing key_path and key_content (password auth takes priority)");
            } else {
                // 如果有上传密钥文件
                if (isset($_FILES['key_file']) && $_FILES['key_file']['error'] === UPLOAD_ERR_OK) {
                    $keyContent = file_get_contents($_FILES['key_file']['tmp_name']);
                    $data['key_content'] = base64_encode($keyContent);
                    Logger::info("Server edit: uploaded key file, size=" . strlen($keyContent) . " bytes");
                } elseif ($id && $server && !empty($server['key_content'])) {
                    // 保留原有密钥
                    $data['key_content'] = $server['key_content'];
                }
            }
            
            if ($id) {
                Logger::info("Updating server: id={$id}, name={$data['name']}, host={$data['host']}:{$data['port']}, has_password=" . ($data['password'] ? 'yes' : 'no'));
                $db->update('servers', $data, 'id = ?', [$id]);
            } else {
                Logger::info("Creating server: name={$data['name']}, host={$data['host']}:{$data['port']}, has_password=" . ($data['password'] ? 'yes' : 'no'));
                $data['created_at'] = date('Y-m-d H:i:s');
                $id = $db->insert('servers', $data);
            }
            
            header('Location: ?action=servers');
            exit;
        }
        
        $this->renderServerEdit($server);
    }
    
    private function handleServerDelete() {
        $id = $_GET['id'] ?? null;
        if ($id) {
            Logger::info("Deleting server: id={$id}");
            $db = Database::getInstance();
            $db->delete('servers', 'id = ?', [$id]);
        }
        header('Location: ?action=servers');
        exit;
    }
    
    private function handleServerTest() {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            Logger::warning("Server test failed: missing server ID");
            $_SESSION['flash'] = ['type' => 'error', 'message' => '缺少服务器 ID'];
            header('Location: ?action=servers');
            exit;
        }
        $db = Database::getInstance();
        $server = $db->fetchOne("SELECT * FROM servers WHERE id = ?", [$id]);
        if (!$server) {
            Logger::warning("Server test failed: server not found, id={$id}");
            $_SESSION['flash'] = ['type' => 'error', 'message' => '服务器不存在'];
            header('Location: ?action=servers');
            exit;
        }
        Logger::info("Testing server connection: id={$id}, name={$server['name']}, host={$server['host']}:{$server['port']}, user={$server['username']}");
        Logger::debug("Server password check: has_password=" . (isset($server['password']) && $server['password'] !== null && $server['password'] !== '' ? 'yes' : 'no') . ", password_length=" . (isset($server['password']) ? strlen($server['password']) : 0));
        try {
            $sshConfig = [
                'host' => $server['host'],
                'port' => $server['port'],
                'username' => $server['username'],
                'key_path' => $server['key_path'] ?? '',
                'key_content' => $server['key_content'] ?? null,
                'password' => $server['password'] ?? null, // 直接使用密码，不在这里隐藏
            ];
            // 记录配置（隐藏密码用于日志）
            $logConfig = $sshConfig;
            $logConfig['password'] = isset($sshConfig['password']) && $sshConfig['password'] !== null && $sshConfig['password'] !== '' ? '***[HIDDEN]' : null;
            Logger::debug("SSH config (password hidden for security): " . json_encode($logConfig));
            Logger::debug("Password actually used: " . (isset($sshConfig['password']) && $sshConfig['password'] !== null && $sshConfig['password'] !== '' ? 'present(' . strlen($sshConfig['password']) . ' chars)' : 'not set'));
            $exec = new SSHExecutor($sshConfig);
            $ok = $exec->testConnection();
            if ($ok) {
                Logger::info("Server connection test successful: id={$id}, name={$server['name']}");
                $_SESSION['flash'] = ['type' => 'success', 'message' => '连接成功'];
            } else {
                Logger::error("Server connection test failed: id={$id}, name={$server['name']}");
                $_SESSION['flash'] = ['type' => 'error', 'message' => '连接失败'];
            }
        } catch (Exception $e) {
            Logger::error("Server connection test exception: id={$id}, name={$server['name']}, error=" . $e->getMessage() . ", trace=" . $e->getTraceAsString());
            $_SESSION['flash'] = ['type' => 'error', 'message' => '连接失败：' . $e->getMessage()];
        }
        header('Location: ?action=servers');
        exit;
    }
    
    private function handleDeploy() {
        $projectId = $_GET['id'] ?? null;
        $branch = $_GET['branch'] ?? null;
        
        if (!$projectId || !$branch) {
            header('Location: ?action=projects');
            exit;
        }
        
        try {
            // 同步执行部署
            $deployer = new Deployer();
            $result = $deployer->deploy($projectId, $branch);
            
            if ($result['success']) {
                $_SESSION['flash'] = ['type' => 'success', 'message' => '部署成功'];
            } else {
                $_SESSION['flash'] = ['type' => 'error', 'message' => '部署失败: ' . ($result['error'] ?? '未知错误')];
            }
        } catch (Exception $e) {
            Logger::error("Deployment handler exception: " . $e->getMessage());
            $_SESSION['flash'] = ['type' => 'error', 'message' => '部署失败: ' . $e->getMessage()];
        }
        
        header('Location: ?action=deployments&project_id=' . $projectId);
        exit;
    }
    
    private function handleDeployments() {
        $db = Database::getInstance();
        $projectId = $_GET['project_id'] ?? null;
        
        $sql = "
            SELECT d.*, p.name as project_name 
            FROM deployments d 
            JOIN projects p ON d.project_id = p.id 
        ";
        $params = [];
        
        if ($projectId) {
            $sql .= " WHERE d.project_id = ?";
            $params[] = $projectId;
        }
        
        $sql .= " ORDER BY d.started_at DESC LIMIT 50";
        
        $deployments = $db->fetchAll($sql, $params);
        $this->renderDeployments($deployments);
    }
    
    private function handleWebhook() {
        // Webhook 处理逻辑
        $this->renderJson(['status' => 'ok']);
    }
    
    private function handleApi() {
        $endpoint = $_GET['endpoint'] ?? '';
        
        switch ($endpoint) {
            case 'deploy_status':
                $this->apiDeployStatus();
                break;
            case 'deploy_log':
                $this->apiDeployLog();
                break;
            default:
                $this->renderJson(['error' => 'Invalid endpoint'], 404);
        }
    }
    
    private function apiDeployStatus() {
        $deploymentId = $_GET['deployment_id'] ?? null;
        if (!$deploymentId) {
            $this->renderJson(['error' => 'Missing deployment_id'], 400);
            return;
        }
        
        $db = Database::getInstance();
        $deployment = $db->fetchOne("SELECT * FROM deployments WHERE id = ?", [$deploymentId]);
        
        $this->renderJson($deployment ?: ['error' => 'Not found'], $deployment ? 200 : 404);
    }
    
    private function apiDeployLog() {
        $deploymentId = $_GET['deployment_id'] ?? null;
        if (!$deploymentId) {
            $this->renderJson(['error' => 'Missing deployment_id'], 400);
            return;
        }
        
        $db = Database::getInstance();
        $deployment = $db->fetchOne("SELECT output, error, status FROM deployments WHERE id = ?", [$deploymentId]);
        
        if (!$deployment) {
            $this->renderJson(['error' => 'Not found'], 404);
            return;
        }
        
        // 组合输出和错误信息
        $logContent = '';
        if (!empty($deployment['output'])) {
            $logContent .= $deployment['output'];
        }
        if (!empty($deployment['error'])) {
            if (!empty($logContent)) {
                $logContent .= "\n\n";
            }
            $logContent .= "错误信息: " . $deployment['error'];
        }
        
        if (empty($logContent)) {
            $logContent = '暂无日志';
        }
        
        $this->renderJson([
            'output' => $logContent,
            'error' => $deployment['error'] ?? null,
            'status' => $deployment['status'] ?? 'unknown'
        ]);
    }
    
    // 渲染方法
    private function renderLogin($error = null) {
        $viewPath = __DIR__ . '/../ui/views/login.php';
        if (file_exists($viewPath)) {
            include $viewPath;
        } else {
            echo "View file not found: login.php";
        }
    }
    
    private function renderDashboard($projects, $deployments, $envCheck = []) {
        $viewPath = __DIR__ . '/../ui/views/dashboard.php';
        if (file_exists($viewPath)) {
            include $viewPath;
        } else {
            echo "View file not found: dashboard.php";
        }
    }
    
    private function renderProjects($projects) {
        $viewPath = __DIR__ . '/../ui/views/projects.php';
        if (file_exists($viewPath)) {
            include $viewPath;
        } else {
            echo "View file not found: projects.php";
        }
    }
    
    private function renderProjectEdit($project, $servers) {
        $viewPath = __DIR__ . '/../ui/views/project_edit.php';
        if (file_exists($viewPath)) {
            include $viewPath;
        } else {
            echo "View file not found: project_edit.php";
        }
    }
    
    private function renderServers($servers) {
        $viewPath = __DIR__ . '/../ui/views/servers.php';
        if (file_exists($viewPath)) {
            include $viewPath;
        } else {
            echo "View file not found: servers.php";
        }
    }
    
    private function renderServerEdit($server) {
        $viewPath = __DIR__ . '/../ui/views/server_edit.php';
        if (file_exists($viewPath)) {
            include $viewPath;
        } else {
            echo "View file not found: server_edit.php";
        }
    }
    
    private function renderDeployments($deployments) {
        $viewPath = __DIR__ . '/../ui/views/deployments.php';
        if (file_exists($viewPath)) {
            include $viewPath;
        } else {
            echo "View file not found: deployments.php";
        }
    }
    
    private function renderJson($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}


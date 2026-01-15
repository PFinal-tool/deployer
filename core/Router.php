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
        $publicActions = ['login', 'logout'];
        // 需要登录的路由（包括 API）
        if (!in_array($action, $publicActions) && $action !== 'login') {$this->auth->requireLogin();}
        // 如果使用默认密码，只能访问修改密码页面和退出登录
        if ($this->auth->isLoggedIn() && $this->auth->isDefaultPassword() && $action !== 'change_password' && $action !== 'logout') {header('Location: ?action=change_password&required=1');exit;}
        switch ($action) {
            case 'login':
                $this->handleLogin();break;
            case 'logout':
                $this->handleLogout();break;
            case 'dashboard':
                $this->handleDashboard();break;
            case 'projects':
                $this->handleProjects();break;
            case 'project_edit':
                $this->handleProjectEdit();break;
            case 'project_delete':
                $this->handleProjectDelete();break;
            case 'servers':
                $this->handleServers();break;
            case 'server_edit':
                $this->handleServerEdit();break;
            case 'server_delete':
                $this->handleServerDelete();break;
            case 'server_test':
                $this->handleServerTest();break;
            case 'deploy':
                $this->handleDeploy();break;
            case 'deployments':
                $this->handleDeployments();break;
            case 'webhook':
                $this->handleWebhook();break;
            case 'api':
                $this->handleApi();break;
            case 'change_password':
                $this->handleChangePassword();break;
            default:
                $this->handleDashboard();
        }
    }
    private function handleLogin() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { if (!CSRF::validate()) { Logger::warning("CSRF validation failed on login"); $error = '安全验证失败，请重新提交'; } else { try { $username = Validator::validateUsername($_POST['username'] ?? ''); $password = $_POST['password'] ?? ''; if (empty($password)) {throw new InvalidArgumentException("密码不能为空");} Logger::info("Login attempt: username={$username}"); if ($this->auth->login($username, $password)) { Logger::info("Login successful: username={$username}"); CSRF::regenerateToken(); if ($this->auth->isDefaultPassword()) {header('Location: ?action=change_password&required=1');exit;} header('Location: ?action=dashboard');exit; } else { Logger::warning("Login failed: username={$username}"); $error = '用户名或密码错误'; } } catch (InvalidArgumentException $e) { Logger::warning("Login validation failed: " . $e->getMessage()); $error = $e->getMessage(); } } }
        $this->renderLogin($error ?? null);
    }
    private function handleLogout() {
        Logger::info("User logout");
        $this->auth->logout();header('Location: ?action=login');exit;
    }
    private function handleDashboard() {
        // 检查是否使用默认密码，如果是则强制跳转到修改密码页面
        if ($this->auth->isDefaultPassword()) { header('Location: ?action=change_password&required=1'); exit; }
        $db = Database::getInstance();
        $projects = $db->fetchAll("SELECT * FROM projects ORDER BY id DESC LIMIT 10");
        $recentDeployments = $db->fetchAll("SELECT d.*, p.name as project_name FROM deployments d JOIN projects p ON d.project_id = p.id ORDER BY d.started_at DESC LIMIT 10");
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
        if ($path !== '' && file_exists($path)) { return $path; }
        // 方法2: 使用 which
        $path = trim(shell_exec('which sshpass 2>/dev/null') ?? '');
        if ($path !== '' && file_exists($path)) { return $path; }
        // 方法3: 尝试常见路径
        $commonPaths = ['/usr/bin/sshpass', '/usr/local/bin/sshpass', '/bin/sshpass'];
        foreach ($commonPaths as $commonPath) {
            if (file_exists($commonPath) && is_executable($commonPath)) { return $commonPath; }
        }
        // 方法4: 尝试直接执行 sshpass -V 来验证（最后的手段）
        $output = @shell_exec('sshpass -V 2>&1');
        if ($output !== null && strpos($output, 'sshpass') !== false) { $envPath = getenv('PATH'); if ($envPath) { $paths = explode(':', $envPath); foreach ($paths as $p) { $fullPath = rtrim($p, '/') . '/sshpass'; if (file_exists($fullPath) && is_executable($fullPath)) { return $fullPath; } } } return 'sshpass'; }
        
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
        $projects = $db->fetchAll("SELECT p.*, s.name as server_name FROM projects p LEFT JOIN servers s ON p.server_id = s.id ORDER BY p.id DESC");
        
        $this->renderProjects($projects);
    }

    private function handleProjectEdit() {
        $db = Database::getInstance();
        $id = $_GET['id'] ?? null;
        $project = null;
        if ($id) { try { $validatedId = Validator::validateProjectId($id); $project = $db->fetchOne("SELECT * FROM projects WHERE id = ?", [$validatedId]); } catch (InvalidArgumentException $e) { Logger::warning("Invalid project ID: " . $e->getMessage()); $_SESSION['flash'] = ['type' => 'error', 'message' => '无效的项目 ID']; header('Location: ?action=projects'); exit; } }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { if (!CSRF::validate()) { Logger::warning("CSRF validation failed on project edit"); $_SESSION['flash'] = ['type' => 'error', 'message' => '安全验证失败，请重新提交']; header('Location: ?action=projects'); exit; }
            try {
                // 验证输入参数
                $data = [
                    'name' => Validator::sanitizeString($_POST['name'] ?? '', 255, false),
                    'repo_url' => Validator::validateRepoUrl($_POST['repo_url'] ?? ''),
                    'branch' => Validator::validateBranch($_POST['branch'] ?? 'master'),
                    'deploy_path' => Validator::validateDeployPath($_POST['deploy_path'] ?? ''),
                    'server_id' => Validator::validateServerId($_POST['server_id'] ?? 0),
                    'git_username' => !empty(trim($_POST['git_username'] ?? '')) ? Validator::sanitizeString(trim($_POST['git_username']), 255, true) : null,
                    'pre_deploy_script' => Validator::sanitizeString($_POST['pre_deploy_script'] ?? '', 10000, true),
                    'post_deploy_script' => Validator::sanitizeString($_POST['post_deploy_script'] ?? '', 10000, true),
                    'webhook_enabled' => isset($_POST['webhook_enabled']) ? 1 : 0,
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                // 处理 Git 密码：编辑时，如果密码为空则保留旧密码；新增时，如果为空则保存 null
                $gitPassword = Validator::validatePassword($_POST['git_password'] ?? '', true);
                if ($id && $project) { if ($gitPassword === '') { $data['git_password'] = $project['git_password'] ?? null; Logger::debug("Project edit: keeping old git_password (empty field provided)"); } else { $data['git_password'] = $gitPassword; Logger::debug("Project edit: updating git_password (new password provided, length=" . strlen($gitPassword) . ")"); } } else { $data['git_password'] = $gitPassword ?: null; Logger::debug("Project create: git_password=" . ($gitPassword ? "provided (length=" . strlen($gitPassword) . ")" : "null")); }
                Logger::debug("Project save: git_username=" . ($data['git_username'] ?? 'null') . ", git_password=" . (isset($data['git_password']) && $data['git_password'] ? 'provided' : 'null'));
                if ($id) { $validatedId = Validator::validateProjectId($id); $whereClause = 'id ' . '=' . ' ' . chr(63); $db->update('projects', $data, $whereClause, [$validatedId]); Logger::info("Project updated: id={$validatedId}, name={$data['name']}, git_username=" . ($data['git_username'] ?? 'null')); } else { $data['created_at'] = date('Y-m-d H:i:s'); $id = $db->insert('projects', $data); Logger::info("Project created: id={$id}, name={$data['name']}, git_username=" . ($data['git_username'] ?? 'null')); }
                
                CSRF::regenerateToken(); // 成功后重新生成 token
                $_SESSION['flash'] = ['type' => 'success', 'message' => $id ? '项目更新成功' : '项目创建成功'];
                header('Location: ?action=projects');
                exit;
            } catch (InvalidArgumentException $e) {
                Logger::warning("Project validation failed: " . $e->getMessage());
                $_SESSION['flash'] = ['type' => 'error', 'message' => '验证失败: ' . $e->getMessage()];
            } catch (Exception $e) {
                Logger::error("Project save failed: " . $e->getMessage());
                $_SESSION['flash'] = ['type' => 'error', 'message' => '保存失败: ' . $e->getMessage()];
            }
        }
        
        $servers = $db->fetchAll("SELECT * FROM servers ORDER BY id DESC");
        $this->renderProjectEdit($project, $servers);
    }

    private function handleProjectDelete() {
        try {
            // 验证 CSRF Token（如果是 POST 请求）
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && !CSRF::validate()) { Logger::warning("CSRF validation failed on project delete"); $_SESSION['flash'] = ['type' => 'error', 'message' => '安全验证失败']; header('Location: ?action=projects'); exit; }
            
            $id = Validator::validateProjectId($_GET['id'] ?? null);
            $db = Database::getInstance();
            $whereClause = 'id ' . '=' . ' ' . chr(63);
            $db->delete('projects', $whereClause, [$id]);
            Logger::info("Project deleted: id={$id}");
            $_SESSION['flash'] = ['type' => 'success', 'message' => '项目删除成功'];
        } catch (InvalidArgumentException $e) {
            Logger::warning("Project delete validation failed: " . $e->getMessage());
            $_SESSION['flash'] = ['type' => 'error', 'message' => '无效的项目 ID'];
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
        if ($id) { try { $validatedId = Validator::validateServerId($id); $server = $db->fetchOne("SELECT * FROM servers WHERE id = ?", [$validatedId]); if ($server && isset($server['key_content'])) { unset($server['key_content']); } } catch (InvalidArgumentException $e) { Logger::warning("Invalid server ID: " . $e->getMessage()); $_SESSION['flash'] = ['type' => 'error', 'message' => '无效的服务器 ID']; header('Location: ?action=servers'); exit; } }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { if (!CSRF::validate()) { Logger::warning("CSRF validation failed on server edit"); $_SESSION['flash'] = ['type' => 'error', 'message' => '安全验证失败，请重新提交']; header('Location: ?action=servers'); exit; }
            
            try {
                // 验证输入参数
                $data = [
                    'name' => Validator::sanitizeString($_POST['name'] ?? '', 255, false),
                    'host' => Validator::validateHost($_POST['host'] ?? ''),
                    'port' => Validator::validatePort($_POST['port'] ?? 22),
                    'username' => Validator::validateUsername($_POST['username'] ?? ''),
                    'key_path' => Validator::sanitizeString($_POST['key_path'] ?? '', 500, true),
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                // 处理密码：编辑时如果密码字段为空则保留旧密码，新增时如果为空则保存 null
                $hasNewPassword = false;
                if ($id && $server) { $password = Validator::validatePassword($_POST['password'] ?? '', true); if ($password !== '') { $data['password'] = $password; $hasNewPassword = true; Logger::debug("Password field updated, length=" . strlen($password)); } else { $data['password'] = $server['password'] ?? null; $hasNewPassword = isset($server['password']) && $server['password'] !== null && $server['password'] !== ''; Logger::debug("Password field empty, keeping old password, old_password_length=" . (isset($server['password']) && $server['password'] !== null ? strlen($server['password']) : 0)); } } else { $password = Validator::validatePassword($_POST['password'] ?? '', true); $data['password'] = $password !== '' ? $password : null; $hasNewPassword = $password !== ''; Logger::debug("New server password: " . ($password !== '' ? 'has_value, length=' . strlen($password) : 'null')); }
                // 如果有密码，清空密钥（密码认证优先）
                if ($hasNewPassword) { $data['key_path'] = ''; $data['key_content'] = null; Logger::info("Password provided, clearing key_path and key_content (password auth takes priority)"); } else { if (isset($_FILES['key_file']) && $_FILES['key_file']['error'] === UPLOAD_ERR_OK) { $keyContent = file_get_contents($_FILES['key_file']['tmp_name']); $data['key_content'] = base64_encode($keyContent); Logger::info("Server edit: uploaded key file, size=" . strlen($keyContent) . " bytes"); } elseif ($id && $server && !empty($server['key_content'])) { $data['key_content'] = $server['key_content']; } }
                if ($id) { $validatedId = Validator::validateServerId($id); Logger::info("Updating server: id={$validatedId}, name={$data['name']}, host={$data['host']}:{$data['port']}, has_password=" . ($data['password'] ? 'yes' : 'no')); $whereClause = 'id ' . '=' . ' ' . chr(63); $db->update('servers', $data, $whereClause, [$validatedId]); } else { Logger::info("Creating server: name={$data['name']}, host={$data['host']}:{$data['port']}, has_password=" . ($data['password'] ? 'yes' : 'no')); $data['created_at'] = date('Y-m-d H:i:s'); $id = $db->insert('servers', $data); }
                
                CSRF::regenerateToken(); // 成功后重新生成 token
                $_SESSION['flash'] = ['type' => 'success', 'message' => $id ? '服务器更新成功' : '服务器创建成功'];
                header('Location: ?action=servers');
                exit;
            } catch (InvalidArgumentException $e) {
                Logger::warning("Server validation failed: " . $e->getMessage());
                $_SESSION['flash'] = ['type' => 'error', 'message' => '验证失败: ' . $e->getMessage()];
            } catch (Exception $e) {
                Logger::error("Server save failed: " . $e->getMessage());
                $_SESSION['flash'] = ['type' => 'error', 'message' => '保存失败: ' . $e->getMessage()];
            }
        }
        
        $servers = $db->fetchAll("SELECT * FROM servers ORDER BY id DESC");
        $this->renderServerEdit($server);
    }

    private function handleServerDelete() {
        try {
            // 验证 CSRF Token（如果是 POST 请求）
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && !CSRF::validate()) { Logger::warning("CSRF validation failed on server delete"); $_SESSION['flash'] = ['type' => 'error', 'message' => '安全验证失败']; header('Location: ?action=servers'); exit; }
            
            $id = Validator::validateServerId($_GET['id'] ?? null);
            Logger::info("Deleting server: id={$id}");
            $db = Database::getInstance();
            $whereClause = 'id ' . '=' . ' ' . chr(63);
            $db->delete('servers', $whereClause, [$id]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => '服务器删除成功'];
        } catch (InvalidArgumentException $e) {
            Logger::warning("Server delete validation failed: " . $e->getMessage());
            $_SESSION['flash'] = ['type' => 'error', 'message' => '无效的服务器 ID'];
        }
        header('Location: ?action=servers');
        exit;
    }

    private function handleServerTest() {
        try {
            $id = Validator::validateServerId($_GET['id'] ?? null);
            $db = Database::getInstance();
            $server = $db->fetchOne("SELECT * FROM servers WHERE id = ?", [$id]);
            if (!$server) { Logger::warning("Server test failed: server not found, id={$id}"); $_SESSION['flash'] = ['type' => 'error', 'message' => '服务器不存在']; header('Location: ?action=servers'); exit; }
            
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
                if ($ok) { Logger::info("Server connection test successful: id={$id}, name={$server['name']}"); $_SESSION['flash'] = ['type' => 'success', 'message' => '连接成功']; } else { Logger::error("Server connection test failed: id={$id}, name={$server['name']}"); $_SESSION['flash'] = ['type' => 'error', 'message' => '连接失败']; }
            } catch (Exception $e) {
                Logger::error("Server connection test exception: id={$id}, name={$server['name']}, error=" . $e->getMessage() . ", trace=" . $e->getTraceAsString());
                $_SESSION['flash'] = ['type' => 'error', 'message' => '连接失败：' . $e->getMessage()];
            }
        } catch (InvalidArgumentException $e) {
            Logger::warning("Server test validation failed: " . $e->getMessage());
            $_SESSION['flash'] = ['type' => 'error', 'message' => '无效的服务器 ID'];
        }
        header('Location: ?action=servers');
        exit;
    }

    private function handleDeploy() {
        // 设置执行时间限制（部署可能需要较长时间）
        set_time_limit(600); // 10 分钟
        
        try {
            // 验证输入参数
            $projectId = Validator::validateProjectId($_GET['id'] ?? null);
            
            // 如果指定了 branch 参数，使用指定的；否则使用项目默认的
            $branch = null;
            if (isset($_GET['branch']) && !empty(trim($_GET['branch']))) { $branch = Validator::validateBranch(trim($_GET['branch'])); } else { $db = Database::getInstance(); $project = $db->fetchOne("SELECT branch FROM projects WHERE id = ?", [$projectId]); if ($project) { $branch = $project['branch']; } else { throw new InvalidArgumentException("项目不存在"); } }
            // 验证 CSRF Token（如果是 POST 请求）
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && !CSRF::validate()) { Logger::warning("CSRF validation failed on deploy"); $_SESSION['flash'] = ['type' => 'error', 'message' => '安全验证失败，请重新提交']; header('Location: ?action=projects'); exit; }
            // 同步执行部署
            $deployer = new Deployer();
            $result = $deployer->deploy($projectId, $branch);
            if ($result['success']) { $_SESSION['flash'] = ['type' => 'success', 'message' => '部署成功']; } else { $_SESSION['flash'] = ['type' => 'error', 'message' => '部署失败: ' . ($result['error'] ?? '未知错误')]; }
        } catch (InvalidArgumentException $e) {
            Logger::warning("Deploy validation failed: " . $e->getMessage());
            $_SESSION['flash'] = ['type' => 'error', 'message' => '参数错误: ' . $e->getMessage()];
            header('Location: ?action=projects');
            exit;
        } catch (Exception $e) {
            Logger::error("Deployment handler exception: " . $e->getMessage());
            $_SESSION['flash'] = ['type' => 'error', 'message' => '部署失败: ' . $e->getMessage()];
        }
        
        header('Location: ?action=deployments&project_id=' . ($projectId ?? ''));
        exit;
    }

    private function handleDeployments() {
        $db = Database::getInstance();
        $projectId = $_GET['project_id'] ?? null;
        
        $sql = "SELECT d.*, p.name as project_name FROM deployments d JOIN projects p ON d.project_id = p.id";
        $params = [];
        if ($projectId) { $sql .= " WHERE d.project_id = ?"; $params[] = $projectId; }
        
        $sql .= " ORDER BY d.started_at DESC LIMIT 50";
        
        $deployments = $db->fetchAll($sql, $params);
        $this->renderDeployments($deployments);
    }

    private function handleWebhook() {
        // Webhook 处理逻辑
        $this->renderJson(['status' => 'ok']);
    }

    private function handleChangePassword() {
        // 需要登录才能修改密码
        $this->auth->requireLogin();
        
        $error = null;
        $success = false;
        $required = isset($_GET['required']) && $_GET['required'] == '1';
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { if (!CSRF::validate()) { Logger::warning("CSRF validation failed on change password"); $error = '安全验证失败，请重新提交'; } else {
                try {
                    $oldPassword = Validator::validatePassword($_POST['old_password'] ?? '', false);
                    $newPassword = Validator::validatePassword($_POST['new_password'] ?? '', false);
                    $confirmPassword = Validator::validatePassword($_POST['confirm_password'] ?? '', false);
                    
                    // 验证必填字段（validatePassword已经验证了，这里保留用于兼容性）
                    if (empty($oldPassword)) { throw new InvalidArgumentException("旧密码不能为空"); }
                    if (empty($newPassword)) { throw new InvalidArgumentException("新密码不能为空"); }
                    if (empty($confirmPassword)) { throw new InvalidArgumentException("确认密码不能为空"); }
                    // 验证新密码和确认密码是否一致
                    if ($newPassword !== $confirmPassword) { throw new InvalidArgumentException("新密码和确认密码不一致"); }
                    // 验证新密码长度
                    if (strlen($newPassword) < 8) { throw new InvalidArgumentException("新密码长度至少需要 8 个字符"); }
                    // 如果使用默认密码，允许使用旧密码 'admin' 或当前密码
                    $userId = $this->auth->getUserId();
                    if ($this->auth->isDefaultPassword()) { if ($oldPassword !== 'admin') { $user = Database::getInstance()->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]); if (!$user || !password_verify($oldPassword, $user['password'])) { throw new InvalidArgumentException("旧密码不正确"); } } if ($newPassword === 'admin') { throw new InvalidArgumentException("新密码不能使用默认密码"); } $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT); Database::getInstance()->update('users', ['password' => $hashedPassword, 'is_default_password' => 0], 'id ' . '=' . ' ' . chr(63), [$userId]); $_SESSION['is_default_password'] = false; Logger::info("Password changed successfully for user ID: {$userId} (default password user)"); CSRF::regenerateToken(); if ($required) { $_SESSION['flash'] = ['type' => 'success', 'message' => '密码修改成功！']; header('Location: ?action=dashboard'); exit; } $success = true; $error = null; } else { $this->auth->changePassword($userId, $oldPassword, $newPassword); Logger::info("Password changed successfully for user ID: {$userId}"); CSRF::regenerateToken(); $success = true; $error = null; }
                    
                } catch (InvalidArgumentException $e) { Logger::warning("Change password validation failed: " . $e->getMessage()); $error = $e->getMessage(); } catch (Exception $e) { Logger::error("Change password failed: " . $e->getMessage()); $error = '修改密码失败: ' . $e->getMessage(); } } }
        
        $this->renderChangePassword($error, $success, $required);
    }

    private function handleApi() {
        // API 端点需要认证
        $this->auth->requireLogin();
        
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
        try {
            $deploymentId = Validator::validateDeploymentId($_GET['deployment_id'] ?? null);
            $db = Database::getInstance();
            $deployment = $db->fetchOne("SELECT * FROM deployments WHERE id = ?", [$deploymentId]);
            
            $this->renderJson($deployment ?: ['error' => 'Not found'], $deployment ? 200 : 404);
        } catch (InvalidArgumentException $e) {
            Logger::warning("API deploy status validation failed: " . $e->getMessage());
            $this->renderJson(['error' => $e->getMessage()], 400);
        }
    }

    private function apiDeployLog() {
        try {
            $deploymentId = Validator::validateDeploymentId($_GET['deployment_id'] ?? null);
            $db = Database::getInstance();
            $deployment = $db->fetchOne("SELECT output, error, status FROM deployments WHERE id = ?", [$deploymentId]);
            if (!$deployment) { $this->renderJson(['error' => 'Not found'], 404); return; }
            // 组合输出和错误信息
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

    // 渲染方法
    private function renderLogin($error = null) {
        if (class_exists('ViewRenderer')) { ViewRenderer::render('login', ['error' => $error]); } else { $viewPath = __DIR__ . '/../ui/views/login.php'; if (file_exists($viewPath)) { include $viewPath; } else { echo "View file not found: login.php"; } }
    }

    private function renderDashboard($projects, $deployments, $envCheck = []) {
        if (class_exists('ViewRenderer')) { ViewRenderer::render('dashboard', ['projects' => $projects, 'deployments' => $deployments, 'envCheck' => $envCheck]); } else { $viewPath = __DIR__ . '/../ui/views/dashboard.php'; if (file_exists($viewPath)) { include $viewPath; } else { echo "View file not found: dashboard.php"; } }
    }

    private function renderProjects($projects) {
        if (class_exists('SecurityOutput')) { $projects = SecurityOutput::escapeArray($projects); }
        if (class_exists('ViewRenderer')) { ViewRenderer::render('projects', ['projects' => $projects]); } else { $viewPath = __DIR__ . '/../ui/views/projects.php'; if (file_exists($viewPath)) { include $viewPath; } else { echo "View file not found: projects.php"; } }
    }

    private function renderProjectEdit($project, $servers) {
        if (class_exists('SecurityOutput')) { if ($project) { $project = SecurityOutput::escapeArray($project); } $servers = SecurityOutput::escapeArray($servers); }
        if (class_exists('ViewRenderer')) { ViewRenderer::render('project_edit', ['project' => $project, 'servers' => $servers]); } else { $viewPath = __DIR__ . '/../ui/views/project_edit.php'; if (file_exists($viewPath)) { include $viewPath; } else { echo "View file not found: project_edit.php"; } }
    }

    private function renderServers($servers) {
        if (class_exists('SecurityOutput')) { $servers = SecurityOutput::escapeArray($servers); }
        if (class_exists('ViewRenderer')) { ViewRenderer::render('servers', ['servers' => $servers]); } else { $viewPath = __DIR__ . '/../ui/views/servers.php'; if (file_exists($viewPath)) { include $viewPath; } else { echo "View file not found: servers.php"; } }
    }

    private function renderServerEdit($server) {
        if (class_exists('SecurityOutput') && $server) { $server = SecurityOutput::escapeArray($server); }
        if (class_exists('ViewRenderer')) { ViewRenderer::render('server_edit', ['server' => $server]); } else { $viewPath = __DIR__ . '/../ui/views/server_edit.php'; if (file_exists($viewPath)) { include $viewPath; } else { echo "View file not found: server_edit.php"; } }
    }

    private function renderChangePassword($error = null, $success = false, $required = false) {
        if (class_exists('ViewRenderer')) { ViewRenderer::render('change_password', ['error' => $error, 'success' => $success, 'required' => $required]); } else { $viewPath = __DIR__ . '/../ui/views/change_password.php'; if (file_exists($viewPath)) { include $viewPath; } else { echo "View file not found: change_password.php"; } }
    }
    private function renderDeployments($deployments) {
        if (class_exists('SecurityOutput')) { $deployments = SecurityOutput::escapeArray($deployments); }
        if (class_exists('ViewRenderer')) { ViewRenderer::render('deployments', ['deployments' => $deployments]); } else { $viewPath = __DIR__ . '/../ui/views/deployments.php'; if (file_exists($viewPath)) { include $viewPath; } else { echo "View file not found: deployments.php"; } }
    }
    private function renderJson($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}


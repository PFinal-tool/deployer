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
            
            if ($this->auth->login($username, $password)) {
                header('Location: ?action=dashboard');
                exit;
            } else {
                $error = '用户名或密码错误';
            }
        }
        
        $this->renderLogin($error ?? null);
    }
    
    private function handleLogout() {
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
        
        $this->renderDashboard($projects, $recentDeployments);
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
                'pre_deploy_script' => $_POST['pre_deploy_script'] ?? '',
                'post_deploy_script' => $_POST['post_deploy_script'] ?? '',
                'webhook_enabled' => isset($_POST['webhook_enabled']) ? 1 : 0,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            if ($id) {
                $db->update('projects', $data, 'id = ?', [$id]);
            } else {
                $data['created_at'] = date('Y-m-d H:i:s');
                $id = $db->insert('projects', $data);
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
            
            // 如果有上传密钥文件
            if (isset($_FILES['key_file']) && $_FILES['key_file']['error'] === UPLOAD_ERR_OK) {
                $keyContent = file_get_contents($_FILES['key_file']['tmp_name']);
                $data['key_content'] = base64_encode($keyContent);
            } elseif ($id && $server && !empty($server['key_content'])) {
                // 保留原有密钥
                $data['key_content'] = $server['key_content'];
            }
            
            if ($id) {
                $db->update('servers', $data, 'id = ?', [$id]);
            } else {
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
            $db = Database::getInstance();
            $db->delete('servers', 'id = ?', [$id]);
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
        
        // 异步执行部署（实际应该是后台任务）
        $deployer = new Deployer();
        $result = $deployer->deploy($projectId, $branch);
        
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
        $deployment = $db->fetchOne("SELECT output, error FROM deployments WHERE id = ?", [$deploymentId]);
        
        $this->renderJson($deployment ?: ['error' => 'Not found'], $deployment ? 200 : 404);
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
    
    private function renderDashboard($projects, $deployments) {
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


<?php
/**
 * 服务器控制器
 */
class ServerController extends BaseController {
    
    /**
     * 服务器列表
     */
    public function index() {
        $this->requireLogin();
        
        $servers = $this->db->fetchAll("SELECT * FROM servers ORDER BY id DESC");
        if (class_exists('SecurityOutput')) { $servers = SecurityOutput::escapeArray($servers); }
        
        $this->renderServers($servers);
    }
    
    /**
     * 编辑服务器
     */
    public function edit() {
        $this->requireLogin();
        
        $id = $_GET['id'] ?? null;
        $server = null;
        if ($id) { try { $validatedId = Validator::validateServerId($id); $server = $this->db->fetchOne("SELECT * FROM servers WHERE id = ?", [$validatedId]); if ($server && isset($server['key_content'])) { unset($server['key_content']); } } catch (InvalidArgumentException $e) { Logger::warning("Invalid server ID: " . $e->getMessage()); $this->flash('error', '无效的服务器 ID'); $this->redirect('servers'); } }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { if (!CSRF::validate()) { Logger::warning("CSRF validation failed on server edit"); $this->flash('error', '安全验证失败，请重新提交'); $this->redirect('servers'); }
            
            try {
                $data = [
                    'name' => Validator::sanitizeString($_POST['name'] ?? '', 255, false),
                    'host' => Validator::validateHost($_POST['host'] ?? ''),
                    'port' => Validator::validatePort($_POST['port'] ?? 22),
                    'username' => Validator::validateUsername($_POST['username'] ?? ''),
                    'key_path' => Validator::sanitizeString($_POST['key_path'] ?? '', 500, true),
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                $hasNewPassword = false;
                if ($id && $server) { $password = Validator::validatePassword($_POST['password'] ?? '', true); if ($password !== '') { $data['password'] = $password; $hasNewPassword = true; Logger::debug("Password field updated, length=" . strlen($password)); } else { $data['password'] = $server['password'] ?? null; $hasNewPassword = isset($server['password']) && $server['password'] !== null && $server['password'] !== ''; Logger::debug("Password field empty, keeping old password"); } } else { $password = Validator::validatePassword($_POST['password'] ?? '', true); $data['password'] = $password !== '' ? $password : null; $hasNewPassword = $password !== ''; Logger::debug("New server password: " . ($password !== '' ? 'has_value' : 'null')); }
                if ($hasNewPassword) { $data['key_path'] = ''; $data['key_content'] = null; Logger::info("Password provided, clearing key_path and key_content"); } else { if (isset($_FILES['key_file']) && $_FILES['key_file']['error'] === UPLOAD_ERR_OK) { $keyContent = file_get_contents($_FILES['key_file']['tmp_name']); $data['key_content'] = base64_encode($keyContent); Logger::info("Server edit: uploaded key file, size=" . strlen($keyContent) . " bytes"); } elseif ($id && $server && !empty($server['key_content'])) { $data['key_content'] = $server['key_content']; } }
                if ($id) { $validatedId = Validator::validateServerId($id); Logger::info("Updating server: id={$validatedId}, name={$data['name']}"); $whereClause = 'id = ?'; $this->db->update('servers', $data, $whereClause, [$validatedId]); if (class_exists('AuditLogger')) { AuditLogger::logServer('updated', $validatedId, $data['name']); } } else { Logger::info("Creating server: name={$data['name']}"); $data['created_at'] = date('Y-m-d H:i:s'); $id = $this->db->insert('servers', $data); if (class_exists('AuditLogger')) { AuditLogger::logServer('created', $id, $data['name']); } }
                
                CSRF::regenerateToken();
                $this->flash('success', $id ? '服务器更新成功' : '服务器创建成功');
                $this->redirect('servers');
            } catch (InvalidArgumentException $e) {
                Logger::warning("Server validation failed: " . $e->getMessage());
                $this->flash('error', '验证失败: ' . $e->getMessage());
            } catch (Exception $e) {
                Logger::error("Server save failed: " . $e->getMessage());
                $this->flash('error', '保存失败: ' . $e->getMessage());
            }
        }
        
        $this->renderServerEdit($server);
    }
    
    /**
     * 删除服务器
     */
    public function delete() {
        $this->requireLogin();
        
        try {
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && !CSRF::validate()) { Logger::warning("CSRF validation failed on server delete"); $this->flash('error', '安全验证失败'); $this->redirect('servers'); }
            $id = Validator::validateServerId($_GET['id'] ?? null);
            $server = $this->db->fetchOne("SELECT name FROM servers WHERE id = ?", [$id]);
            Logger::info("Deleting server: id={$id}");
            $whereClause = 'id = ?';
            $this->db->delete('servers', $whereClause, [$id]);
            if (class_exists('AuditLogger')) { AuditLogger::logServer('deleted', $id, $server['name'] ?? null); }
            $this->flash('success', '服务器删除成功');
        } catch (InvalidArgumentException $e) {
            Logger::warning("Server delete validation failed: " . $e->getMessage());
            $this->flash('error', '无效的服务器 ID');
        }
        
        $this->redirect('servers');
    }
    
    /**
     * 测试服务器连接
     */
    public function test() {
        $this->requireLogin();
        
        try {
            $id = Validator::validateServerId($_GET['id'] ?? null);
            $server = $this->db->fetchOne("SELECT * FROM servers WHERE id = ?", [$id]);
            if (!$server) { Logger::warning("Server test failed: server not found, id={$id}"); $this->flash('error', '服务器不存在'); $this->redirect('servers'); }
            Logger::info("Testing server connection: id={$id}, name={$server['name']}");
            try {
                $sshConfig = ['host' => $server['host'], 'port' => $server['port'], 'username' => $server['username'], 'key_path' => $server['key_path'] ?? '', 'key_content' => $server['key_content'] ?? null, 'password' => $server['password'] ?? null];
                $exec = new SSHExecutor($sshConfig);
                $ok = $exec->testConnection();
                if ($ok) { Logger::info("Server connection test successful: id={$id}"); $this->flash('success', '连接成功'); } else { Logger::error("Server connection test failed: id={$id}"); $this->flash('error', '连接失败'); }
            } catch (Exception $e) {
                Logger::error("Server connection test exception: id={$id}, error=" . $e->getMessage());
                $this->flash('error', '连接失败：' . $e->getMessage());
            }
        } catch (InvalidArgumentException $e) {
            Logger::warning("Server test validation failed: " . $e->getMessage());
            $this->flash('error', '无效的服务器 ID');
        }
        
        $this->redirect('servers');
    }
    
    /**
     * 渲染服务器列表
     */
    private function renderServers($servers) {
        if (class_exists('ViewRenderer')) { ViewRenderer::render('servers', ['servers' => $servers]); } else { $viewPath = __DIR__ . '/../../ui/views/servers.php'; if (file_exists($viewPath)) { include $viewPath; } else { echo "View file not found: servers.php"; } }
    }
    
    /**
     * 渲染服务器编辑页面
     */
    private function renderServerEdit($server) {
        if (class_exists('SecurityOutput') && $server) { $server = SecurityOutput::escapeArray($server); }
        if (class_exists('ViewRenderer')) { ViewRenderer::render('server_edit', ['server' => $server]); } else { $viewPath = __DIR__ . '/../../ui/views/server_edit.php'; if (file_exists($viewPath)) { include $viewPath; } else { echo "View file not found: server_edit.php"; } }
    }
}

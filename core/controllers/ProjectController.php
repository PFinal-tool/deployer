<?php
/**
 * 项目控制器
 */
class ProjectController extends BaseController {
    
    /**
     * 项目列表
     */
    public function index() {
        $this->requireLogin();
        
        $projects = $this->db->fetchAll("SELECT p.*, s.name as server_name FROM projects p LEFT JOIN servers s ON p.server_id = s.id ORDER BY p.id DESC");
        if (class_exists('SecurityOutput')) { $projects = SecurityOutput::escapeArray($projects); }
        
        $this->renderProjects($projects);
    }
    
    /**
     * 编辑项目
     */
    public function edit() {
        $this->requireLogin();
        
        $id = $_GET['id'] ?? null;
        $project = null;
        if ($id) { try { $validatedId = Validator::validateProjectId($id); $project = $this->db->fetchOne("SELECT * FROM projects WHERE id = ?", [$validatedId]); } catch (InvalidArgumentException $e) { Logger::warning("Invalid project ID: " . $e->getMessage()); $this->flash('error', '无效的项目 ID'); $this->redirect('projects'); } }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { if (!CSRF::validate()) { Logger::warning("CSRF validation failed on project edit"); $this->flash('error', '安全验证失败，请重新提交'); $this->redirect('projects'); }
            
            try {
                $data = [
                    'name' => Validator::sanitizeString($_POST['name'] ?? '', 255, false),
                    'repo_url' => Validator::validateRepoUrl($_POST['repo_url'] ?? ''),
                    'branch' => Validator::validateBranch($_POST['branch'] ?? 'master'),
                    'deploy_path' => Validator::validateDeployPath($_POST['deploy_path'] ?? ''),
                    'server_id' => Validator::validateServerId($_POST['server_id'] ?? 0),
                    'git_username' => !empty(trim($_POST['git_username'] ?? '')) 
                        ? Validator::sanitizeString(trim($_POST['git_username']), 255, true) 
                        : null,
                    'pre_deploy_script' => Validator::sanitizeString($_POST['pre_deploy_script'] ?? '', 10000, true),
                    'post_deploy_script' => Validator::sanitizeString($_POST['post_deploy_script'] ?? '', 10000, true),
                    'webhook_enabled' => isset($_POST['webhook_enabled']) ? 1 : 0,
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                $gitPassword = Validator::validatePassword($_POST['git_password'] ?? '', true);
                if ($id && $project) { if ($gitPassword === '') { $data['git_password'] = $project['git_password'] ?? null; Logger::debug("Project edit: keeping old git_password (empty field provided)"); } else { $data['git_password'] = $gitPassword; Logger::debug("Project edit: updating git_password (new password provided, length=" . strlen($gitPassword) . ")"); } } else { $data['git_password'] = $gitPassword ?: null; Logger::debug("Project create: git_password=" . ($gitPassword ? "provided (length=" . strlen($gitPassword) . ")" : "null")); }
                Logger::debug("Project save: git_username=" . ($data['git_username'] ?? 'null') . ", git_password=" . (isset($data['git_password']) && $data['git_password'] ? 'provided' : 'null'));
                if ($id) { $validatedId = Validator::validateProjectId($id); $whereClause = 'id = ?'; $this->db->update('projects', $data, $whereClause, [$validatedId]); Logger::info("Project updated: id={$validatedId}, name={$data['name']}, git_username=" . ($data['git_username'] ?? 'null')); if (class_exists('AuditLogger')) { AuditLogger::logProject('updated', $validatedId, $data['name']); } } else { $data['created_at'] = date('Y-m-d H:i:s'); $id = $this->db->insert('projects', $data); Logger::info("Project created: id={$id}, name={$data['name']}, git_username=" . ($data['git_username'] ?? 'null')); if (class_exists('AuditLogger')) { AuditLogger::logProject('created', $id, $data['name']); } }
                
                CSRF::regenerateToken();
                $this->flash('success', $id ? '项目更新成功' : '项目创建成功');
                $this->redirect('projects');
            } catch (InvalidArgumentException $e) {
                Logger::warning("Project validation failed: " . $e->getMessage());
                $this->flash('error', '验证失败: ' . $e->getMessage());
            } catch (Exception $e) {
                Logger::error("Project save failed: " . $e->getMessage());
                $this->flash('error', '保存失败: ' . $e->getMessage());
            }
        }
        
        $servers = $this->db->fetchAll("SELECT * FROM servers ORDER BY id DESC");
        if (class_exists('SecurityOutput')) { if ($project) { $project = SecurityOutput::escapeArray($project); } $servers = SecurityOutput::escapeArray($servers); }
        
        $this->renderProjectEdit($project, $servers);
    }
    
    /**
     * 删除项目
     */
    public function delete() {
        $this->requireLogin();
        
        try {
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && !CSRF::validate()) { Logger::warning("CSRF validation failed on project delete"); $this->flash('error', '安全验证失败'); $this->redirect('projects'); }
            
            $id = Validator::validateProjectId($_GET['id'] ?? null);
            $project = $this->db->fetchOne("SELECT name FROM projects WHERE id = ?", [$id]);
            $whereClause = 'id = ?';
            $this->db->delete('projects', $whereClause, [$id]);
            Logger::info("Project deleted: id={$id}");
            if (class_exists('AuditLogger')) { AuditLogger::logProject('deleted', $id, $project['name'] ?? null); }
            $this->flash('success', '项目删除成功');
        } catch (InvalidArgumentException $e) {
            Logger::warning("Project delete validation failed: " . $e->getMessage());
            $this->flash('error', '无效的项目 ID');
        }
        
        $this->redirect('projects');
    }
    
    /**
     * 渲染项目列表
     */
    private function renderProjects($projects) {
        if (class_exists('ViewRenderer')) { ViewRenderer::render('projects', ['projects' => $projects]); } else { $viewPath = __DIR__ . '/../../ui/views/projects.php'; if (file_exists($viewPath)) { include $viewPath; } else { echo "View file not found: projects.php"; } }
    }
    
    /**
     * 渲染项目编辑页面
     */
    private function renderProjectEdit($project, $servers) {
        if (class_exists('ViewRenderer')) { ViewRenderer::render('project_edit', ['project' => $project, 'servers' => $servers]); } else { $viewPath = __DIR__ . '/../../ui/views/project_edit.php'; if (file_exists($viewPath)) { include $viewPath; } else { echo "View file not found: project_edit.php"; } }
    }
}

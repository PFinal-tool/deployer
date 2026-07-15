<?php
class ProjectController extends BaseController {

    public function index(): void {
        $this->requireLogin();
        $projects = $this->db->fetchAll(
            'SELECT p.*, s.name as server_name FROM projects p LEFT JOIN servers s ON p.server_id = s.id ORDER BY p.id DESC'
        );
        $this->view('projects', ['projects' => $projects]);
    }

    public function edit(): void {
        $this->requireLogin();
        $id = $_GET['id'] ?? null;
        $project = null;

        if ($id) {
            try {
                $project = $this->db->fetchOne('SELECT * FROM projects WHERE id = ?', [Validator::validateProjectId($id)]);
            } catch (InvalidArgumentException $e) {
                $this->flash('error', '无效的项目 ID');
                $this->redirect('projects');
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CSRF::validate()) {
                $this->flash('error', '安全验证失败');
                $this->redirect('projects');
            }
            try {
                $data = fn_project_form_data($_POST, $project);
                if ($id) {
                    $validatedId = Validator::validateProjectId($id);
                    $this->db->update('projects', $data, 'id = ?', [$validatedId]);
                    AuditLogger::logProject('updated', $validatedId, $data['name']);
                } else {
                    $data['created_at'] = date('Y-m-d H:i:s');
                    $newId = $this->db->insert('projects', $data);
                    AuditLogger::logProject('created', $newId, $data['name']);
                }
                CSRF::regenerateToken();
                $this->flash('success', $id ? '项目更新成功' : '项目创建成功');
                $this->redirect('projects');
            } catch (Exception $e) {
                $this->flash('error', '保存失败: ' . $e->getMessage());
            }
        }

        $servers = $this->db->fetchAll('SELECT * FROM servers ORDER BY id DESC');
        $webhookUrl = '';
        if ($project && ($project['webhook_enabled'] ?? 0) && !empty($project['webhook_token'])) {
            $webhookUrl = fn_webhook_url((int)$project['id'], $project['webhook_token']);
        }
        $this->view('project_edit', compact('project', 'servers', 'webhookUrl'));
    }

    public function delete(): void {
        $this->requireLogin();
        $this->requirePost();
        try {
            $this->requireCsrf();
            $id = Validator::validateProjectId($_POST['id'] ?? null);
            $project = $this->db->fetchOne('SELECT name FROM projects WHERE id = ?', [$id]);
            $this->db->delete('projects', 'id = ?', [$id]);
            AuditLogger::logProject('deleted', $id, $project['name'] ?? null);
            $this->flash('success', '项目删除成功');
        } catch (Exception $e) {
            $this->flash('error', $e->getMessage());
        }
        $this->redirect('projects');
    }
}

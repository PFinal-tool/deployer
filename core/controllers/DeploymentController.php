<?php
class DeploymentController extends BaseController {

    public function dashboard(): void {
        $this->requireLogin();
        if ($this->auth->isDefaultPassword()) {
            $this->redirect('change_password', ['required' => 1]);
        }

        $projects = $this->db->fetchAll('SELECT * FROM projects ORDER BY id DESC LIMIT 10');
        $deployments = $this->db->fetchAll(
            'SELECT d.id, d.project_id, d.branch, d.commit_hash, d.commit_message, d.status, d.started_at, d.finished_at, p.name AS project_name
             FROM deployments d JOIN projects p ON d.project_id = p.id ORDER BY d.started_at DESC LIMIT 10'
        );
        $envCheck = fn_check_environment(fn_storage_dir());
        $envSummary = fn_env_summary($envCheck);

        $this->view('dashboard', compact('projects', 'deployments', 'envCheck', 'envSummary'));
    }

    public function index(): void {
        $this->requireLogin();
        $projectId = $_GET['project_id'] ?? null;
        $sql = 'SELECT d.id, d.project_id, d.branch, d.commit_hash, d.commit_message, d.status, d.started_at, d.finished_at, p.name AS project_name
                FROM deployments d JOIN projects p ON d.project_id = p.id';
        $params = [];
        if ($projectId) {
            $sql .= ' WHERE d.project_id = ?';
            $params[] = Validator::validateProjectId($projectId);
        }
        $sql .= ' ORDER BY d.started_at DESC LIMIT 50';
        $highlightId = null;
        if (!empty($_GET['deployment_id'])) {
            try {
                $highlightId = Validator::validateDeploymentId($_GET['deployment_id']);
            } catch (InvalidArgumentException $e) {
                $highlightId = null;
            }
        }
        $this->view('deployments', [
            'deployments' => $this->db->fetchAll($sql, $params),
            'highlightId' => $highlightId,
        ]);
    }

    public function deploy(): void {
        $this->requireLogin();
        $this->requirePost();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        set_time_limit(600);

        $projectId = null;
        $deploymentId = null;
        try {
            $this->requireCsrf();
            RateLimiter::check('deploy');
            $projectId = Validator::validateProjectId($_POST['id'] ?? null);
            $branch = null;
            if (!empty(trim($_POST['branch'] ?? ''))) {
                $branch = Validator::validateBranch(trim($_POST['branch']));
            } else {
                $project = $this->db->fetchOne('SELECT branch FROM projects WHERE id = ?', [$projectId]);
                if (!$project) {
                    throw new InvalidArgumentException('项目不存在');
                }
                $branch = $project['branch'];
            }

            AuditLogger::logDeployment('started', null, $projectId, $branch);

            $result = (new Deployer())->deploy($projectId, $branch);
            $deploymentId = $result['deployment_id'] ?? null;

            if ($deploymentId) {
                AuditLogger::logDeployment($result['success'] ? 'completed' : 'failed', $deploymentId, $projectId, $branch);
            }

            $this->flash($result['success'] ? 'success' : 'error',
                $result['success'] ? '部署成功' : '部署失败: ' . ($result['error'] ?? '未知错误'));
        } catch (Exception $e) {
            $this->flash('error', $e->getMessage());
        }

        $params = ['project_id' => $projectId ?? ''];
        if ($deploymentId) {
            $params['deployment_id'] = $deploymentId;
        }
        $this->redirect('deployments', $params);
    }

    public function rollback(): void {
        $this->requireLogin();
        $this->requirePost();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        set_time_limit(600);

        $projectId = null;
        try {
            $this->requireCsrf();
            RateLimiter::check('deploy');
            $projectId = Validator::validateProjectId($_POST['project_id'] ?? null);
            $commitHash = trim($_POST['commit_hash'] ?? '');
            if ($commitHash === '' || !preg_match('/^[a-f0-9]{7,40}$/i', $commitHash)) {
                throw new InvalidArgumentException('无效的 commit hash');
            }

            $result = (new Deployer())->rollback($projectId, $commitHash);
            $this->flash($result['success'] ? 'success' : 'error',
                $result['success'] ? '回滚成功' : '回滚失败: ' . ($result['error'] ?? '未知错误'));
        } catch (Exception $e) {
            $this->flash('error', $e->getMessage());
        }

        $this->redirect('deployments', ['project_id' => $projectId ?? '']);
    }

    public function webhook(): void {
        $projectId = $_GET['project_id'] ?? null;
        $token = $_GET['token'] ?? '';
        if (!$projectId || !$token) {
            $this->renderJson(['error' => 'Missing parameters'], 400);
        }

        try {
            RateLimiter::check('webhook');
            $validatedId = Validator::validateProjectId($projectId);
            $project = $this->db->fetchOne(
                'SELECT * FROM projects WHERE id = ? AND webhook_enabled = 1',
                [$validatedId]
            );
            if (!$project || empty($project['webhook_token']) || !hash_equals($project['webhook_token'], $token)) {
                $this->renderJson(['error' => 'Unauthorized'], 403);
            }

            $payload = file_get_contents('php://input');
            if (!fn_webhook_validate_signature($project, $payload)) {
                $this->renderJson(['error' => 'Invalid signature'], 403);
            }

            $branch = fn_webhook_extract_branch($payload) ?: $project['branch'];
            AuditLogger::logDeployment('webhook', null, $validatedId, $branch);
            $result = (new Deployer())->deploy($validatedId, $branch);

            $this->renderJson(
                $result['success']
                    ? ['status' => 'ok', 'deployment_id' => $result['deployment_id']]
                    : ['status' => 'error', 'message' => $result['error'] ?? 'unknown'],
                $result['success'] ? 200 : 500
            );
        } catch (Exception $e) {
            $this->renderJson(['error' => $e->getMessage()], 400);
        }
    }
}

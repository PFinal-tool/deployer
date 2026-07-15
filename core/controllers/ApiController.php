<?php
class ApiController extends BaseController {

    public function handle(): void {
        $this->requireLogin();
        RateLimiter::check('api');
        $endpoint = $_GET['endpoint'] ?? '';

        if ($endpoint === 'deploy_status') {
            $this->deployStatus();
        } elseif ($endpoint === 'deploy_log') {
            $this->deployLog();
        } else {
            $this->renderJson(['error' => 'Invalid endpoint'], 404);
        }
    }

    private function deployStatus(): void {
        try {
            $deploymentId = Validator::validateDeploymentId($_GET['deployment_id'] ?? null);
            $deployment = $this->db->fetchOne(
                'SELECT id, project_id, branch, commit_hash, status, started_at, finished_at FROM deployments WHERE id = ?',
                [$deploymentId]
            );
            $this->renderJson($deployment ?: ['error' => 'Not found'], $deployment ? 200 : 404);
        } catch (InvalidArgumentException $e) {
            $this->renderJson(['error' => $e->getMessage()], 400);
        }
    }

    private function deployLog(): void {
        try {
            $deploymentId = Validator::validateDeploymentId($_GET['deployment_id'] ?? null);
            $deployment = $this->db->fetchOne('SELECT output, error, status FROM deployments WHERE id = ?', [$deploymentId]);
            if (!$deployment) {
                $this->renderJson(['error' => 'Not found'], 404);
            }
            $this->renderJson([
                'output' => fn_deploy_log_text($deployment),
                'error' => $deployment['error'] ?? null,
                'status' => $deployment['status'] ?? 'unknown',
            ]);
        } catch (InvalidArgumentException $e) {
            $this->renderJson(['error' => $e->getMessage()], 400);
        }
    }
}

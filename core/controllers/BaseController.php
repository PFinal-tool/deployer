<?php
/**
 * 控制器基类（薄编排层）
 */
abstract class BaseController {
    protected $auth;
    protected $db;

    public function __construct() {
        $this->auth = new Auth();
        $this->db = Database::getInstance();
    }

    protected function requireLogin(): void {
        $this->auth->requireLogin();
    }

    protected function requirePost(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            exit('Method Not Allowed');
        }
    }

    protected function requireCsrf(): void {
        if (!CSRF::validate()) {
            throw new InvalidArgumentException('安全验证失败');
        }
    }

    protected function view(string $name, array $vars = []): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        render_view($name, $vars);
    }

    protected function renderJson($data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    protected function redirect(string $action, array $params = []): void {
        $url = '?action=' . $action;
        if (!empty($params)) {
            $url .= '&' . http_build_query($params);
        }
        header('Location: ' . $url);
        exit;
    }

    protected function flash(string $type, string $message): void {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }
}

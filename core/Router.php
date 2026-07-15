<?php
/**
 * 路由处理（仅分发，无业务逻辑）
 */
class Router {
    private $auth;
    private $controllers = [];

    private static $routes = [
        'login' => ['auth', 'login'],
        'logout' => ['auth', 'logout'],
        'change_password' => ['auth', 'changePassword'],
        'dashboard' => ['deployment', 'dashboard'],
        'projects' => ['project', 'index'],
        'project_edit' => ['project', 'edit'],
        'project_delete' => ['project', 'delete'],
        'servers' => ['server', 'index'],
        'server_edit' => ['server', 'edit'],
        'server_delete' => ['server', 'delete'],
        'server_test' => ['server', 'test'],
        'deploy' => ['deployment', 'deploy'],
        'rollback' => ['deployment', 'rollback'],
        'deployments' => ['deployment', 'index'],
        'webhook' => ['deployment', 'webhook'],
        'api' => ['api', 'handle'],
    ];

    public function __construct() {
        $this->auth = new Auth();
    }

    private function getController(string $name) {
        if (!isset($this->controllers[$name])) {
            $className = ucfirst($name) . 'Controller';
            if (class_exists($className)) {
                $this->controllers[$name] = new $className();
            }
        }
        return $this->controllers[$name] ?? null;
    }

    public function handle(): void {
        $action = $_GET['action'] ?? 'dashboard';

        if (!in_array($action, ['login', 'logout', 'webhook'], true)) {
            $this->auth->requireLogin();
        }

        if ($this->auth->isLoggedIn() && $this->auth->isDefaultPassword()
            && !in_array($action, ['change_password', 'logout'], true)) {
            header('Location: ?action=change_password&required=1');
            exit;
        }

        $this->dispatch($action);
    }

    private function dispatch(string $action): void {
        if (!isset(self::$routes[$action])) {
            $this->getController('deployment')->dashboard();
            return;
        }

        [$controllerName, $method] = self::$routes[$action];
        $controller = $this->getController($controllerName);

        if ($controller && method_exists($controller, $method)) {
            $controller->$method();
            return;
        }

        http_response_code(404);
        echo 'Not found';
    }
}

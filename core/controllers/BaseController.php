<?php
/**
 * 控制器基类
 * 提供公共方法和属性
 */
abstract class BaseController {
    protected $auth;
    protected $db;
    
    public function __construct() {
        $this->auth = new Auth();
        $this->db = Database::getInstance();
    }
    
    /**
     * 要求登录
     */
    protected function requireLogin() {
        $this->auth->requireLogin();
    }
    
    /**
     * 返回 JSON 响应
     */
    protected function renderJson($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    /**
     * 重定向
     */
    protected function redirect($action, $params = []) {
        $url = '?action=' . $action;
        if (!empty($params)) { $url .= '&' . http_build_query($params); }
        header('Location: ' . $url);
        exit;
    }
    
    /**
     * 设置 Flash 消息
     */
    protected function flash($type, $message) {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }
    
    /**
     * 获取当前用户 ID
     */
    protected function getUserId() {
        return $this->auth->getUserId();
    }
    
    /**
     * 获取当前用户名
     */
    protected function getUsername() {
        return $this->auth->getUsername();
    }
}

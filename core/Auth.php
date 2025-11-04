<?php
/**
 * 用户认证类
 */
class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
        
        if (session_status() === PHP_SESSION_NONE) {
            // 配置会话安全选项
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_strict_mode', 1);
            ini_set('session.cookie_samesite', 'Strict');
            
            // 如果使用 HTTPS，启用 secure cookie
            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
                ini_set('session.cookie_secure', 1);
            }
            
            // 配置会话名称
            session_name('deployer_session');
            
            session_start();
            
            // 定期重新生成会话 ID（防止会话固定攻击）
            if (!isset($_SESSION['created'])) {
                $_SESSION['created'] = time();
            } elseif (time() - $_SESSION['created'] > 1800) { // 30 分钟
                session_regenerate_id(true);
                $_SESSION['created'] = time();
            }
        }
    }
    
    public function login($username, $password) {
        $user = $this->db->fetchOne(
            "SELECT * FROM users WHERE username = ?",
            [$username]
        );
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            Logger::info("User {$username} logged in");
            return true;
        }
        
        Logger::warning("Failed login attempt for username: {$username}");
        return false;
    }
    
    public function logout() {
        $username = $_SESSION['username'] ?? 'unknown';
        session_destroy();
        Logger::info("User {$username} logged out");
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    public function getUsername() {
        return $_SESSION['username'] ?? null;
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: ?action=login');
            exit;
        }
    }
    
    public function createUser($username, $password) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            $id = $this->db->insert('users', [
                'username' => $username,
                'password' => $hashedPassword
            ]);
            Logger::info("User {$username} created");
            return $id;
        } catch (PDOException $e) {
            Logger::error("Failed to create user: " . $e->getMessage());
            return false;
        }
    }
}


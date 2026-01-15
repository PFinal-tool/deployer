<?php
/**
 * 用户认证类
 */
class Auth {
    private $db;
    private static $maxLoginAttempts = 5;
    private static $lockoutTime = 900; // 15分钟
    
    public function __construct() {
        $this->db = Database::getInstance();
        
        if (session_status() === PHP_SESSION_NONE) { if (isset($_COOKIE['deployer_session'])) { $sessionId = $_COOKIE['deployer_session']; if (!preg_match('/^[a-zA-Z0-9,-]+$/', $sessionId)) { unset($_COOKIE['deployer_session']); Logger::warning("Invalid session ID detected: " . substr($sessionId, 0, 10) . "..."); } } ini_set('session.cookie_httponly', 1); ini_set('session.use_strict_mode', 1); ini_set('session.cookie_samesite', 'Strict'); if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') { ini_set('session.cookie_secure', 1); } session_name('deployer_session'); session_start(); if (!isset($_SESSION['created'])) { $_SESSION['created'] = time(); } elseif (time() - $_SESSION['created'] > 1800) { session_regenerate_id(true); $_SESSION['created'] = time(); Logger::debug("Session ID regenerated for security"); } }
    }
    
    public function login($username, $password) {
        // 检查是否被锁定
        if ($this->isLocked($username)) { $lockoutRemaining = $this->getLockoutRemaining($username); throw new InvalidArgumentException("账户已被锁定，请在 {$lockoutRemaining} 秒后重试"); }
        
        $user = $this->db->fetchOne(
            "SELECT * FROM users WHERE username = ?",
            [$username]
        );
        
        if ($user && password_verify($password, $user['password'])) { $this->clearLoginAttempts($username); $_SESSION['user_id'] = $user['id']; $_SESSION['username'] = $user['username']; $_SESSION['is_default_password'] = ($user['is_default_password'] ?? 0) ==1; Logger::info("User {$username} logged in"); return true; }
        
        // 登录失败，记录失败次数
        $this->recordFailedLogin($username);
        Logger::warning("Failed login attempt for username: {$username}");
        return false;
    }
    
    /**
     * 记录登录失败
     */
    private function recordFailedLogin($username) {
        $key = "login_attempts_{$username}";
        $attempts = $_SESSION[$key] ?? 0;
        $attempts++;
        $_SESSION[$key] = $attempts;
        $_SESSION["{$key}_time"] = time();
        
        if ($attempts >= self::$maxLoginAttempts) { $_SESSION["locked_{$username}"] = time(); Logger::warning("Account locked due to too many failed attempts: {$username}"); }
    }
    
    /**
     * 检查账户是否被锁定
     */
    private function isLocked($username) {
        $lockTime = $_SESSION["locked_{$username}"] ?? 0;
        if ($lockTime === 0) { return false; }
        if (time() - $lockTime > self::$lockoutTime) { unset($_SESSION["locked_{$username}"]); $this->clearLoginAttempts($username); return false; }
        return true;
    }
    
    /**
     * 获取锁定剩余时间
     */
    private function getLockoutRemaining($username) {
        $lockTime = $_SESSION["locked_{$username}"] ?? 0;
        if ($lockTime === 0) { return 0; }
        $remaining = self::$lockoutTime - (time() - $lockTime);
        return max(0, $remaining);
    }
    
    /**
     * 清除登录失败记录
     */
    private function clearLoginAttempts($username) {
        unset($_SESSION["login_attempts_{$username}"]);
        unset($_SESSION["login_attempts_{$username}_time"]);
    }
    
    /**
     * 检查是否使用默认密码
     */
    public function isDefaultPassword() {
        if (!$this->isLoggedIn()) { return false; }
        return $_SESSION['is_default_password'] ?? false;
    }
    
    /**
     * 修改密码
     */
    public function changePassword($userId, $oldPassword, $newPassword) {
        // 验证旧密码
        $user = $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId]);
        if (!$user || !password_verify($oldPassword, $user['password'])) { throw new InvalidArgumentException("旧密码不正确"); }
        // 验证新密码
        if (empty($newPassword)) { throw new InvalidArgumentException("新密码不能为空"); }
        if (strlen($newPassword) < 8) { throw new InvalidArgumentException("新密码长度至少需要 8 个字符"); }
        
        // 更新密码
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $this->db->update('users', [
            'password' => $hashedPassword,
            'is_default_password' => 0
        ], 'id = ?', [$userId]);
        
        // 更新 session
        $_SESSION['is_default_password'] = false;
        
        Logger::info("User {$user['username']} changed password");
        return true;
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
        if (!$this->isLoggedIn()) { header('Location: ?action=login'); exit; }
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


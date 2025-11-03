<?php
/**
 * 用户认证类
 */
class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
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


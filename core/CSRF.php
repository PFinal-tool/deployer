<?php
/**
 * CSRF 防护类
 * 用于防止跨站请求伪造攻击
 */
class CSRF {
    private static $sessionKey = 'csrf_token';
    private static $tokenName = '_token';
    
    /**
     * 初始化会话（如果尚未启动）
     */
    private static function ensureSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    /**
     * 生成 CSRF Token
     * 
     * @return string CSRF Token
     */
    public static function generateToken(): string {
        self::ensureSession();
        
        if (!isset($_SESSION[self::$sessionKey])) {
            $_SESSION[self::$sessionKey] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION[self::$sessionKey];
    }
    
    /**
     * 验证 CSRF Token
     * 
     * @param string|null $token 要验证的 token，如果为 null 则从 POST 或 GET 中获取
     * @return bool 验证是否通过
     */
    public static function validate(?string $token = null): bool {
        self::ensureSession();
        
        if (!isset($_SESSION[self::$sessionKey])) {
            Logger::warning("CSRF validation failed: no token in session");
            return false;
        }
        
        $sessionToken = $_SESSION[self::$sessionKey];
        
        // 如果未提供 token，尝试从请求中获取
        if ($token === null) {
            $token = $_POST[self::$tokenName] ?? $_GET[self::$tokenName] ?? null;
        }
        
        if ($token === null) {
            Logger::warning("CSRF validation failed: no token provided");
            return false;
        }
        
        // 使用 hash_equals 进行时间安全的比较
        $valid = hash_equals($sessionToken, $token);
        
        if (!$valid) {
            Logger::warning("CSRF validation failed: token mismatch");
        }
        
        return $valid;
    }
    
    /**
     * 生成 CSRF Token 隐藏字段的 HTML
     * 
     * @return string HTML 隐藏字段
     */
    public static function field(): string {
        $token = self::generateToken();
        return '<input type="hidden" name="' . htmlspecialchars(self::$tokenName) . '" value="' . htmlspecialchars($token) . '">';
    }
    
    /**
     * 获取 CSRF Token（用于 AJAX 请求）
     * 
     * @return string CSRF Token
     */
    public static function token(): string {
        return self::generateToken();
    }
    
    /**
     * 验证并刷新 Token（用于关键操作后）
     */
    public static function regenerateToken(): void {
        self::ensureSession();
        $_SESSION[self::$sessionKey] = bin2hex(random_bytes(32));
    }
    
    /**
     * 检查请求是否需要 CSRF 验证
     * 
     * @param string $method HTTP 方法
     * @return bool 是否需要验证
     */
    public static function requiresValidation(string $method): bool {
        // POST、PUT、DELETE、PATCH 等修改操作需要验证
        return in_array(strtoupper($method), ['POST', 'PUT', 'DELETE', 'PATCH']);
    }
}


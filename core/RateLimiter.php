<?php
/**
 * 请求频率限制类
 * 用于防止暴力破解和滥用
 */
class RateLimiter {
    private static $limits = [
        'login' => ['max' => 5, 'window' => 300],
        'deploy' => ['max' => 10, 'window' => 3600],
        'api' => ['max' => 100, 'window' => 3600],
        'webhook' => ['max' => 60, 'window' => 3600],
    ];
    
    /**
     * 检查请求频率
     * 
     * @param string $action 操作类型
     * @param string|null $identifier 标识符（默认使用IP地址）
     * @return bool 是否允许请求
     * @throws Exception 如果超过限制
     */
    public static function check($action, $identifier = null) {
        if (!isset(self::$limits[$action])) { return true; }
        if (session_status() === PHP_SESSION_NONE) { session_start(); }
        
        $identifier = $identifier ?: ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $key = "rate_limit_{$action}_{$identifier}";
        
        $limit = self::$limits[$action];
        $current = $_SESSION[$key] ?? ['count' => 0, 'reset' => time()];
        
        if (time() > $current['reset']) { $current = ['count' => 0, 'reset' => time() + $limit['window']]; }
        if ($current['count'] >= $limit['max']) { $remaining = $current['reset'] - time(); Logger::warning("Rate limit exceeded: action={$action}, identifier={$identifier}, remaining={$remaining}s"); throw new Exception("请求过于频繁，请在 {$remaining} 秒后重试"); }
        
        $current['count']++;
        $_SESSION[$key] = $current;
        
        return true;
    }
    
    /**
     * 清除指定操作的频率限制
     * 
     * @param string $action 操作类型
     * @param string|null $identifier 标识符
     */
    public static function clear($action, $identifier = null) {
        if (session_status() === PHP_SESSION_NONE) { session_start(); }
        
        $identifier = $identifier ?: ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $key = "rate_limit_{$action}_{$identifier}";
        unset($_SESSION[$key]);
    }
    
    /**
     * 获取剩余请求次数
     * 
     * @param string $action 操作类型
     * @param string|null $identifier 标识符
     * @return array ['remaining' => int, 'reset' => int]
     */
    public static function getRemaining($action, $identifier = null) {
        if (!isset(self::$limits[$action])) { return ['remaining' => PHP_INT_MAX, 'reset' => 0]; }
        if (session_status() === PHP_SESSION_NONE) { session_start(); }
        
        $identifier = $identifier ?: ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $key = "rate_limit_{$action}_{$identifier}";
        
        $limit = self::$limits[$action];
        $current = $_SESSION[$key] ?? ['count' => 0, 'reset' => time() + $limit['window']];
        
        if (time() > $current['reset']) { return ['remaining' => $limit['max'], 'reset' => time() + $limit['window']]; }
        
        return [
            'remaining' => max(0, $limit['max'] - $current['count']),
            'reset' => $current['reset']
        ];
    }
}

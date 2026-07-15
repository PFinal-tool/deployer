<?php
/**
 * 操作审计日志类
 * 记录关键操作以便审计和追踪
 */
class AuditLogger {
    private static $logFile = null;
    
    /**
     * 初始化审计日志
     */
    public static function init() {
        $logDir = fn_storage_dir() . '/logs';
        if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
        self::$logFile = $logDir . '/audit_' . date('Y-m-d') . '.log';
    }
    
    /**
     * 记录审计日志
     * 
     * @param string $action 操作类型
     * @param array $details 详细信息
     */
    public static function log($action, $details = []) {
        if (self::$logFile === null) { self::init(); }
        
        $user = $_SESSION['username'] ?? 'anonymous';
        $userId = $_SESSION['user_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $requestUri = $_SERVER['REQUEST_URI'] ?? 'unknown';
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'unknown';
        
        $log = [
            'timestamp' => date('Y-m-d H:i:s'),
            'user' => $user,
            'user_id' => $userId,
            'action' => $action,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'request_method' => $requestMethod,
            'request_uri' => $requestUri,
            'details' => $details
        ];
        
        // 写入审计日志文件
        $logLine = json_encode($log, JSON_UNESCAPED_UNICODE) . PHP_EOL;
        @file_put_contents(self::$logFile, $logLine, FILE_APPEND);
        
        // 同时写入普通日志
        Logger::info("AUDIT: {$action} by {$user} (IP: {$ip})");
    }
    
    /**
     * 记录登录操作
     */
    public static function logLogin($username, $success, $reason = null) {
        self::log('login', [
            'username' => $username,
            'success' => $success,
            'reason' => $reason
        ]);
    }
    
    /**
     * 记录项目操作
     */
    public static function logProject($action, $projectId, $projectName = null) {
        self::log("project_{$action}", [
            'project_id' => $projectId,
            'project_name' => $projectName
        ]);
    }
    
    /**
     * 记录服务器操作
     */
    public static function logServer($action, $serverId, $serverName = null) {
        self::log("server_{$action}", [
            'server_id' => $serverId,
            'server_name' => $serverName
        ]);
    }
    
    /**
     * 记录部署操作
     */
    public static function logDeployment($action, $deploymentId, $projectId, $branch = null) {
        self::log("deployment_{$action}", [
            'deployment_id' => $deploymentId,
            'project_id' => $projectId,
            'branch' => $branch
        ]);
    }
    
    /**
     * 记录密码修改操作
     */
    public static function logPasswordChange($userId, $username) {
        self::log('password_change', [
            'user_id' => $userId,
            'username' => $username
        ]);
    }
    
    /**
     * 查询审计日志
     * 
     * @param array $filters 过滤条件 ['action' => string, 'user' => string, 'date' => string]
     * @param int $limit 限制条数
     * @return array 日志记录数组
     */
    public static function query($filters = [], $limit = 100) {
        if (self::$logFile === null) { self::init(); }
        
        $logs = [];
        $files = glob(fn_storage_dir() . '/logs/audit_*.log');
        
        // 按日期排序，最新的在前
        rsort($files);
        
        foreach ($files as $file) {
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            foreach ($lines as $line) {
                $log = json_decode($line, true);
                if (!$log) continue;
                // 应用过滤
                $match = true;
                if (isset($filters['action']) && $log['action'] !== $filters['action']) { $match = false; }
                if (isset($filters['user']) && $log['user'] !== $filters['user']) { $match = false; }
                if (isset($filters['date']) && strpos($log['timestamp'], $filters['date']) !== 0) { $match = false; }
                if ($match) { $logs[] = $log; if (count($logs) >= $limit) { break 2; } }
            }
            if (count($logs) >= $limit) { break; }
        }
        
        return $logs;
    }
}

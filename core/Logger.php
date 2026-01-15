<?php
/**
 * 日志记录类
 */
class Logger {
    private static $logFile = null;private static $logDir = null;
    public static function init($logDir = null) {
        if ($logDir === null) {$logDir = __DIR__ . '/../storage/logs';}
        if (!is_dir($logDir)) {@mkdir($logDir, 0755, true);}
        self::$logDir = $logDir;
        self::$logFile = $logDir . '/deployer_' . date('Y-m-d') . '.log';
        
        // 1% 的概率触发清理（避免每次请求都扫描文件）
        if (rand(1, 100) === 1) { self::cleanOldLogs(); }
    }
    
    // 新增清理方法
    private static function cleanOldLogs() {
        $files = glob(self::$logDir . '/deployer_*.log');
        $now = time();
        foreach ($files as $file) {
            if (is_file($file) && $now - filemtime($file) > 30 * 86400) { @unlink($file); }
        }
    }
    public static function log($message, $level = 'INFO') {if (self::$logFile === null) { self::init();}$timestamp = date('Y-m-d H:i:s');$logMessage = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;@file_put_contents(self::$logFile, $logMessage, FILE_APPEND);}
    public static function info($message) { self::log($message, 'INFO');}
    public static function error($message) { self::log($message, 'ERROR');}
    public static function warning($message) { self::log($message, 'WARNING');}
    public static function debug($message) {self::log($message, 'DEBUG');}
    public static function getLogs($lines = 100) {if (self::$logFile === null || !file_exists(self::$logFile)) {return [];}$content = file_get_contents(self::$logFile);$logLines = explode(PHP_EOL, $content);$logLines = array_filter($logLines);return array_slice($logLines, -$lines);}
}


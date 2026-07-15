<?php
/**
 * 日志记录类
 */
class Logger {
    private static $logFile = null;
    private static $logDir = null;
    private static $buffer = [];
    private static $bufferSize = 10; // 缓冲10条后写入
    private static $initialized = false;
    
    public static function init($logDir = null) {
        if (self::$initialized) { return; }
        if ($logDir === null) { $logDir = fn_storage_dir() . '/logs'; }
        if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
        self::$logDir = $logDir;
        self::$logFile = $logDir . '/deployer_' . date('Y-m-d') . '.log';
        register_shutdown_function([self::class, 'flush']);
        if (rand(1, 100) === 1) { self::cleanOldLogs(); }
        self::$initialized = true;
    }
    
    // 新增清理方法
    private static function cleanOldLogs() {
        $files = glob(self::$logDir . '/deployer_*.log');
        $now = time();
        foreach ($files as $file) {
            if (is_file($file) && $now - filemtime($file) > 30 * 86400) { @unlink($file); }
        }
    }
    
    public static function log($message, $level = 'INFO') {
        if (self::$logFile === null) { self::init(); }
        $timestamp = date('Y-m-d H:i:s');
        self::$buffer[] = "[{$timestamp}] [{$level}] {$message}";
        if (count(self::$buffer) >= self::$bufferSize || $level === 'ERROR') { self::flush(); }
    }
    
    /**
     * 刷新缓冲区，将日志写入文件
     */
    public static function flush() {
        if (empty(self::$buffer)) { return; }
        if (self::$logFile === null) { self::init(); }
        $content = implode(PHP_EOL, self::$buffer) . PHP_EOL;
        @file_put_contents(self::$logFile, $content, FILE_APPEND);
        self::$buffer = [];
    }
    
    public static function info($message) {
        self::log($message, 'INFO');
    }
    
    public static function error($message) {
        self::log($message, 'ERROR');
    }
    
    public static function warning($message) {
        self::log($message, 'WARNING');
    }
    
    public static function debug($message) {
        self::log($message, 'DEBUG');
    }
    
    public static function getLogs($lines = 100) {
        if (self::$logFile === null || !file_exists(self::$logFile)) { return []; }
        $content = file_get_contents(self::$logFile);
        $logLines = explode(PHP_EOL, $content);
        $logLines = array_filter($logLines);
        return array_slice($logLines, -$lines);
    }
}


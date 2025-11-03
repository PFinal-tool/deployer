<?php
/**
 * SSH 驱动（类似 Adminer 的数据库驱动）
 */
class SSHDriver {
    public static function getSupportedDrivers() {
        return ['ssh'];
    }
    
    public static function testConnection($config) {
        try {
            $sshExecutor = new SSHExecutor($config);
            return $sshExecutor->testConnection();
        } catch (Exception $e) {
            return false;
        }
    }
    
    public static function executeCommand($config, $command) {
        $sshExecutor = new SSHExecutor($config);
        return $sshExecutor->execute($command);
    }
}


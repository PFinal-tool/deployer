<?php
/**
 * Composer 插件
 */
class ComposerPlugin implements PluginInterface {
    public function shouldRun($project) {
        // 简化处理：总是尝试运行，如果失败会在 execute 中处理
        // 实际部署时会在服务器上检查 composer.json 是否存在
        return true;
    }
    
    public function execute($sshExecutor, $project) {
        // 检查是否存在 composer.json
        $checkCommand = sprintf(
            "test -f %s/composer.json && echo 'exists' || echo 'not_exists'",
            escapeshellarg($project['deploy_path'])
        );
        
        try {
            $result = trim($sshExecutor->execute($checkCommand));
            if ($result !== 'exists') {
                return "Composer skipped: composer.json not found";
            }
            
            $command = sprintf(
                "cd %s && composer install --no-dev --optimize-autoloader --no-interaction",
                escapeshellarg($project['deploy_path'])
            );
            
            return $sshExecutor->execute($command);
        } catch (Exception $e) {
            return "Composer install failed: " . $e->getMessage();
        }
    }
}


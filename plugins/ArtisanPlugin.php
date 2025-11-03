<?php
/**
 * Laravel Artisan 插件
 */
class ArtisanPlugin implements PluginInterface {
    public function shouldRun($project) {
        // 简化处理：总是尝试运行，如果失败会在 execute 中处理
        return true;
    }
    
    public function execute($sshExecutor, $project) {
        // 检查是否存在 artisan 文件
        $checkCommand = sprintf(
            "test -f %s/artisan && echo 'exists' || echo 'not_exists'",
            escapeshellarg($project['deploy_path'])
        );
        
        try {
            $result = trim($sshExecutor->execute($checkCommand));
            if ($result !== 'exists') {
                return "Artisan skipped: artisan file not found";
            }
        } catch (Exception $e) {
            return "Artisan check failed: " . $e->getMessage();
        }
        
        $output = [];
        
        $commands = [
            'php artisan config:cache',
            'php artisan route:cache',
            'php artisan view:cache',
            'php artisan migrate --force'
        ];
        
        foreach ($commands as $cmd) {
            $fullCommand = sprintf(
                "cd %s && %s",
                escapeshellarg($project['deploy_path']),
                $cmd
            );
            
            try {
                $result = $sshExecutor->execute($fullCommand);
                $output[] = "✓ {$cmd}";
                $output[] = $result;
            } catch (Exception $e) {
                $output[] = "✗ {$cmd} failed: " . $e->getMessage();
            }
        }
        
        return implode("\n", $output);
    }
}


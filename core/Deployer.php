<?php
/**
 * 核心部署类
 */
class Deployer {
    private $db;
    private $plugins = [];
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->loadPlugins();
    }
    
    /**
     * 加载插件
     */
    private function loadPlugins() {
        // 加载内置插件
        if (class_exists('ComposerPlugin')) {
            $this->plugins[] = new ComposerPlugin();
        }
        if (class_exists('ArtisanPlugin')) {
            $this->plugins[] = new ArtisanPlugin();
        }
    }
    
    /**
     * 执行部署
     */
    public function deploy($projectId, $branch = null) {
        $deploymentId = null;
        
        try {
            // 获取项目信息
            $project = $this->db->fetchOne("SELECT * FROM projects WHERE id = ?", [$projectId]);
            
            if (!$project) {
                throw new Exception("Project not found");
            }
            
            if ($branch === null) {
                $branch = $project['branch'];
            }
            
            // 获取服务器信息
            $server = $this->db->fetchOne("SELECT * FROM servers WHERE id = ?", [$project['server_id']]);
            
            if (!$server) {
                throw new Exception("Server not found");
            }
            
            // 创建部署记录
            $deploymentId = $this->db->insert('deployments', [
                'project_id' => $projectId,
                'branch' => $branch,
                'status' => 'running',
                'started_at' => date('Y-m-d H:i:s')
            ]);
            
            Logger::info("Starting deployment: project_id={$projectId}, branch={$branch}, deployment_id={$deploymentId}");
            
        } catch (Exception $e) {
            // 如果连部署记录都无法创建，记录错误并返回
            Logger::error("Failed to initialize deployment: " . $e->getMessage());
            throw $e;
        }
        
        try {
            // 初始化 SSH 执行器
            $sshConfig = [
                'host' => $server['host'],
                'port' => $server['port'],
                'username' => $server['username'],
                'key_path' => $server['key_path'],
                'key_content' => $server['key_content'] ?? null,
                'password' => $server['password'] ?? null
            ];
            
            $sshExecutor = new SSHExecutor($sshConfig);
            
            // 初始化 Git 部署器
            $gitDeployer = new GitDeployer(
                $sshExecutor,
                $project['repo_url'],
                $branch,
                $project['deploy_path'],
                $project['git_username'] ?? null,
                $project['git_password'] ?? null
            );
            
            // 记录 Git 认证信息状态（用于调试）
            $gitUsername = $project['git_username'] ?? null;
            $gitPassword = isset($project['git_password']) && $project['git_password'] ? 'provided' : 'empty';
            Logger::debug("GitDeployer initialized: repo_url=" . $project['repo_url'] . ", git_username=" . ($gitUsername ?: 'null') . ", git_password=" . $gitPassword);
            
            // 检查 Git 认证信息是否完整
            if (strpos($project['repo_url'], 'https://') === 0 && (!$gitUsername || !$project['git_password'])) {
                Logger::warning("Git HTTPS URL requires authentication but credentials incomplete: git_username=" . ($gitUsername ?: 'null') . ", git_password=" . $gitPassword);
            }
            
            $output = [];
            $output[] = "=== Deployment Started ===";
            $output[] = "Project: {$project['name']}";
            $output[] = "Branch/Tag: {$branch}";
            $output[] = "Path: {$project['deploy_path']}";
            $output[] = "";
            
            try {
                // 执行部署前脚本
                if (!empty($project['pre_deploy_script'])) {
                    $output[] = "=== Pre-deploy Script ===";
                    $output[] = $this->executeScript($sshExecutor, $project['deploy_path'], $project['pre_deploy_script']);
                    $output[] = "";
                }
                
                // 执行 Git 部署
                $output[] = "=== Git Operations ===";
                $gitResult = $gitDeployer->deploy();
                $output[] = $gitResult['output'];
                $output[] = "";
                
                // 执行插件任务
                $output[] = "=== Running Plugins ===";
                foreach ($this->plugins as $plugin) {
                    if ($plugin->shouldRun($project)) {
                        $output[] = "Running plugin: " . get_class($plugin);
                        $pluginOutput = $plugin->execute($sshExecutor, $project);
                        $output[] = $pluginOutput;
                        $output[] = "";
                    }
                }
                
                // 执行部署后脚本
                if (!empty($project['post_deploy_script'])) {
                    $output[] = "=== Post-deploy Script ===";
                    $output[] = $this->executeScript($sshExecutor, $project['deploy_path'], $project['post_deploy_script']);
                    $output[] = "";
                }
                
                $output[] = "=== Deployment Completed ===";
                
                // 更新部署记录为成功
                $this->db->update('deployments', [
                    'status' => 'success',
                    'commit_hash' => $gitResult['commit_hash'],
                    'commit_message' => $gitResult['commit_message'],
                    'output' => implode("\n", $output),
                    'finished_at' => date('Y-m-d H:i:s')
                ], 'id = ?', [$deploymentId]);
                
                Logger::info("Deployment status updated to success: deployment_id={$deploymentId}");
                
                Logger::info("Deployment completed successfully: project_id={$projectId}");
                
                return [
                    'success' => true,
                    'deployment_id' => $deploymentId,
                    'output' => implode("\n", $output)
                ];
                
            } catch (Exception $e) {
                $error = $e->getMessage();
                $output[] = "";
                $output[] = "=== Deployment Failed ===";
                $output[] = "Error: {$error}";
                
                // 检查是否是 Git 认证问题，给出更友好的提示
                if (strpos($error, 'could not read Username') !== false || strpos($error, 'authentication') !== false) {
                    $output[] = "";
                    $output[] = "=== 解决方案 ===";
                    $output[] = "Git 仓库需要认证信息，但未配置。请按以下步骤操作：";
                    $output[] = "1. 进入项目列表页面";
                    $output[] = "2. 点击编辑项目";
                    $output[] = "3. 填写 'Git 用户名' 和 'Git 密码' 字段";
                    $output[] = "4. 保存后重新部署";
                    $output[] = "";
                    $output[] = "注意：对于 HTTPS 私有仓库，必须填写用户名和密码/访问令牌";
                }
                
                // 只在开发环境显示详细错误信息（生产环境隐藏）
                $isDevelopment = getenv('APP_ENV') === 'development' 
                    || ini_get('display_errors') 
                    || (defined('APP_DEBUG') && APP_DEBUG);
                
                if ($isDevelopment) {
                    $output[] = "";
                    $output[] = "Error Details:";
                    $output[] = "File: " . $e->getFile();
                    $output[] = "Line: " . $e->getLine();
                    $output[] = "Trace:";
                    $output[] = $e->getTraceAsString();
                } else {
                    $output[] = "";
                    $output[] = "提示: 详细错误信息已记录到日志文件，请联系管理员查看";
                }
                
                Logger::error("Deployment failed: {$error}");
                Logger::error("Deployment error details: " . $e->getFile() . ":" . $e->getLine() . "\n" . $e->getTraceAsString());
                
                // 更新部署记录为失败，同时保存输出日志
                $this->updateDeploymentStatus($deploymentId, 'failed', $error, implode("\n", $output));
                
                return [
                    'success' => false,
                    'deployment_id' => $deploymentId,
                    'error' => $error,
                    'output' => implode("\n", $output)
                ];
            }
        } catch (Exception $e) {
            // 外层 try 的异常处理（初始化 SSH 等操作失败）
            $error = $e->getMessage();
            Logger::error("Deployment initialization failed: {$error}");
            
            // 只在开发环境显示详细错误信息
            $isDevelopment = getenv('APP_ENV') === 'development' 
                || ini_get('display_errors') 
                || (defined('APP_DEBUG') && APP_DEBUG);
            
            $errorOutput = "=== Deployment Initialization Failed ===\nError: {$error}";
            if ($isDevelopment) {
                $errorOutput .= "\nFile: " . $e->getFile() . "\nLine: " . $e->getLine();
            }
            
            // 确保更新部署记录为失败
            $this->updateDeploymentStatus($deploymentId, 'failed', $error, $errorOutput);
            
            return [
                'success' => false,
                'deployment_id' => $deploymentId,
                'error' => $error,
                'output' => "=== Deployment Initialization Failed ===\nError: {$error}"
            ];
        }
    }
    
    /**
     * 更新部署状态（带错误处理）
     */
    private function updateDeploymentStatus($deploymentId, $status, $error = null, $output = null) {
        if (!$deploymentId) {
            Logger::warning("Cannot update deployment status: deployment_id is null");
            return false;
        }
        
        try {
            $updateData = [
                'status' => $status,
                'finished_at' => date('Y-m-d H:i:s')
            ];
            
            if ($error !== null) {
                $updateData['error'] = $error;
            }
            
            if ($output !== null) {
                $updateData['output'] = $output;
            }
            
            Logger::debug("Updating deployment status: deployment_id={$deploymentId}, status={$status}, has_error=" . ($error !== null ? 'yes' : 'no') . ", has_output=" . ($output !== null ? 'yes' : 'no'));
            
            $rowsAffected = $this->db->update('deployments', $updateData, 'id = ?', [$deploymentId]);
            
            if ($rowsAffected === 0) {
                // 检查部署记录是否存在
                $existing = $this->db->fetchOne("SELECT id, status FROM deployments WHERE id = ?", [$deploymentId]);
                if ($existing) {
                    Logger::warning("Failed to update deployment status: deployment_id={$deploymentId}, status={$status}, no rows affected (current status: {$existing['status']})");
                } else {
                    Logger::error("Failed to update deployment status: deployment_id={$deploymentId}, status={$status}, deployment record not found");
                }
            } else {
                Logger::info("Deployment status updated successfully: deployment_id={$deploymentId}, status={$status}, rows_affected={$rowsAffected}");
            }
            
            return $rowsAffected > 0;
        } catch (Exception $e) {
            Logger::error("Error updating deployment status: deployment_id={$deploymentId}, status={$status}, error=" . $e->getMessage() . ", trace=" . $e->getTraceAsString());
            return false;
        }
    }
    
    /**
     * 验证脚本内容的安全性
     * 
     * @param string $script 脚本内容
     * @throws InvalidArgumentException 如果脚本包含危险命令
     */
    private function validateScript($script) {
        if (empty($script)) {
            return;
        }
        
        // 危险命令模式（禁止执行）
        $dangerousPatterns = [
            // 删除命令
            '/\brm\s+(-rf|-r|-f)\s+/i',
            '/\brmdir\s+/i',
            '/\bdel\s+/i',
            // 格式化命令
            '/\bformat\s+/i',
            '/\bmkfs\s+/i',
            // 磁盘操作
            '/\bdd\s+if=/i',
            '/\bdd\s+of=/i',
            // 系统命令
            '/\bshutdown\s+/i',
            '/\breboot\s+/i',
            '/\bhalt\s+/i',
            '/\bpoweroff\s+/i',
            // 危险重定向
            '/>\s*\/dev\/(sd[a-z]|hd[a-z]|nvme)/i',
            '/>\s*\/dev\/null\s*&&\s*rm/i',
            // 修改系统文件
            '/\s+\/etc\/(passwd|shadow|hosts)/i',
            '/\s+\/proc\//i',
            '/\s+\/sys\//i',
            // 网络危险操作
            '/\bwget\s+.*\|\s*sh/i',
            '/\bcurl\s+.*\|\s*sh/i',
            // 命令注入尝试
            '/;\s*rm\s+/i',
            '/\|\s*rm\s+/i',
            '/&&\s*rm\s+/i',
            '/`.*rm.*`/i',
            '/\$\{.*rm.*\}/i',
        ];
        
        // 按行检查脚本
        $scriptLines = explode("\n", $script);
        foreach ($scriptLines as $lineNum => $line) {
            $line = trim($line);
            
            // 跳过空行和注释
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            
            // 检查危险模式
            foreach ($dangerousPatterns as $pattern) {
                if (preg_match($pattern, $line)) {
                    Logger::warning("Dangerous command detected in script at line " . ($lineNum + 1) . ": " . substr($line, 0, 50));
                    throw new InvalidArgumentException("脚本包含危险命令，不允许执行。检测到第 " . ($lineNum + 1) . " 行存在安全问题。");
                }
            }
        }
        
        // 检查脚本长度（防止过长的脚本）
        if (strlen($script) > 10000) {
            throw new InvalidArgumentException("脚本内容过长（超过 10000 字符），可能存在安全风险");
        }
    }
    
    /**
     * 执行脚本
     */
    private function executeScript($sshExecutor, $deployPath, $script) {
        // 先验证脚本安全性
        try {
            $this->validateScript($script);
        } catch (InvalidArgumentException $e) {
            Logger::error("Script validation failed: " . $e->getMessage());
            throw $e;
        }
        
        $command = sprintf(
            "cd %s && %s",
            escapeshellarg($deployPath),
            $script
        );
        
        try {
            return $sshExecutor->execute($command);
        } catch (Exception $e) {
            Logger::error("Script execution failed: " . $e->getMessage());
            return "Error: " . $e->getMessage();
        }
    }
    
    /**
     * 回滚部署
     */
    public function rollback($projectId, $commitHash) {
        $project = $this->db->fetchOne("SELECT * FROM projects WHERE id = ?", [$projectId]);
        
        if (!$project) {
            throw new Exception("Project not found");
        }
        
        $server = $this->db->fetchOne("SELECT * FROM servers WHERE id = ?", [$project['server_id']]);
        
        if (!$server) {
            throw new Exception("Server not found");
        }
        
        $sshConfig = [
            'host' => $server['host'],
            'port' => $server['port'],
            'username' => $server['username'],
            'key_path' => $server['key_path'],
            'key_content' => $server['key_content'] ?? null,
            'password' => $server['password'] ?? null
        ];
        
        $sshExecutor = new SSHExecutor($sshConfig);
        $gitDeployer = new GitDeployer(
            $sshExecutor,
            $project['repo_url'],
            $project['branch'],
            $project['deploy_path']
        );
        
        $result = $gitDeployer->rollback($commitHash);
        
        if ($result['success']) {
            // 记录回滚
            $this->db->insert('deployments', [
                'project_id' => $projectId,
                'branch' => $project['branch'],
                'commit_hash' => $commitHash,
                'status' => 'rolled_back',
                'output' => $result['output'],
                'started_at' => date('Y-m-d H:i:s'),
                'finished_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        return $result;
    }
}


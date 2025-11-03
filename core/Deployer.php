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
        
        Logger::info("Starting deployment: project_id={$projectId}, branch={$branch}");
        
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
            
            $output = [];
            $output[] = "=== Deployment Started ===";
            $output[] = "Project: {$project['name']}";
            $output[] = "Branch: {$branch}";
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
                
                // 更新部署记录
                $this->db->update('deployments', [
                    'status' => 'success',
                    'commit_hash' => $gitResult['commit_hash'],
                    'commit_message' => $gitResult['commit_message'],
                    'output' => implode("\n", $output),
                    'finished_at' => date('Y-m-d H:i:s')
                ], 'id = ?', [$deploymentId]);
                
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
                
                Logger::error("Deployment failed: {$error}");
                
                // 更新部署记录为失败，同时保存输出日志
                $this->db->update('deployments', [
                    'status' => 'failed',
                    'error' => $error,
                    'output' => implode("\n", $output),
                    'finished_at' => date('Y-m-d H:i:s')
                ], 'id = ?', [$deploymentId]);
                
                return [
                    'success' => false,
                    'deployment_id' => $deploymentId,
                    'error' => $error,
                    'output' => implode("\n", $output)
                ];
            }
    }
    
    /**
     * 执行脚本
     */
    private function executeScript($sshExecutor, $deployPath, $script) {
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


<?php
/**
 * Git 操作封装类
 */
class GitDeployer {
    private $sshExecutor;
    private $repoUrl;
    private $branch;
    private $deployPath;
    
    public function __construct($sshExecutor, $repoUrl, $branch, $deployPath) {
        $this->sshExecutor = $sshExecutor;
        $this->repoUrl = $repoUrl;
        $this->branch = $branch;
        $this->deployPath = $deployPath;
    }
    
    /**
     * 执行部署
     */
    public function deploy() {
        $output = [];
        
        // 1. 检查目录是否存在
        $checkDir = "test -d " . escapeshellarg($this->deployPath);
        $dirExists = $this->sshExecutor->execute($checkDir . " && echo 'exists' || echo 'not_exists'");
        
        if (trim($dirExists) !== 'exists') {
            // 目录不存在，创建并克隆
            $output[] = "Creating directory and cloning repository...";
            $this->sshExecutor->execute("mkdir -p " . escapeshellarg($this->deployPath));
            $output[] = $this->sshExecutor->execute($this->buildCloneCommand());
        } else {
            // 目录存在，拉取更新
            $output[] = "Pulling latest changes...";
            $output[] = $this->sshExecutor->execute($this->buildPullCommand());
        }
        
        // 2. 获取当前提交信息
        $commitHash = $this->getCurrentCommit();
        $commitMessage = $this->getCommitMessage($commitHash);
        
        return [
            'success' => true,
            'commit_hash' => $commitHash,
            'commit_message' => $commitMessage,
            'output' => implode("\n", $output)
        ];
    }
    
    /**
     * 构建克隆命令
     */
    private function buildCloneCommand() {
        $cmd = sprintf(
            "cd %s && git clone -b %s %s .",
            escapeshellarg($this->deployPath),
            escapeshellarg($this->branch),
            escapeshellarg($this->repoUrl)
        );
        return $cmd;
    }
    
    /**
     * 构建拉取命令
     */
    private function buildPullCommand() {
        $cmd = sprintf(
            "cd %s && git fetch origin && git checkout %s && git pull origin %s",
            escapeshellarg($this->deployPath),
            escapeshellarg($this->branch),
            escapeshellarg($this->branch)
        );
        return $cmd;
    }
    
    /**
     * 获取当前提交哈希
     */
    public function getCurrentCommit() {
        try {
            $cmd = sprintf(
                "cd %s && git rev-parse HEAD",
                escapeshellarg($this->deployPath)
            );
            $result = $this->sshExecutor->execute($cmd);
            return trim($result);
        } catch (Exception $e) {
            Logger::error("Failed to get commit hash: " . $e->getMessage());
            return '';
        }
    }
    
    /**
     * 获取提交信息
     */
    public function getCommitMessage($commitHash) {
        if (empty($commitHash)) {
            return '';
        }
        
        try {
            $cmd = sprintf(
                "cd %s && git log -1 --pretty=format:'%%s' %s",
                escapeshellarg($this->deployPath),
                escapeshellarg($commitHash)
            );
            $result = $this->sshExecutor->execute($cmd);
            return trim($result);
        } catch (Exception $e) {
            Logger::error("Failed to get commit message: " . $e->getMessage());
            return '';
        }
    }
    
    /**
     * 回滚到指定提交
     */
    public function rollback($commitHash) {
        $output = [];
        
        try {
            $cmd = sprintf(
                "cd %s && git reset --hard %s",
                escapeshellarg($this->deployPath),
                escapeshellarg($commitHash)
            );
            $output[] = $this->sshExecutor->execute($cmd);
            
            return [
                'success' => true,
                'output' => implode("\n", $output)
            ];
        } catch (Exception $e) {
            Logger::error("Rollback failed: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 获取提交历史
     */
    public function getCommitHistory($limit = 10) {
        try {
            $cmd = sprintf(
                "cd %s && git log -%d --pretty=format:'%%H|%%s|%%an|%%ad' --date=iso",
                escapeshellarg($this->deployPath),
                intval($limit)
            );
            $result = $this->sshExecutor->execute($cmd);
            
            $commits = [];
            foreach (explode("\n", trim($result)) as $line) {
                if (empty($line)) continue;
                $parts = explode('|', $line, 4);
                if (count($parts) === 4) {
                    $commits[] = [
                        'hash' => $parts[0],
                        'message' => $parts[1],
                        'author' => $parts[2],
                        'date' => $parts[3]
                    ];
                }
            }
            
            return $commits;
        } catch (Exception $e) {
            Logger::error("Failed to get commit history: " . $e->getMessage());
            return [];
        }
    }
}


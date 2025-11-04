<?php
/**
 * Git 操作封装类
 */
class GitDeployer {
    private $sshExecutor;
    private $repoUrl;
    private $branch;
    private $deployPath;
    private $gitUsername;
    private $gitPassword;
    
    public function __construct($sshExecutor, $repoUrl, $branch, $deployPath, $gitUsername = null, $gitPassword = null) {
        $this->sshExecutor = $sshExecutor;
        $this->repoUrl = $repoUrl;
        $this->branch = $branch;
        $this->deployPath = $deployPath;
        $this->gitUsername = $gitUsername;
        $this->gitPassword = $gitPassword;
    }
    
    /**
     * 执行部署
     */
    public function deploy() {
        $output = [];
        
        // 记录 Git 配置信息
        Logger::debug("GitDeployer deploy: repo_url=" . $this->repoUrl . ", branch=" . $this->branch . ", username=" . ($this->gitUsername ? 'provided' : 'empty'));
        
        // 1. 检查目录是否存在
        $checkDir = "test -d " . escapeshellarg($this->deployPath);
        $dirExists = $this->sshExecutor->execute($checkDir . " && echo 'exists' || echo 'not_exists'");
        
        if (trim($dirExists) !== 'exists') {
            // 目录不存在，创建并克隆
            $output[] = "Creating directory and cloning repository...";
            $this->sshExecutor->execute("mkdir -p " . escapeshellarg($this->deployPath));
            $cloneCmd = $this->buildCloneCommand();
            // 隐藏密码用于日志（不使用正则表达式，避免压缩问题）
            $logCmd = str_replace($this->gitPassword ?? '', '***', $cloneCmd);
            Logger::debug("Clone command: " . $logCmd);
            $output[] = $this->sshExecutor->execute($cloneCmd);
        } else {
            // 目录存在，检查是否是 git 仓库
            $checkGit = "cd " . escapeshellarg($this->deployPath) . " && git rev-parse --git-dir > /dev/null 2>&1 && echo 'is_git' || echo 'not_git'";
            $isGitRepo = trim($this->sshExecutor->execute($checkGit));
            
            if ($isGitRepo !== 'is_git') {
                // 目录存在但不是 git 仓库，先清空目录再克隆（用 find 避免通配符被压缩破坏）
                $output[] = "Directory exists but is not a git repository, cleaning and cloning...";
                $cleanCmd = sprintf(
                    "cd %s && find . -mindepth 1 -maxdepth 1 -exec rm -rf -- {} +",
                    escapeshellarg($this->deployPath)
                );
                $this->sshExecutor->execute($cleanCmd);
                $cloneCmd = $this->buildCloneCommand();
                // 隐藏密码用于日志（不使用正则表达式，避免压缩问题）
                $logCmd = str_replace($this->gitPassword ?? '', '***', $cloneCmd);
                Logger::debug("Clone command: " . $logCmd);
                $output[] = $this->sshExecutor->execute($cloneCmd);
            } else {
                // 目录存在且是 git 仓库，拉取更新
                $output[] = "Pulling latest changes...";
                $pullCmd = $this->buildPullCommand();
                // 隐藏密码用于日志（不使用正则表达式，避免压缩问题）
                $logCmd = str_replace($this->gitPassword ?? '', '***', $pullCmd);
                Logger::debug("Pull command: " . $logCmd);
                $output[] = $this->sshExecutor->execute($pullCmd);
            }
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
        $url = $this->buildAuthenticatedUrl();
        $cmd = sprintf(
            "cd %s && git clone -b %s %s .",
            escapeshellarg($this->deployPath),
            escapeshellarg($this->branch),
            escapeshellarg($url)
        );
        return $cmd;
    }
    
    /**
     * 构建带认证信息的 URL
     */
    private function buildAuthenticatedUrl() {
        // 如果 URL 中已经包含用户名，直接返回
        // 使用 strpos 检查是否包含 @ 符号，更可靠
        if (preg_match('#^https?://[^@]+@#', $this->repoUrl)) {
            Logger::debug("Git URL already contains authentication info");
            return $this->repoUrl;
        }
        
        // 记录认证信息状态（不记录密码）
        Logger::debug("Git authentication check: username=" . ($this->gitUsername ? 'provided' : 'empty') . ", password=" . ($this->gitPassword ? 'provided' : 'empty') . ", repo_url=" . $this->repoUrl);
        
        // 如果提供了用户名和密码，且 URL 是 HTTPS，则将认证信息嵌入 URL
        if ($this->gitUsername && $this->gitPassword && strpos($this->repoUrl, 'https://') === 0) {
            $urlParts = parse_url($this->repoUrl);
            $scheme = isset($urlParts['scheme']) ? $urlParts['scheme'] : 'https';
            $host = isset($urlParts['host']) ? $urlParts['host'] : '';
            $port = isset($urlParts['port']) ? ':' . $urlParts['port'] : '';
            $path = isset($urlParts['path']) ? $urlParts['path'] : '';
            $query = isset($urlParts['query']) ? '?' . $urlParts['query'] : '';
            $fragment = isset($urlParts['fragment']) ? '#' . $urlParts['fragment'] : '';
            
            $authenticatedUrl = sprintf(
                '%s://%s:%s@%s%s%s%s',
                $scheme,
                rawurlencode($this->gitUsername),
                rawurlencode($this->gitPassword),
                $host,
                $port,
                $path,
                $query . $fragment
            );
            
            // 隐藏密码用于日志（不使用正则表达式，避免压缩问题）
            $logUrl = str_replace($this->gitPassword ?? '', '***', $authenticatedUrl);
            Logger::debug("Built authenticated Git URL: " . $logUrl);
            return $authenticatedUrl;
        }
        
        // 如果没有认证信息，记录警告
        if (strpos($this->repoUrl, 'https://') === 0 && (!$this->gitUsername || !$this->gitPassword)) {
            Logger::warning("Git HTTPS URL requires authentication but credentials not provided: " . $this->repoUrl);
        }
        
        return $this->repoUrl;
    }
    
    /**
     * 构建拉取命令
     */
    private function buildPullCommand() {
        $url = $this->buildAuthenticatedUrl();
        // 如果提供了认证信息且 URL 已更改，先更新 remote URL
        $commands = [];
        if ($this->gitUsername && $this->gitPassword && strpos($this->repoUrl, 'https://') === 0) {
            $commands[] = sprintf(
                "cd %s && git remote set-url origin %s",
                escapeshellarg($this->deployPath),
                escapeshellarg($url)
            );
        }
        $commands[] = sprintf(
            "cd %s && git fetch origin && git checkout %s && git pull origin %s",
            escapeshellarg($this->deployPath),
            escapeshellarg($this->branch),
            escapeshellarg($this->branch)
        );
        return implode(' && ', $commands);
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


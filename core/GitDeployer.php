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
        
        // 检查是 tag 还是 branch（在克隆/拉取前检查）
        $isTag = $this->isTag($this->branch);
        $refType = $isTag ? 'tag' : 'branch';
        Logger::debug("Git reference type: {$refType}, name: {$this->branch}");
        $output[] = "Deploying {$refType}: {$this->branch}";
        
        // 1. 检查目录是否存在
        $checkDir = "test -d " . escapeshellarg($this->deployPath);
        $dirExists = $this->sshExecutor->execute($checkDir . " && echo 'exists' || echo 'not_exists'");
        
        if (trim($dirExists) !== 'exists') {
            // 目录不存在，创建并克隆
            $output[] = "Creating directory and cloning repository...";
            $this->sshExecutor->execute("mkdir -p " . escapeshellarg($this->deployPath));
            $cloneCmd = $this->buildCloneCommand($isTag);
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
                $cloneCmd = $this->buildCloneCommand($isTag);
                // 隐藏密码用于日志（不使用正则表达式，避免压缩问题）
                $logCmd = str_replace($this->gitPassword ?? '', '***', $cloneCmd);
                Logger::debug("Clone command: " . $logCmd);
                $output[] = $this->sshExecutor->execute($cloneCmd);
            } else {
                // 目录存在且是 git 仓库，拉取更新
                $output[] = "Pulling latest changes...";
                $pullCmd = $this->buildPullCommand($isTag);
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
    private function buildCloneCommand($isTag = false) {
        if ($isTag) {
            // Tag 部署：先克隆仓库，再 checkout tag
            $url = $this->buildAuthenticatedUrl();
            $cmd = sprintf(
                "cd %s && git clone %s temp_repo && cd temp_repo && git checkout %s && cd .. && mv temp_repo/* temp_repo/.* . 2>/dev/null || true && rm -rf temp_repo",
                escapeshellarg($this->deployPath),
                escapeshellarg($url),
                escapeshellarg($this->branch)
            );
            Logger::debug("Clone command for tag: {$this->branch}");
            return $cmd;
        }
        
        // Branch 部署：完整克隆以确保稳定性
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
     * 检查指定的是分支还是 tag
     */
    private function isTag($ref) {
        // 先尝试通过远程仓库检查（不依赖本地仓库是否存在）
        try {
            $url = $this->buildAuthenticatedUrl();
            // 检查远程 tag
            $checkRemoteTagCmd = sprintf(
                "git ls-remote --tags %s 2>&1 | grep -q 'refs/tags/%s$' && echo 'is_tag' || echo 'not_tag'",
                escapeshellarg($url),
                escapeshellarg($ref)
            );
            $result = trim($this->sshExecutor->execute($checkRemoteTagCmd));
            
            if ($result === 'is_tag') {
                Logger::debug("Detected tag: {$ref}");
                return true;
            }
            
            // 如果远程没有找到 tag，检查是否是分支
            $checkRemoteBranchCmd = sprintf(
                "git ls-remote --heads %s 2>&1 | grep -q 'refs/heads/%s$' && echo 'is_branch' || echo 'not_branch'",
                escapeshellarg($url),
                escapeshellarg($ref)
            );
            $branchResult = trim($this->sshExecutor->execute($checkRemoteBranchCmd));
            
            if ($branchResult === 'is_branch') {
                Logger::debug("Detected branch: {$ref}");
                return false;
            }
            
            // 如果远程都没有找到，尝试检查本地（如果仓库已存在）
            $checkDir = "test -d " . escapeshellarg($this->deployPath);
            $dirExists = trim($this->sshExecutor->execute($checkDir . " && echo 'exists' || echo 'not_exists'"));
            
            if ($dirExists === 'exists') {
                $checkGit = "cd " . escapeshellarg($this->deployPath) . " && git rev-parse --git-dir > /dev/null 2>&1 && echo 'is_git' || echo 'not_git'";
                $isGitRepo = trim($this->sshExecutor->execute($checkGit));
                
                if ($isGitRepo === 'is_git') {
                    // 检查本地 tag
                    $checkLocalTagCmd = sprintf(
                        "cd %s && git rev-parse -q --verify refs/tags/%s 2>/dev/null && echo 'is_tag' || echo 'not_tag'",
                        escapeshellarg($this->deployPath),
                        escapeshellarg($ref)
                    );
                    $localResult = trim($this->sshExecutor->execute($checkLocalTagCmd));
                    
                    if ($localResult === 'is_tag') {
                        Logger::debug("Detected local tag: {$ref}");
                        return true;
                    }
                }
            }
            
            // 默认当作分支处理
            Logger::debug("Cannot determine if {$ref} is tag or branch, assuming branch");
            return false;
        } catch (Exception $e) {
            Logger::debug("Error checking tag/branch for {$ref}, assuming branch: " . $e->getMessage());
            return false; // 默认当作分支处理
        }
    }
    
    /**
     * 构建带认证信息的 URL（使用更安全的方式）
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
        
        // 如果提供了用户名和密码，且 URL 是 HTTPS，使用更安全的方式
        if ($this->gitUsername && $this->gitPassword && strpos($this->repoUrl, 'https://') === 0) {
            // 方案1: 使用环境变量（最安全）
            // 在SSH命令中设置GIT_ASKPASS和GIT_USERNAME/GIT_PASSWORD环境变量
            // 但这需要在SSHExecutor中实现，这里先使用URL方式但添加警告
            
            $urlParts = parse_url($this->repoUrl);
            $scheme = isset($urlParts['scheme']) ? $urlParts['scheme'] : 'https';
            $host = isset($urlParts['host']) ? $urlParts['host'] : '';
            $port = isset($urlParts['port']) ? ':' . $urlParts['port'] : '';
            $path = isset($urlParts['path']) ? $urlParts['path'] : '';
            $query = isset($urlParts['query']) ? '?' . $urlParts['query'] : '';
            $fragment = isset($urlParts['fragment']) ? '#' . $urlParts['fragment'] : '';
            
            // 临时方案：使用URL方式，但记录警告
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
            Logger::warning("Using URL-based authentication (not recommended): " . $logUrl);
            Logger::warning("Recommendation: Use SSH key authentication instead of password for better security");
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
    private function buildPullCommand($isTag = false) {
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
        
        if ($isTag) {
            // Tag 部署：fetch tags 然后 checkout tag（tag 不能 pull）
            $commands[] = sprintf(
                "cd %s && git fetch origin --tags && git checkout %s",
                escapeshellarg($this->deployPath),
                escapeshellarg($this->branch)
            );
            Logger::debug("Pull command for tag: {$this->branch}");
        } else {
            // Branch 部署：普通 fetch
            $commands[] = sprintf(
                "cd %s && git fetch origin && git checkout -f %s && git reset --hard origin/%s",
                escapeshellarg($this->deployPath),
                escapeshellarg($this->branch),
                escapeshellarg($this->branch)
            );
        }
        
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


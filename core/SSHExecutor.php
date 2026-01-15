<?php
/**
 * SSH 命令执行器
 */
class SSHExecutor {
    private $host;
    private $port;
    private $username;
    private $keyPath;
    private $keyContent;
    private $password;
    private $timeout = 300;
    
    public function __construct($config) {
        $this->host = $config['host'] ?? '';
        $this->port = $config['port'] ?? 22;
        $this->username = $config['username'] ?? '';
        $this->keyPath = $config['key_path'] ?? '';
        $this->keyContent = $config['key_content'] ?? null;
        $this->password = $config['password'] ?? null;
        $hasPassword = isset($this->password) && $this->password !== null && $this->password !== '';
        $authMethod = $this->keyContent ? 'key_content' : ($this->keyPath ? 'key_path' : ($hasPassword ? 'password' : 'none'));
        Logger::debug("SSHExecutor initialized: host={$this->host}, port={$this->port}, user={$this->username}, auth={$authMethod}, has_password=" . ($hasPassword ? 'yes' : 'no'));
    }
    
    /**
     * 执行 SSH 命令
     */
    public function execute($command, $callback = null) {
        $tempKeyFile = null;
        try {
            $sshCommand = $this->buildSSHCommand($command, $tempKeyFile);
            
            Logger::info("Executing SSH command: host={$this->host}, port={$this->port}, user={$this->username}, command={$command}");
            Logger::debug("SSH command line: " . str_replace($this->password ?? '', '***', $sshCommand));
            
            $descriptorspec = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w']
            ];
            
            // 设置环境变量，确保能找到 sshpass 等工具
            $envPath = getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin:/opt/homebrew/bin';
            $env = [
                'PATH' => $envPath . ':/usr/local/bin:/usr/bin:/bin:/opt/homebrew/bin'
            ];
            
            // 如果命令包含 sshpass 的完整路径，只在 CLI 环境中且文件不存在时才替换
            // 在 Web 环境中，即使 file_exists() 返回 false，也保留完整路径（因为检测时已经确定使用该路径）
            if (preg_match("/['\"]?(\/.*?\/sshpass)['\"]?\s+/", $sshCommand, $matches)) {
                $fullPath = $matches[1];
                Logger::debug("Detected sshpass full path in command: {$fullPath}");
                // 只在 CLI 环境中且文件真的不存在时才替换为命令名
                if (php_sapi_name() === 'cli' && !@file_exists($fullPath)) {
                    Logger::debug("sshpass full path {$fullPath} not found in CLI, trying command name with PATH");
                    $sshCommand = preg_replace("/['\"]?\/.*?\/sshpass['\"]?/", 'sshpass', $sshCommand);
                    Logger::debug("Modified SSH command: " . str_replace($this->password ?? '', '***', $sshCommand));
                } else {
                    Logger::debug("Keeping full path {$fullPath} (Web environment or file exists)");
                }
            }
            
            // 如果命令使用 sshpass 命令名（不是完整路径），在命令前显式设置 PATH
            if (preg_match("/\bsshpass\s+/", $sshCommand) && strpos($sshCommand, '/sshpass') === false) {
                $envPathStr = escapeshellarg($env['PATH']);
                // 使用 sh -c 来执行命令，确保 PATH 环境变量被正确使用
                // 注意：在 && 前后都要有空格，避免被压缩掉
                $sshCommand = sprintf("sh -c %s", escapeshellarg("export PATH={$envPathStr} && " . trim($sshCommand)));
                Logger::debug("Added explicit PATH to command: " . str_replace($this->password ?? '', '***', $sshCommand));
            }
            
            // 仍然设置环境变量，但也在命令中显式使用 env
            $process = proc_open($sshCommand, $descriptorspec, $pipes, null, $env);
            
            if (!is_resource($process)) {
                Logger::error("Failed to open SSH process: host={$this->host}, command={$command}");
                throw new Exception("Failed to execute SSH command");
            }
            
            $output = '';
            $errorOutput = '';
            
            // 设置非阻塞模式
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            
            $startTime = time();
            
            while (true) {
                $read = [$pipes[1], $pipes[2]];
                $write = null;
                $except = null;
                
                $changed = stream_select($read, $write, $except, 1);
                
                if ($changed > 0) {
                    foreach ($read as $pipe) {
                        $data = stream_get_contents($pipe);
                        if ($pipe === $pipes[1]) {
                            $output .= $data;
                            if ($callback && $data) {
                                call_user_func($callback, $data, 'stdout');
                            }
                        } elseif ($pipe === $pipes[2]) {
                            $errorOutput .= $data;
                            if ($callback && $data) {
                                call_user_func($callback, $data, 'stderr');
                            }
                        }
                    }
                }
                
                // 检查进程状态
                $status = proc_get_status($process);
                
                if (!$status['running']) {
                    break;
                }
                
                // 检查超时
                if (time() - $startTime > $this->timeout) {
                    Logger::warning("SSH command timeout: host={$this->host}, command={$command}, elapsed=" . (time() - $startTime) . "s");
                    proc_terminate($process);
                    throw new Exception("SSH command timeout after {$this->timeout} seconds");
                }
                
                usleep(100000); // 100ms
            }
            
            // 读取剩余输出
            $output .= stream_get_contents($pipes[1]);
            $errorOutput .= stream_get_contents($pipes[2]);
            
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            
            $returnCode = proc_close($process);
            
            // 如果使用完整路径失败（code 127 通常表示命令未找到），尝试使用命令名 + PATH
            if ($returnCode === 127 && preg_match("/['\"]?(\/.*?\/sshpass)['\"]?\s+/", $sshCommand, $matches)) {
                $fullPath = $matches[1];
                Logger::warning("Full path {$fullPath} failed (code 127), trying command name with PATH");
                // 重新构建命令，使用命令名
                $sshCommand = preg_replace("/['\"]?\/.*?\/sshpass['\"]?/", 'sshpass', $this->buildSSHCommand($command, $tempKeyFile));
                // 添加 PATH 设置
                if (strpos($sshCommand, '/sshpass') === false) {
                    $envPathStr = escapeshellarg($env['PATH']);
                    $sshCommand = sprintf("sh -c %s", escapeshellarg("export PATH={$envPathStr} && " . trim($sshCommand)));
                }
                Logger::debug("Retrying with command name: " . str_replace($this->password ?? '', '***', $sshCommand));
                
                // 重试执行
                $process = proc_open($sshCommand, $descriptorspec, $pipes, null, $env);
                if (is_resource($process)) {
                    stream_set_blocking($pipes[1], false);
                    stream_set_blocking($pipes[2], false);
                    $startTime = time();
                    $output = '';
                    $errorOutput = '';
                    
                    while (true) {
                        $read = [$pipes[1], $pipes[2]];
                        $write = null;
                        $except = null;
                        $changed = stream_select($read, $write, $except, 1);
                        if ($changed > 0) {
                            foreach ($read as $pipe) {
                                $data = stream_get_contents($pipe);
                                if ($pipe === $pipes[1]) {
                                    $output .= $data;
                                } elseif ($pipe === $pipes[2]) {
                                    $errorOutput .= $data;
                                }
                            }
                        }
                        $status = proc_get_status($process);
                        if (!$status['running']) {
                            break;
                        }
                        if (time() - $startTime > $this->timeout) {
                            proc_terminate($process);
                            throw new Exception("SSH command timeout after {$this->timeout} seconds");
                        }
                        usleep(100000);
                    }
                    $output .= stream_get_contents($pipes[1]);
                    $errorOutput .= stream_get_contents($pipes[2]);
                    fclose($pipes[0]);
                    fclose($pipes[1]);
                    fclose($pipes[2]);
                    $returnCode = proc_close($process);
                }
            }
            
            if ($returnCode !== 0) {
                Logger::error("SSH command failed: host={$this->host}, command={$command}, code={$returnCode}, error={$errorOutput}");
                throw new Exception("SSH command failed: {$errorOutput}");
            }
            
            Logger::debug("SSH command success: host={$this->host}, command={$command}, output_length=" . strlen($output));
            return $output;
        } finally {
            // 确保删除临时密钥文件
            if ($tempKeyFile && file_exists($tempKeyFile)) {
                @unlink($tempKeyFile);
                Logger::debug("Temporary SSH key file deleted: {$tempKeyFile}");
            }
        }
    }
    
    /**
     * 构建 SSH 命令
     */
    private function buildSSHCommand($command, &$tempKeyFile = null) {
        $sshOptions = [
            '-o', 'StrictHostKeyChecking=no',
            '-o', 'UserKnownHostsFile=/dev/null',
            '-o', 'ConnectTimeout=10',
            '-p', $this->port
        ];
        
        // 使用密钥文件或密钥内容
        if ($this->keyContent) {
            // 临时保存密钥内容
            $tempKeyFile = tempnam(sys_get_temp_dir(), 'ssh_key_');
            file_put_contents($tempKeyFile, base64_decode($this->keyContent));
            chmod($tempKeyFile, 0600);
            $sshOptions[] = '-i';
            $sshOptions[] = escapeshellarg($tempKeyFile);
        } elseif ($this->keyPath) {
            $sshOptions[] = '-i';
            $sshOptions[] = escapeshellarg($this->keyPath);
        }
        
        $host = escapeshellarg($this->host);
        $command = escapeshellarg($command);
        
        $sshBase = 'ssh ' . implode(' ', $sshOptions) . ' ' . $this->username . '@' . $host . ' ' . $command;
        $hasPassword = isset($this->password) && $this->password !== null && $this->password !== '';
        
        // 如果提供了密码，使用 sshpass
        if ($hasPassword) {
            // 检查 sshpass 是否可用（多种方式检测）
            $sshpass = $this->checkSshpass();
            if ($sshpass === '') {
                $errorMsg = "密码认证需要安装 sshpass 工具。请运行以下命令安装：\n" .
                           "Ubuntu/Debian: sudo apt-get install sshpass\n" .
                           "CentOS/RHEL: sudo yum install sshpass\n" .
                           "macOS: brew install sshpass\n" .
                           "或者使用 SSH 密钥认证（推荐）";
                Logger::error("sshpass not found: password authentication requires sshpass installed");
                throw new Exception($errorMsg);
            }
            Logger::debug("Using sshpass for password authentication: host={$this->host}, path={$sshpass}");
            // 如果检测到的是命令名（不是完整路径），再次尝试查找完整路径
            if ($sshpass === 'sshpass' || strpos($sshpass, '/') === false) {
                Logger::debug("sshpass is command name, attempting to find full path...");
                // 再次尝试通过 whereis 查找
                $whereisOutput = shell_exec('whereis sshpass 2>/dev/null');
                Logger::debug("whereis output: " . ($whereisOutput ?: 'empty'));
                if ($whereisOutput && preg_match('/sshpass:\s*(\S+)/', $whereisOutput, $matches)) {
                    $foundPath = trim($matches[1]);
                    Logger::debug("whereis found path: {$foundPath}, exists: " . (file_exists($foundPath) ? 'yes' : 'no') . ", executable: " . (is_executable($foundPath) ? 'yes' : 'no'));
                    if ($foundPath !== '' && file_exists($foundPath) && is_executable($foundPath)) {
                        Logger::debug("sshpass full path found via whereis in buildSSHCommand: {$foundPath}");
                        $sshpass = $foundPath;
                    }
                }
                
                // 如果还是找不到，尝试常见路径
                if ($sshpass === 'sshpass' || strpos($sshpass, '/') === false) {
                    Logger::debug("sshpass still command name, checking common paths...");
                    $commonPaths = ['/usr/local/bin/sshpass', '/usr/bin/sshpass', '/bin/sshpass', '/opt/homebrew/bin/sshpass'];
                    foreach ($commonPaths as $commonPath) {
                        // 在 Web 环境中，file_exists() 可能不可靠，先尝试直接使用路径
                        // 如果文件存在，优先使用；否则也尝试使用（因为执行时可能会找到）
                        $exists = @file_exists($commonPath);
                        $executable = $exists ? @is_executable($commonPath) : false;
                        Logger::debug("Checking common path: {$commonPath}, exists: " . ($exists ? 'yes' : 'no') . ", executable: " . ($executable ? 'yes' : 'no'));
                        
                        if ($exists && $executable) {
                            Logger::debug("sshpass full path found in common path: {$commonPath}");
                            $sshpass = $commonPath;
                            break;
                        }
                    }
                    
                    // 如果还是找不到，尝试直接使用常见路径（即使 file_exists() 返回 false）
                    // 因为在 Web 环境中，file_exists() 可能不可靠，但文件可能仍然存在
                    if (($sshpass === 'sshpass' || strpos($sshpass, '/') === false)) {
                        // 按照优先级尝试常见路径
                        $tryPaths = ['/usr/local/bin/sshpass', '/usr/bin/sshpass', '/bin/sshpass', '/opt/homebrew/bin/sshpass'];
                        foreach ($tryPaths as $tryPath) {
                            // 即使 file_exists() 返回 false，也尝试使用（因为可能在 Web 环境中不可靠）
                            // 但如果 file_exists() 返回 true，优先使用
                            if (@file_exists($tryPath) && @is_executable($tryPath)) {
                                Logger::debug("Using verified path: {$tryPath}");
                                $sshpass = $tryPath;
                                break;
                            }
                        }
                        // 如果所有路径都检查失败，但在 Web 环境中，使用最常见的路径
                        if (($sshpass === 'sshpass' || strpos($sshpass, '/') === false) && php_sapi_name() !== 'cli') {
                            Logger::debug("In web environment, using default path /usr/local/bin/sshpass (file_exists check may be unreliable)");
                            $sshpass = '/usr/local/bin/sshpass';
                        }
                    }
                }
                
                Logger::debug("Final sshpass path after secondary lookup: {$sshpass}");
            }
            
            // 如果检测到的是完整路径，使用完整路径；如果是命令名，使用命令名（执行时会设置 PATH）
            if ($sshpass !== '' && strpos($sshpass, '/') !== false) {
                // 使用完整路径
                Logger::debug("Using full path for sshpass: {$sshpass}");
                $sshCmd = escapeshellarg($sshpass) . ' -p ' . escapeshellarg($this->password) . ' ' . $sshBase;
            } else {
                // 使用命令名（执行时会设置 PATH 环境变量）
                Logger::debug("Using command name for sshpass (will rely on PATH)");
                $sshCmd = 'sshpass -p ' . escapeshellarg($this->password) . ' ' . $sshBase;
            }
        } elseif ($this->keyContent || $this->keyPath) {
            // 如果没有密码但有密钥，使用密钥认证
            Logger::debug("Using SSH key authentication: host={$this->host}");
            $sshCmd = $sshBase;
        } else {
            // 既没有密码也没有密钥
            Logger::warning("No authentication method provided: host={$this->host}");
            $sshCmd = $sshBase;
        }
        
        Logger::debug("Built SSH command: auth_method=" . ($this->keyContent ? 'key_content' : ($this->keyPath ? 'key_path' : ($hasPassword ? 'password' : 'none'))));
        return $sshCmd;
    }
    
    /**
     * 检查 sshpass 是否可用
     */
    private function checkSshpass() {
        // 方法1: 使用 command -v（优先使用完整路径）
        $path = trim(shell_exec('command -v sshpass 2>/dev/null') ?? '');
        if ($path !== '' && file_exists($path)) {
            Logger::debug("sshpass found via command -v: {$path}");
            return $path;
        }
        
        // 方法2: 使用 which
        $path = trim(shell_exec('which sshpass 2>/dev/null') ?? '');
        if ($path !== '' && file_exists($path)) {
            Logger::debug("sshpass found via which: {$path}");
            return $path;
        }
        
        // 方法3: 使用 whereis（更可靠，特别是在 Web 环境中）
        $whereisOutput = shell_exec('whereis sshpass 2>/dev/null');
        if ($whereisOutput) {
            // whereis 输出格式: "sshpass: /usr/local/bin/sshpass /usr/local/share/man/man1/sshpass.1"
            if (preg_match('/sshpass:\s*(\S+)/', $whereisOutput, $matches)) {
                $path = trim($matches[1]);
                if ($path !== '' && file_exists($path) && is_executable($path)) {
                    Logger::debug("sshpass found via whereis: {$path}");
                    return $path;
                }
            }
        }
        
        // 方法4: 尝试常见路径
        $commonPaths = ['/usr/bin/sshpass', '/usr/local/bin/sshpass', '/bin/sshpass', '/opt/homebrew/bin/sshpass'];
        foreach ($commonPaths as $commonPath) {
            if (file_exists($commonPath) && is_executable($commonPath)) {
                Logger::debug("sshpass found in common path: {$commonPath}");
                return $commonPath;
            }
        }
        
        // 方法5: 使用 proc_open 执行 sshpass -V，并设置正确的 PATH
        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];
        
        // 构建 PATH 环境变量（包含常见路径）
        $basePath = getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin:/opt/homebrew/bin';
        $envPath = $basePath . ':/usr/local/bin:/usr/bin:/bin:/opt/homebrew/bin';
        $env = [
            'PATH' => $envPath
        ];
        
        // 首先尝试在增强的 PATH 中查找
        $paths = explode(':', $envPath);
        foreach ($paths as $p) {
            $p = trim($p);
            if ($p === '') continue;
            $fullPath = rtrim($p, '/') . '/sshpass';
            if (file_exists($fullPath) && is_executable($fullPath)) {
                Logger::debug("sshpass found in enhanced PATH: {$fullPath}");
                return $fullPath;
            }
        }
        
        // 尝试执行 sshpass -V 来验证
        $process = @proc_open('sshpass -V', $descriptorspec, $pipes, null, $env);
        if (is_resource($process)) {
            $output = stream_get_contents($pipes[1]);
            $errorOutput = stream_get_contents($pipes[2]);
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            
            if ($output !== false && strpos($output, 'sshpass') !== false) {
                // 如果能执行，尝试通过 which 命令查找（使用增强的 PATH）
                $whichProcess = @proc_open('which sshpass', $descriptorspec, $whichPipes, null, $env);
                if (is_resource($whichProcess)) {
                    $whichOutput = stream_get_contents($whichPipes[1]);
                    fclose($whichPipes[0]);
                    fclose($whichPipes[1]);
                    fclose($whichPipes[2]);
                    proc_close($whichProcess);
                    
                    if ($whichOutput && strpos($whichOutput, '/') !== false) {
                        $foundPath = trim($whichOutput);
                        if (file_exists($foundPath)) {
                            Logger::debug("sshpass found via which in proc_open: {$foundPath}");
                            return $foundPath;
                        }
                    }
                }
                
                // 如果能执行但找不到完整路径，返回 'sshpass'（命令名）
                // 执行时会设置 PATH 环境变量，所以应该能找到
                Logger::debug("sshpass executable found but cannot determine full path, using command name");
                return 'sshpass';
            }
        }
        
        // 如果所有检测方法都失败，但在 Web 环境中，仍然尝试使用命令名
        // 因为执行时会设置 PATH 环境变量，可能会找到 sshpass
        // 如果执行时也失败，会在 execute() 方法中抛出更详细的错误
        if (php_sapi_name() === 'cli') {
            // CLI 环境中，如果检测失败，返回空字符串（表示未找到）
            return '';
        } else {
            // Web 环境中，即使检测失败，也允许尝试使用命令名（执行时会设置 PATH）
            Logger::debug("sshpass detection failed in web environment, will try command name during execution");
            return 'sshpass';
        }
    }
    
    /**
     * 测试连接
     */
    public function testConnection() {
        try {
            Logger::info("Testing SSH connection: host={$this->host}, port={$this->port}, user={$this->username}");
            $result = $this->execute('echo "OK"');
            $success = trim($result) === 'OK';
            if ($success) {
                Logger::info("SSH connection test successful: host={$this->host}, port={$this->port}, user={$this->username}");
            } else {
                Logger::warning("SSH connection test returned unexpected result: host={$this->host}, result={$result}");
            }
            return $success;
        } catch (Exception $e) {
            Logger::error("SSH connection test failed: host={$this->host}, port={$this->port}, user={$this->username}, error=" . $e->getMessage());
            return false;
        }
    }
}


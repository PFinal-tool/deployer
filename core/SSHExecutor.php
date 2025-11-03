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
    private $timeout = 300;
    
    public function __construct($config) {
        $this->host = $config['host'] ?? '';
        $this->port = $config['port'] ?? 22;
        $this->username = $config['username'] ?? '';
        $this->keyPath = $config['key_path'] ?? '';
        $this->keyContent = $config['key_content'] ?? null;
    }
    
    /**
     * 执行 SSH 命令
     */
    public function execute($command, $callback = null) {
        $sshCommand = $this->buildSSHCommand($command);
        
        Logger::info("Executing SSH command: {$command}");
        
        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];
        
        $process = proc_open($sshCommand, $descriptorspec, $pipes);
        
        if (!is_resource($process)) {
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
        
        if ($returnCode !== 0) {
            Logger::error("SSH command failed with code {$returnCode}: {$errorOutput}");
            throw new Exception("SSH command failed: {$errorOutput}");
        }
        
        return $output;
    }
    
    /**
     * 构建 SSH 命令
     */
    private function buildSSHCommand($command) {
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
        
        $sshCmd = 'ssh ' . implode(' ', $sshOptions) . ' ' . $this->username . '@' . $host . ' ' . $command;
        
        return $sshCmd;
    }
    
    /**
     * 测试连接
     */
    public function testConnection() {
        try {
            $result = $this->execute('echo "OK"');
            return trim($result) === 'OK';
        } catch (Exception $e) {
            Logger::error("SSH connection test failed: " . $e->getMessage());
            return false;
        }
    }
}


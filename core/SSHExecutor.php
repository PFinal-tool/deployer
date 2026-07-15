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

        $hasPassword = $this->password !== null && $this->password !== '';
        $authMethod = $this->keyContent ? 'key_content' : ($this->keyPath ? 'key_path' : ($hasPassword ? 'password' : 'none'));
        Logger::debug("SSHExecutor initialized: host={$this->host}, port={$this->port}, user={$this->username}, auth={$authMethod}, has_password=" . ($hasPassword ? 'yes' : 'no'));
    }

    public function execute($command, $callback = null) {
        $tempKeyFile = null;
        try {
            $argv = $this->buildSSHArgv($command, $tempKeyFile);
            Logger::info("Executing SSH command: host={$this->host}, port={$this->port}, user={$this->username}, command={$command}");
            Logger::debug('SSH argv: ' . $this->maskArgvForLog($argv));

            $descriptorspec = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $env = ['PATH' => fn_shell_path()];
            $process = proc_open($argv, $descriptorspec, $pipes, null, $env);
            if (!is_resource($process)) {
                throw new Exception('无法启动 SSH 进程');
            }

            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);

            $output = '';
            $errorOutput = '';
            $startTime = time();

            while (true) {
                $read = [$pipes[1], $pipes[2]];
                $changed = stream_select($read, $write, $except, 1);
                if ($changed > 0) {
                    foreach ($read as $pipe) {
                        $data = stream_get_contents($pipe);
                        if ($pipe === $pipes[1]) {
                            $output .= $data;
                            if ($callback && $data) {
                                call_user_func($callback, $data, 'stdout');
                            }
                        } else {
                            $errorOutput .= $data;
                            if ($callback && $data) {
                                call_user_func($callback, $data, 'stderr');
                            }
                        }
                    }
                }
                $status = proc_get_status($process);
                if (!$status['running']) {
                    break;
                }
                if (time() - $startTime > $this->timeout) {
                    proc_terminate($process);
                    throw new Exception("SSH 命令超时（{$this->timeout}s）");
                }
                usleep(100000);
            }

            $output .= stream_get_contents($pipes[1]);
            $errorOutput .= stream_get_contents($pipes[2]);
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            $returnCode = proc_close($process);
            if ($returnCode !== 0) {
                $errorOutput = trim($errorOutput);
                if ($returnCode === 127 && $this->usesPasswordAuth()) {
                    throw new Exception(fn_sshpass_error_message());
                }
                Logger::error("SSH command failed: host={$this->host}, code={$returnCode}, error={$errorOutput}");
                throw new Exception($errorOutput !== '' ? $errorOutput : "SSH 命令失败，退出码 {$returnCode}");
            }

            Logger::debug("SSH command success: host={$this->host}, output_length=" . strlen($output));
            return $output;
        } finally {
            if ($tempKeyFile && file_exists($tempKeyFile)) {
                @unlink($tempKeyFile);
            }
        }
    }

    private function usesPasswordAuth(): bool {
        return $this->password !== null && $this->password !== '';
    }

    private function buildSSHArgv($command, &$tempKeyFile = null): array {
        $sshOptions = [
            '-o', 'StrictHostKeyChecking=no',
            '-o', 'UserKnownHostsFile=/dev/null',
            '-o', 'ConnectTimeout=10',
            '-p', (string)$this->port,
        ];

        $target = $this->username . '@' . $this->host;
        $sshArgv = array_merge(['ssh'], $sshOptions, [$target, $command]);

        if ($this->usesPasswordAuth()) {
            fn_require_sshpass_for_password();
            $sshpass = fn_find_sshpass();
            return array_merge([$sshpass, '-p', $this->password], $sshArgv);
        }

        if ($this->keyContent) {
            $tempKeyFile = tempnam(sys_get_temp_dir(), 'ssh_key_');
            file_put_contents($tempKeyFile, base64_decode($this->keyContent));
            chmod($tempKeyFile, 0600);
            array_splice($sshArgv, 1, 0, ['-i', $tempKeyFile]);
        } elseif ($this->keyPath) {
            array_splice($sshArgv, 1, 0, ['-i', $this->keyPath]);
        }

        return $sshArgv;
    }

    private function maskArgvForLog(array $argv): string {
        $masked = [];
        $hideNext = false;
        foreach ($argv as $part) {
            if ($hideNext) {
                $masked[] = '***';
                $hideNext = false;
                continue;
            }
            if ($part === '-p') {
                $masked[] = $part;
                $hideNext = true;
                continue;
            }
            $masked[] = $part;
        }
        return implode(' ', $masked);
    }

    public function testConnection(): void {
        Logger::info("Testing SSH connection: host={$this->host}, port={$this->port}, user={$this->username}");
        $result = trim($this->execute('echo "OK"'));
        if ($result !== 'OK') {
            throw new Exception('连接测试返回异常: ' . $result);
        }
        Logger::info("SSH connection test successful: host={$this->host}");
    }
}

<?php
/**
 * 纯函数层：数据变换与业务规则（无副作用）
 */

function fn_ssh_config(array $server): array {
    return [
        'host' => $server['host'],
        'port' => $server['port'],
        'username' => $server['username'],
        'key_path' => $server['key_path'] ?? '',
        'key_content' => $server['key_content'] ?? null,
        'password' => $server['password'] ?? null,
    ];
}

function fn_project_form_data(array $post, ?array $existing = null): array {
    $data = [
        'name' => Validator::sanitizeString($post['name'] ?? '', 255, false),
        'repo_url' => Validator::validateRepoUrl($post['repo_url'] ?? ''),
        'branch' => Validator::validateBranch($post['branch'] ?? 'master'),
        'deploy_path' => Validator::validateDeployPath($post['deploy_path'] ?? ''),
        'server_id' => Validator::validateServerId($post['server_id'] ?? 0),
        'git_username' => !empty(trim($post['git_username'] ?? ''))
            ? Validator::sanitizeString(trim($post['git_username']), 255, true)
            : null,
        'pre_deploy_script' => Validator::sanitizeString($post['pre_deploy_script'] ?? '', 10000, true),
        'post_deploy_script' => Validator::sanitizeString($post['post_deploy_script'] ?? '', 10000, true),
        'webhook_enabled' => isset($post['webhook_enabled']) ? 1 : 0,
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    if ($data['webhook_enabled']) {
        $token = $existing['webhook_token'] ?? '';
        $data['webhook_token'] = ($token !== '' && $token !== null)
            ? $token
            : bin2hex(random_bytes(32));
    } elseif ($existing) {
        $data['webhook_token'] = $existing['webhook_token'] ?? null;
    } else {
        $data['webhook_token'] = null;
    }

    $gitPassword = Validator::validatePassword($post['git_password'] ?? '', true);
    if ($existing) {
        $data['git_password'] = $gitPassword === ''
            ? ($existing['git_password'] ?? null)
            : $gitPassword;
    } else {
        $data['git_password'] = $gitPassword ?: null;
    }

    return $data;
}

function fn_server_form_data(array $post, ?array $existing, array $files): array {
    $data = [
        'name' => Validator::sanitizeString($post['name'] ?? '', 255, false),
        'host' => Validator::validateHost($post['host'] ?? ''),
        'port' => Validator::validatePort($post['port'] ?? 22),
        'username' => Validator::validateUsername($post['username'] ?? ''),
        'key_path' => Validator::sanitizeString($post['key_path'] ?? '', 500, true),
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    $password = Validator::validatePassword($post['password'] ?? '', true);
    $hasNewPassword = false;

    if ($existing) {
        if ($password !== '') {
            $data['password'] = $password;
            $hasNewPassword = true;
        } else {
            $data['password'] = $existing['password'] ?? null;
            $hasNewPassword = !empty($existing['password']);
        }
    } else {
        $data['password'] = $password !== '' ? $password : null;
        $hasNewPassword = $password !== '';
    }

    if ($hasNewPassword) {
        $data['key_path'] = '';
        $data['key_content'] = null;
    } elseif (isset($files['key_file']) && ($files['key_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $data['key_content'] = base64_encode(file_get_contents($files['key_file']['tmp_name']));
    } elseif ($existing && !empty($existing['key_content'])) {
        $data['key_content'] = $existing['key_content'];
    }

    return $data;
}

function fn_env_summary(array $checks): array {
    $status = 'ok';
    $issues = 0;
    foreach ($checks as $check) {
        if ($check['status'] === 'error') {
            $status = 'error';
            $issues++;
        } elseif ($check['status'] === 'warning' && $status !== 'error') {
            $status = 'warning';
            $issues++;
        }
    }
    return ['status' => $status, 'issues' => $issues];
}

function fn_shell_path(): string {
    $base = getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin:/opt/homebrew/bin';
    return $base . ':/usr/local/bin:/usr/local/sbin:/usr/bin:/bin:/opt/homebrew/bin';
}

function fn_sshpass_candidates(): array {
    $candidates = [];
    $envPath = getenv('DEPLOYER_SSHPASS_PATH');
    if ($envPath !== false && $envPath !== '') {
        $candidates[] = $envPath;
    }
    foreach (explode(':', fn_shell_path()) as $dir) {
        $dir = trim($dir);
        if ($dir !== '') {
            $candidates[] = rtrim($dir, '/') . '/sshpass';
        }
    }
    $candidates = array_merge($candidates, [
        '/usr/local/bin/sshpass',
        '/usr/local/opt/sshpass/bin/sshpass',
        '/opt/homebrew/bin/sshpass',
        '/opt/homebrew/opt/sshpass/bin/sshpass',
        '/usr/bin/sshpass',
        '/bin/sshpass',
    ]);
    foreach (['command -v sshpass 2>/dev/null', 'which sshpass 2>/dev/null'] as $cmd) {
        $found = trim(shell_exec($cmd) ?? '');
        if ($found !== '') {
            $candidates[] = $found;
        }
    }
    return array_values(array_unique(array_filter($candidates)));
}

function fn_sshpass_executable(string $path): bool {
    if ($path === '' || $path === 'sshpass') {
        return false;
    }
    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $env = ['PATH' => fn_shell_path()];
    $process = @proc_open([$path, '-V'], $descriptors, $pipes, null, $env);
    if (!is_resource($process)) {
        return false;
    }
    $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);
    return $code === 0 && strpos($output, 'sshpass') !== false;
}

function fn_find_sshpass(): string {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    foreach (fn_sshpass_candidates() as $path) {
        if (fn_sshpass_executable($path)) {
            Logger::debug("sshpass found: {$path}");
            $cache = $path;
            return $path;
        }
    }
    Logger::warning('sshpass not found in any candidate path');
    $cache = '';
    return '';
}

function fn_sshpass_error_message(): string {
    return "密码认证需要 sshpass。请安装后重试：\n"
        . "macOS: brew install sshpass\n"
        . "Ubuntu/Debian: sudo apt-get install sshpass\n"
        . "CentOS/RHEL: sudo yum install sshpass\n"
        . "或设置环境变量 DEPLOYER_SSHPASS_PATH 指向 sshpass 可执行文件\n"
        . "也可改用 SSH 密钥认证（推荐）";
}

/** 运行时检测 sshpass 是否可用 */
function fn_sshpass_runtime_check(): array {
    $path = fn_find_sshpass();
    if ($path !== '') {
        return [
            'installed' => true,
            'path' => $path,
            'message' => "已安装 ({$path})",
        ];
    }
    return [
        'installed' => false,
        'path' => '',
        'message' => '未安装 — SSH 密码认证不可用，请执行 brew install sshpass 或设置 DEPLOYER_SSHPASS_PATH',
    ];
}

function fn_sshpass_installed(): bool {
    return fn_sshpass_runtime_check()['installed'];
}

/** 应用启动时探测运行环境（结果写入日志） */
function fn_runtime_environment_probe(): void {
    $sshpass = fn_sshpass_runtime_check();
    if ($sshpass['installed']) {
        Logger::info('Runtime check: sshpass OK, path=' . $sshpass['path']);
    } else {
        Logger::warning('Runtime check: sshpass NOT found, SSH password auth disabled');
    }
    $ssh = trim(shell_exec('command -v ssh 2>/dev/null') ?? '');
    Logger::info('Runtime check: ssh ' . ($ssh !== '' ? "OK ({$ssh})" : 'NOT found'));
}

/** 使用密码认证前校验 sshpass */
function fn_require_sshpass_for_password(): void {
    if (!fn_sshpass_installed()) {
        throw new Exception(fn_sshpass_error_message());
    }
}

function fn_check_environment(string $storageDir): array {
    $checks = [];

    $phpOk = version_compare(PHP_VERSION, '7.4.0', '>=');
    $checks[] = ['name' => 'PHP 版本', 'value' => PHP_VERSION, 'status' => $phpOk ? 'ok' : 'error', 'message' => $phpOk ? '' : '需要 PHP 7.4+'];

    $pdo = extension_loaded('pdo');
    $checks[] = ['name' => 'PDO 扩展', 'value' => $pdo ? '已安装' : '未安装', 'status' => $pdo ? 'ok' : 'error', 'message' => $pdo ? '' : '需要 PDO'];

    $sqlite = extension_loaded('pdo_sqlite');
    $checks[] = ['name' => 'PDO SQLite', 'value' => $sqlite ? '已安装' : '未安装', 'status' => $sqlite ? 'ok' : 'error', 'message' => $sqlite ? '' : '需要 pdo_sqlite'];

    $sshpassCheck = fn_sshpass_runtime_check();
    $checks[] = [
        'name' => 'sshpass（密码登录）',
        'value' => $sshpassCheck['installed'] ? '已安装' : '未安装',
        'status' => $sshpassCheck['installed'] ? 'ok' : 'error',
        'message' => $sshpassCheck['message'],
    ];

    $git = trim(shell_exec('command -v git') ?? '');
    $checks[] = ['name' => 'Git', 'value' => $git ? '已安装' : '未安装', 'status' => $git ? 'ok' : 'warning', 'message' => $git ? '' : '需要 Git'];

    $ssh = trim(shell_exec('command -v ssh') ?? '');
    $checks[] = ['name' => 'SSH', 'value' => $ssh ? '已安装' : '未安装', 'status' => $ssh ? 'ok' : 'error', 'message' => $ssh ? '' : '需要 SSH 客户端'];

    $writable = is_writable($storageDir);
    $checks[] = ['name' => '存储目录', 'value' => $writable ? '可写' : '不可写', 'status' => $writable ? 'ok' : 'error', 'message' => $writable ? '' : 'storage 需可写'];

    try {
        Database::getInstance()->query('SELECT 1');
        $checks[] = ['name' => '数据库', 'value' => '正常', 'status' => 'ok', 'message' => ''];
    } catch (Exception $e) {
        $checks[] = ['name' => '数据库', 'value' => '失败', 'status' => 'error', 'message' => $e->getMessage()];
    }

    $checks[] = ['name' => '时区', 'value' => date_default_timezone_get(), 'status' => 'ok', 'message' => ''];

    return $checks;
}

function fn_webhook_extract_branch(string $payload): ?string {
    if ($payload === '') {
        return null;
    }
    $data = json_decode($payload, true);
    if (!$data || !isset($data['ref'])) {
        return null;
    }
    if (preg_match('/^refs\/heads\/(.+)$/', $data['ref'], $m)) {
        return $m[1];
    }
    if (strpos($data['ref'], 'refs/heads/') === 0) {
        return substr($data['ref'], 11);
    }
    return $data['ref'];
}

function fn_webhook_url(int $projectId, string $token): string {
    return fn_me() . 'action=webhook&project_id=' . $projectId . '&token=' . rawurlencode($token);
}

function fn_webhook_validate_signature(array $project, string $payload): bool {
    $github = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
    if ($github !== '') {
        $expected = 'sha256=' . hash_hmac('sha256', $payload, $project['webhook_token']);
        return hash_equals($expected, $github);
    }
    $gitlab = $_SERVER['HTTP_X_GITLAB_TOKEN'] ?? '';
    if ($gitlab !== '') {
        return hash_equals($project['webhook_token'], $gitlab);
    }
    $gitee = $_SERVER['HTTP_X_GITEE_TOKEN'] ?? '';
    if ($gitee !== '') {
        return hash_equals($project['webhook_token'], $gitee);
    }
    return false;
}

function fn_is_dev(): bool {
    return substr(DEPLOYER_VERSION, -4) === '-dev';
}

function fn_is_https(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function fn_deploy_log_text(?array $deployment): string {
    if (!$deployment) {
        return '';
    }
    $log = '';
    if (!empty($deployment['output'])) {
        $log .= $deployment['output'];
    }
    if (!empty($deployment['error'])) {
        if ($log !== '') {
            $log .= "\n\n";
        }
        $log .= '错误: ' . $deployment['error'];
    }
    return $log !== '' ? $log : '暂无日志';
}

function fn_status_label(string $status): string {
    $map = ['success' => '成功', 'failed' => '失败', 'running' => '运行中', 'pending' => '等待', 'rolled_back' => '已回滚'];
    return $map[$status] ?? $status;
}

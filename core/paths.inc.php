<?php

function fn_deployer_root(): string {
    return defined('DEPLOYER_ROOT') ? DEPLOYER_ROOT : dirname(__DIR__);
}

function fn_storage_dir(): string {
    return fn_deployer_root() . '/storage';
}

function fn_me(): string {
    static $me = null;
    if ($me !== null) {
        return $me;
    }
    $uri = $_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? 'deployer.php';
    $me = preg_replace('~\?.*~', '', $uri) . '?';
    return $me;
}

function fn_check_allowed_ip(): void {
    $allowed = getenv('DEPLOYER_ALLOWED_IPS');
    if ($allowed === false || $allowed === '') {
        return;
    }
    $ips = array_filter(array_map('trim', explode(',', $allowed)));
    if (empty($ips)) {
        return;
    }
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    $client = $forwarded !== '' ? trim(explode(',', $forwarded)[0]) : $remote;
    if (!in_array($client, $ips, true) && !in_array($remote, $ips, true)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

function fn_send_security_headers(): void {
    if (headers_sent()) {
        return;
    }
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
}

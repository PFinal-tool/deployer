<?php
if (substr(DEPLOYER_VERSION, -4) !== '-dev') {
    if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
        header('HTTP/1.1 304 Not Modified');
        exit;
    }
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 365 * 24 * 60 * 60) . ' GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Cache-Control: immutable');
}

@ini_set('zlib.output_compression', '1');

$file = $_GET['file'] ?? '';
if ($file === 'default.css') {
    header('Content-Type: text/css; charset=utf-8');
    readfile(DEPLOYER_ROOT . '/ui/static/default.css');
    exit;
}
if ($file === 'functions.js') {
    header('Content-Type: text/javascript; charset=utf-8');
    readfile(DEPLOYER_ROOT . '/ui/static/functions.js');
    exit;
}

http_response_code(404);
exit;

#!/usr/bin/env php
<?php
/**
 * 打包脚本 - 参考 Adminer compile.php
 * 从 deployer.php 入口递归内联 include，静态资源 LZW 压缩后经 ?file= 路由提供
 *
 * Usage: php compile.php
 */

$projectRoot = __DIR__;
$outputFile = $projectRoot . '/deployer-single.php';
$compiledFileHandler = '';

function put_file(array $match): string {
    global $projectRoot, $compiledFileHandler;
    $path = $match[3];
    if ($path === '/ui/file.inc.php' && $compiledFileHandler !== '') {
        fwrite(STDERR, "内联: {$path} (LZW)\n");
        return "\n" . $compiledFileHandler . "\n";
    }
    $fullPath = $projectRoot . '/' . $path;
    if (!file_exists($fullPath)) {
        fwrite(STDERR, "警告: 文件不存在: {$path}\n");
        return $match[0];
    }
    fwrite(STDERR, "内联: {$path}\n");
    $content = file_get_contents($fullPath);
    $content = preg_replace('/^<\?php\s*/', '', $content);
    $content = preg_replace('/\?>\s*$/', '', $content);
    return "\n" . $content . "\n";
}

function lzw_compress(string $string): string {
    $dictionary = array_flip(range("\0", "\xFF"));
    $word = '';
    $codes = [];
    $len = strlen($string);
    for ($i = 0; $i <= $len; $i++) {
        $x = $i < $len ? $string[$i] : '';
        if ($x !== '' && isset($dictionary[$word . $x])) {
            $word .= $x;
        } elseif ($i > 0) {
            $codes[] = $dictionary[$word];
            $dictionary[$word . $x] = count($dictionary);
            $word = $x;
        }
    }
    $dictionary_count = 256;
    $bits = 8;
    $return = '';
    $rest = 0;
    $rest_length = 0;
    foreach ($codes as $code) {
        $rest = ($rest << $bits) + $code;
        $rest_length += $bits;
        $dictionary_count++;
        if ($dictionary_count >> $bits) {
            $bits++;
        }
        while ($rest_length > 7) {
            $rest_length -= 8;
            $return .= chr($rest >> $rest_length);
            $rest &= (1 << $rest_length) - 1;
        }
    }
    return $return . ($rest_length ? chr($rest << (8 - $rest_length)) : '');
}

function minify_css(string $css): string {
    $css = preg_replace('/\/\*[\s\S]*?\*\//', '', $css);
    $css = preg_replace('/\s+/', ' ', $css);
    $css = preg_replace('/\s*([:;{},])\s*/', '$1', $css);
    return trim($css);
}

function minify_js(string $js): string {
    $js = preg_replace('/\/\*[\s\S]*?\*\//', '', $js);
    $strings = [];
    $js = preg_replace_callback('/(["\'])(?:\\\\.|(?!\1).)*\1/', function ($m) use (&$strings) {
        $key = '___S' . count($strings) . '___';
        $strings[$key] = $m[0];
        return $key;
    }, $js);
    $js = preg_replace('/(^|[;{}()\[\]\s])\/\/[^\n]*$/m', '$1', $js);
    foreach ($strings as $key => $value) {
        $js = str_replace($key, $value, $js);
    }
    $js = preg_replace('/\s+/', ' ', $js);
    $js = preg_replace('/\s*([{}();,:=\[\]+\-<>\|&])\s*/', '$1', $js);
    return trim($js);
}

function compile_static_file(string $relativePath, string $minifier): string {
    global $projectRoot;
    $content = file_get_contents($projectRoot . '/' . $relativePath);
    if ($minifier === 'minify_css') {
        $content = minify_css($content);
    } elseif ($minifier === 'minify_js') {
        $content = minify_js($content);
    }
    return base64_encode(lzw_compress($content));
}

function clean_view(string $content): string {
    $content = preg_replace('/^<\?php\s*/', '', $content);
    $content = preg_replace('/^\?>\s*/', '', $content);
    $content = preg_replace('/require_once\s+[^;]*;/s', '', $content);
    $content = preg_replace('/require\s+[^;]*;/s', '', $content);
    $content = preg_replace('/include_once\s+[^;]*;/s', '', $content);
    $content = preg_replace('/include\s+[^;]*;/s', '', $content);
    $content = preg_replace('/\$auth\s*=\s*new\s+Auth\(\);\s*/s', '', $content);
    $content = preg_replace('/\?>\s*$/', '', $content);
    $content = preg_replace('/<!--[\s\S]*?-->/', '', $content);
    $content = preg_replace('/\n\s*\n+/', "\n", $content);
    return trim($content);
}

function build_view_renderer(): string {
    global $projectRoot;
    $views = [];
    foreach (glob($projectRoot . '/ui/views/*.php') as $viewPath) {
        $views[] = basename($viewPath);
    }
    sort($views);
    $out = "class ViewRenderer{\nprivate static \$views=[];\n";
    $out .= "public static function init(){\nif(!empty(self::\$views))return;\n";
    foreach ($views as $view) {
        $viewPath = $projectRoot . '/ui/views/' . $view;
        if (!file_exists($viewPath)) {
            continue;
        }
        fwrite(STDERR, "视图: {$view}\n");
        $viewContent = base64_encode(clean_view(file_get_contents($viewPath)));
        $viewName = basename($view, '.php');
        $out .= "self::\$views['{$viewName}']=base64_decode('{$viewContent}');\n";
    }
    $out .= "}\npublic static function render(\$view,\$vars=[]){\n";
    $out .= "self::init();\n";
    $out .= "if(!isset(self::\$views[\$view])){echo'View not found:'.\$view;return;}\n";
    $out .= "extract(\$vars,EXTR_SKIP);\n";
    $out .= "\$viewContent=self::\$views[\$view];ob_start();eval('?>'.(\$viewContent[0]==='<'?\$viewContent:'<?php '.\$viewContent));\$output=ob_get_clean();\$output=preg_replace('/^\\?>\\s*/','',\$output);echo\$output;\n";
    $out .= "}\n}\n";
    return $out;
}

function build_compiled_file_handler(string $cssData, string $jsData): string {
    return <<<PHP
if (substr(DEPLOYER_VERSION, -4) !== '-dev') {
    if (!empty(\$_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
        header('HTTP/1.1 304 Not Modified');
        exit;
    }
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 365 * 24 * 60 * 60) . ' GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Cache-Control: immutable');
}
@ini_set('zlib.output_compression', '1');
\$file = \$_GET['file'] ?? '';
if (\$file === 'default.css') {
    header('Content-Type: text/css; charset=utf-8');
    echo lzw_decompress(base64_decode('{$cssData}'));
    exit;
}
if (\$file === 'functions.js') {
    header('Content-Type: text/javascript; charset=utf-8');
    echo lzw_decompress(base64_decode('{$jsData}'));
    exit;
}
http_response_code(404);
exit;
PHP;
}

function shrink_php(string $content): string {
    $content = preg_replace('~<\?php\s*\?>\s*~', '', $content);
    $content = preg_replace('~\?>\s*<\?php\s*~', '', $content);
    $content = preg_replace('/\n{3,}/', "\n\n", $content);
    return $content;
}

if (php_sapi_name() !== 'cli') {
    echo "请在命令行运行: php compile.php\n";
    exit(1);
}

echo "开始编译（Adminer 模式）...\n";

$cssData = compile_static_file('ui/static/default.css', 'minify_css');
$jsData = compile_static_file('ui/static/functions.js', 'minify_js');
$compiledFileHandler = build_compiled_file_handler($cssData, $jsData);

$file = file_get_contents($projectRoot . '/deployer.php');

$includePattern = '~\b(include|require)(_once)?\s+DEPLOYER_ROOT\s*\.\s*[\'"]([^\'"]+)[\'"]\s*;~';
for ($i = 0; $i < 64; $i++) {
    $next = preg_replace_callback($includePattern, 'put_file', $file);
    if ($next === null || $next === $file) {
        break;
    }
    $file = $next;
}

$file = preg_replace(
    '/function render_view\(string \$view, array \$vars = \[\]\): void \{[\s\S]*?^}/m',
    'function render_view(string $view, array $vars = []): void {$vars=is_array($vars)?$vars:[];ViewRenderer::render($view,$vars);}',
    $file,
    1
);

$file = preg_replace('/define\(\'DEPLOYER_ROOT\',\s*__DIR__\);\s*/', '', $file, 1);

$file = shrink_php($file);
$file = preg_replace('/^<\?php\s*/', '', $file);

$output = "<?php\ndefine('DEPLOYER_ROOT', __DIR__);\n";
$output .= "if(php_sapi_name()!=='cli'){try{\n";
$output .= $file . "\n";
$output .= "}catch(Throwable \$e){error_log('Deployer Error: '.\$e->getMessage().' in '.\$e->getFile().':'.\$e->getLine().PHP_EOL.\$e->getTraceAsString());if(!headers_sent()){header('Content-Type: text/html; charset=UTF-8');}if(substr(DEPLOYER_VERSION,-4)==='-dev'){echo '<h1>Error</h1><pre>'.htmlspecialchars(\$e->getMessage().PHP_EOL.\$e->getFile().':'.\$e->getLine()).'</pre>';}else{echo '<h1>部署工具错误</h1><p>请查看服务器日志。</p>';}exit;}}\n";
$output .= build_view_renderer();

$bytes = file_put_contents($outputFile, $output);
if ($bytes === false) {
    fwrite(STDERR, "错误: 无法写入 {$outputFile}\n");
    exit(1);
}

$open = substr_count($output, '{');
$close = substr_count($output, '}');
if ($open !== $close) {
    fwrite(STDERR, "警告: 括号不平衡 (开: {$open}, 闭: {$close})\n");
}

echo "编译完成: deployer-single.php\n";
echo "文件大小: " . number_format(filesize($outputFile) / 1024, 2) . " KB\n";
echo "写入字节: " . number_format($bytes) . " bytes\n";

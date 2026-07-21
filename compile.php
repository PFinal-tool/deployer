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
        return $compiledFileHandler;
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
    $code = <<<PHP
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
    return minify_php($code);
}

function php_minify_is_keyword(int $type): bool {
    static $keywords = null;
    if ($keywords === null) {
        $keywords = [];
        foreach (get_defined_constants(true)['tokenizer'] as $name => $value) {
            if (preg_match('/^T_(ABSTRACT|ARRAY|AS|AND|BOOLEAN|BREAK|CALLABLE|CASE|CATCH|CLASS|CLONE|CONST|CONTINUE|DECLARE|DEFAULT|DIE|DO|ECHO|ELSE|ELSEIF|EMPTY|ENDDECLARE|ENDFOR|ENDFOREACH|ENDIF|ENDSWITCH|ENDWHILE|ENUM|EVAL|EXIT|EXTENDS|FINAL|FINALLY|FN|FOR|FOREACH|FUNCTION|GLOBAL|GOTO|IF|IMPLEMENTS|INCLUDE|INCLUDE_ONCE|INSTANCEOF|INSTEADOF|INTERFACE|ISSET|LIST|MATCH|NAMESPACE|NEW|OR|PARENT|PRINT|PRIVATE|PROTECTED|PUBLIC|READONLY|REQUIRE|REQUIRE_ONCE|RETURN|STATIC|SWITCH|THROW|TRAIT|TRY|UNSET|USE|VAR|WHILE|XOR|YIELD|YIELD_FROM)$/', $name)) {
                $keywords[$value] = true;
            }
        }
    }
    return isset($keywords[$type]);
}

function php_minify_token_starts_word(int $type, string $text): bool {
    if ($type === T_VARIABLE || $type === T_STRING || $type === T_LNUMBER || $type === T_DNUMBER) {
        return true;
    }
    if ($type === T_CONSTANT_ENCAPSED_STRING) {
        return true;
    }
    return php_minify_is_keyword($type);
}

function php_minify_token_ends_word(int $type, string $text): bool {
    if ($type === T_VARIABLE || $type === T_STRING || $type === T_LNUMBER || $type === T_DNUMBER) {
        return true;
    }
    if ($type === T_CONSTANT_ENCAPSED_STRING) {
        $q = $text[0];
        return strlen($text) > 1 && substr($text, -1) !== $q;
    }
    return php_minify_is_keyword($type);
}

function php_minify_char_ends_word(string $char): bool {
    return $char === '$' || $char === ')' || $char === ']' || ctype_alnum($char);
}

function php_minify_char_starts_word(string $char): bool {
    return $char === '$' || ctype_alnum($char);
}

function php_minify_needs_space($prev, $token): bool {
    if ($prev === null) {
        return false;
    }

    $prevEndsWord = is_string($prev)
        ? php_minify_char_ends_word($prev)
        : php_minify_token_ends_word($prev[0], $prev[1]);

    if (is_string($token)) {
        return $prevEndsWord && php_minify_char_starts_word($token);
    }

    [$type, $text] = $token;
    $startsWord = php_minify_token_starts_word($type, $text);

    if ($prevEndsWord && $startsWord) {
        return true;
    }

    if (is_string($prev)) {
        return in_array($prev, ['}', ';', ')'], true) && $startsWord;
    }

    return false;
}

function php_minify_double_string_done(int $type, string $text): bool {
    return $type === T_ENCAPSED_AND_WHITESPACE && substr($text, -1) === '"';
}

function minify_php(string $code): string {
    if ($code === '') {
        return '';
    }

    $code = preg_replace('~<\?php\s*\?>\s*~', '', $code);
    $code = preg_replace('~\?>\s*<\?php\s*~', '', $code);

    $tokens = token_get_all($code, TOKEN_PARSE);
    $out = '';
    $prev = null;
    $inDoubleString = false;

    foreach ($tokens as $token) {
        if (is_string($token)) {
            if ($inDoubleString) {
                $out .= $token;
                continue;
            }
            if (php_minify_needs_space($prev, $token)) {
                $out .= ' ';
            }
            $out .= $token;
            $prev = $token;
            continue;
        }

        [$type, $text] = $token;

        if ($type === T_COMMENT || $type === T_DOC_COMMENT || $type === T_WHITESPACE) {
            continue;
        }

        if ($type === T_OPEN_TAG) {
            if ($out !== '') {
                $out .= ' ';
            }
            $out .= '<?php';
            $prev = [T_STRING, 'php'];
            $inDoubleString = false;
            continue;
        }

        if ($type === T_OPEN_TAG_WITH_ECHO) {
            if (php_minify_needs_space($prev, $token)) {
                $out .= ' ';
            }
            $out .= '<?=';
            $prev = $token;
            $inDoubleString = false;
            continue;
        }

        if ($type === T_CLOSE_TAG) {
            if (php_minify_needs_space($prev, $token)) {
                $out .= ' ';
            }
            $out .= '?>';
            $prev = $token;
            $inDoubleString = false;
            continue;
        }

        if ($inDoubleString) {
            $out .= $text;
            if (php_minify_double_string_done($type, $text)) {
                $inDoubleString = false;
            }
            $prev = $token;
            continue;
        }

        if ($type === T_CONSTANT_ENCAPSED_STRING && $text[0] === '"' && substr($text, -1) !== '"') {
            if (php_minify_needs_space($prev, $token)) {
                $out .= ' ';
            }
            $out .= $text;
            $inDoubleString = true;
            $prev = $token;
            continue;
        }

        if (php_minify_needs_space($prev, $token)) {
            $out .= ' ';
        }
        $out .= $text;
        $prev = $token;
    }

    return trim($out);
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

$file = preg_replace('/^<\?php\s*/', '', $file);

$output = "<?php\ndefine('DEPLOYER_ROOT', __DIR__);\n";
$output .= "if(php_sapi_name()!=='cli'){try{\n";
$output .= $file;
$output .= "}catch(Throwable \$e){error_log('Deployer Error: '.\$e->getMessage().' in '.\$e->getFile().':'.\$e->getLine().PHP_EOL.\$e->getTraceAsString());if(!headers_sent()){header('Content-Type: text/html; charset=UTF-8');}if(substr(DEPLOYER_VERSION,-4)==='-dev'){echo '<h1>Error</h1><pre>'.htmlspecialchars(\$e->getMessage().PHP_EOL.\$e->getFile().':'.\$e->getLine()).'</pre>';}else{echo '<h1>部署工具错误</h1><p>请查看服务器日志。</p>';}exit;}}\n";
$output .= build_view_renderer();

$rawBytes = strlen($output);
$output = minify_php($output);

$bytes = file_put_contents($outputFile, $output);
if ($bytes === false) {
    fwrite(STDERR, "错误: 无法写入 {$outputFile}\n");
    exit(1);
}

$lintTmp = sys_get_temp_dir() . '/deployer-compile-lint-' . getmypid() . '.php';
file_put_contents($lintTmp, $output);
exec(PHP_BINARY . ' -l ' . escapeshellarg($lintTmp) . ' 2>&1', $lintOut, $lintCode);
@unlink($lintTmp);
if ($lintCode !== 0) {
    fwrite(STDERR, "错误: 压缩后 PHP 语法无效\n" . implode("\n", $lintOut) . "\n");
    exit(1);
}

$open = substr_count($output, '{');
$close = substr_count($output, '}');
if ($open !== $close) {
    fwrite(STDERR, "警告: 括号不平衡 (开: {$open}, 闭: {$close})\n");
}

$lines = substr_count($output, "\n") + 1;
echo "编译完成: deployer-single.php\n";
echo "压缩前: " . number_format($rawBytes / 1024, 2) . " KB → 压缩后: " . number_format($bytes / 1024, 2) . " KB\n";
echo "写入字节: " . number_format($bytes) . " bytes\n";
echo "行数: " . number_format($lines) . "（已移除注释与多余空白）\n";

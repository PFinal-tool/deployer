<?php
/**
 * 打包脚本 - 将所有模块合并成单文件（优化版）
 * 参考 Adminer 的编译思路，优化代码压缩和资源处理
 */

class Compiler {
    private $files = [];
    private $views = [];
    private $output = '';
    private $outputFile = 'deployer-single.php';
    private $minify = true; // 是否压缩代码
    
    public function __construct() {
        $this->files = [
            'core/Logger.php',
            'lang/zh.php',
            'core/Database.php',
            'core/SecureStorage.php',
            'core/Validator.php',
            'core/CSRF.php',
            'core/SSHExecutor.php',
            'core/GitDeployer.php',
            'core/Auth.php',
            'core/Deployer.php',
            'drivers/SSHDriver.php',
            'plugins/PluginInterface.php',
            'plugins/ComposerPlugin.php',
            'plugins/ArtisanPlugin.php',
            'ui/css.php',
            'ui/js.php',
        ];
        
        $this->views = [
            'login.php',
            'dashboard.php',
            'projects.php',
            'project_edit.php',
            'servers.php',
            'server_edit.php',
            'deployments.php',
            'change_password.php',
        ];
    }
    
    /**
     * 主编译方法
     */
    public function compile() {
        echo "开始编译（优化模式）...\n";
        
        // 初始化输出内容（压缩后合并为一行）
        $this->output = "<?php\n";
        $this->output .= "error_reporting(E_ALL);ini_set('display_errors',1);ini_set('display_startup_errors',1);date_default_timezone_set('Asia/Shanghai');\n";
        
        // 处理核心文件
        foreach ($this->files as $file) {
            $fullPath = __DIR__ . '/' . $file;
            if (!file_exists($fullPath)) {
                echo "警告: 文件不存在: " . $file . "\n";
                continue;
            }
            echo "处理: " . $file . "\n";
            $content = file_get_contents($fullPath);
            if ($content === false) {
                echo "警告: 无法读取文件: " . $file . "\n";
                continue;
            }
            
            // 处理文件内容
            $content = $this->processFile($content, $file);
            // 移除文件分隔注释以减小体积
            // $this->output .= "\n/* --- 文件: " . $file . " --- */\n";
            $this->output .= "\n" . $content . "\n";
        }
        
        // 处理视图文件并创建 ViewRenderer
        $this->output .= $this->buildViewRenderer();
        
        // 处理 Router 类
        $this->processRouter();
        
        // 添加主入口
        $this->output .= $this->buildMainEntry();
        
        // 移除文件分隔注释（这些注释在压缩后不需要）
        $this->output = preg_replace('/\/\*[\s\S]*?---[\s\S]*?\*\/\s*/', '', $this->output);
        
        // 代码清理和压缩
        $this->output = $this->cleanCode($this->output);
        
        // 检查清理后的内容
        if (empty($this->output)) {
            echo "错误: 清理后内容为空！\n";
            return;
        }
        
        if ($this->minify) {
            $originalLength = strlen($this->output);
            $this->output = $this->minifyPHP($this->output);
            $minifiedLength = strlen($this->output);
            echo "压缩前: " . number_format($originalLength) . " bytes\n";
            echo "压缩后: " . number_format($minifiedLength) . " bytes\n";
            
            if (empty($this->output)) {
                echo "错误: 压缩后内容为空，使用未压缩版本\n";
                // 重新执行清理（不使用压缩）
                $this->output = $this->cleanCode($this->output);
            }
        }
        
        // 最后检查输出内容
        if (empty($this->output)) {
            echo "错误: 最终输出内容为空，无法写入文件！\n";
            return;
        }
        
        // 写入文件
        $bytesWritten = file_put_contents(__DIR__ . '/' . $this->outputFile, $this->output);
        
        if ($bytesWritten === false) {
            echo "错误: 无法写入文件 " . $this->outputFile . "\n";
            return;
        }
        
        $size = filesize(__DIR__ . '/' . $this->outputFile);
        if ($size === false) {
            echo "警告: 无法获取文件大小\n";
        }
        
        echo "\n编译完成: " . $this->outputFile . "\n";
        echo "文件大小: " . number_format($size / 1024, 2) . " KB\n";
        echo "写入字节数: " . number_format($bytesWritten) . " bytes\n";
    }
    
    /**
     * 处理单个文件
     */
    private function processFile($content, $file) {
            // 移除PHP标签
            $content = preg_replace('/^<\?php\s*/', '', $content);
            $content = preg_replace('/\?>\s*$/', '', $content);
            
            // 修复单文件部署的路径问题
            if ($file === 'core/Logger.php') {
                $content = preg_replace("/__DIR__\s*\.\s*'\/\.\.\/storage\/logs'/", "__DIR__ . '/storage/logs'", $content);
            }
            if ($file === 'core/Database.php') {
                $content = preg_replace("/__DIR__\s*\.\s*'\/\.\.\/storage\/deployer\.db'/", "__DIR__ . '/storage/deployer.db'", $content);
            }
        if ($file === 'ui/css.php') {
            // 提取并压缩CSS内容
            if (preg_match('/<<<[\'"]CSS[\'"]\s*\n(.*?)\nCSS;/s', $content, $matches)) {
                $cssContent = $matches[1];
                $minifiedCSS = $this->minifyCSS($cssContent);
                $content = str_replace($cssContent, $minifiedCSS, $content);
            }
        }
        if ($file === 'ui/js.php') {
            // 提取并压缩JS内容
            if (preg_match('/<<<[\'"]JS[\'"]\s*\n(.*?)\nJS;/s', $content, $matches)) {
                $jsContent = $matches[1];
                $minifiedJS = $this->minifyJS($jsContent);
                $content = str_replace($jsContent, $minifiedJS, $content);
            }
        }
        
        return $content;
    }
    
    /**
     * 构建 ViewRenderer 类
     */
    private function buildViewRenderer() {
        $output = 'class ViewRenderer{' . "\n";
        $output .= 'private static $views=[];' . "\n";
        $output .= 'public static function init(){' . "\n";
        $output .= 'if(!empty(self::$views))return;' . "\n";
        
        foreach ($this->views as $view) {
            $viewPath = __DIR__ . '/ui/views/' . $view;
            if (file_exists($viewPath)) {
                echo "处理视图: " . $view . "\n";
                $viewContent = file_get_contents($viewPath);
                
                // 清理视图内容
                $viewContent = $this->cleanView($viewContent);
                
                // 使用 base64 编码避免转义问题
                $viewContent = base64_encode($viewContent);
                $viewName = basename($view, '.php');
                $output .= 'self::$views[\'' . $viewName . '\']=base64_decode(\'' . $viewContent . '\');' . "\n";
            }
        }
        
        $output .= "}\n";
        $output .= 'public static function render($view,$vars=[]){' . "\n";
        $output .= 'self::init();' . "\n";
        $output .= "if(!isset(self::\$views[\$view])){echo'View not found:'.\$view;return;}\n";
        $output .= 'extract($vars,EXTR_SKIP);' . "\n";
        $output .= "\$viewContent=self::\$views[\$view];ob_start();eval('?>'.(\$viewContent[0]==='<'?\$viewContent:'<?php '.\$viewContent));\$output=ob_get_clean();\$output=preg_replace('/^\\?>\\s*/','',\$output);echo\$output;\n";
        $output .= "}\n";
        $output .= "}\n\n";
        
        return $output;
    }
    
    /**
     * 清理视图内容
     */
    private function cleanView($content) {
        // 移除 PHP 开始标签（仅在文件开头）
        $content = preg_replace('/^<\?php\s*/', '', $content);
        $content = preg_replace('/^\?>\s*/', '', $content);
        
        // 移除 require/include 语句
        $content = preg_replace('/require_once\s+[^;]*;/s', '', $content);
        $content = preg_replace('/require\s+[^;]*;/s', '', $content);
        $content = preg_replace('/include_once\s+[^;]*;/s', '', $content);
        $content = preg_replace('/include\s+[^;]*;/s', '', $content);
        
        // 移除不必要的变量赋值
        $pattern = '/\$auth\s*=\s*new\s+Auth\(\);\s*/s';
        $content = preg_replace($pattern, '', $content);
        
        // 移除文件末尾的 PHP 结束标签
        $content = preg_replace('/\?>\s*$/', '', $content);
        
        // 移除 HTML 注释
        $content = preg_replace('/<!--[\s\S]*?-->/', '', $content);
        
        // 移除连续的空行
        $content = preg_replace('/\n\s*\n+/', "\n", $content);
        $content = trim($content);
        
        return $content;
    }
    
    /**
     * 处理 Router 类
     */
    private function processRouter() {
        $routerPath = __DIR__ . '/core/Router.php';
        
        if (!file_exists($routerPath)) {
            echo "警告: Router.php 文件不存在\n";
            return;
        }
        
            $routerContent = file_get_contents($routerPath);
            
        // 移除 PHP 标签
        $routerContent = preg_replace('/^<\?php\s*/', '', $routerContent);
        $routerContent = preg_replace('/\?>\s*$/', '', $routerContent);
        
        // 修复路径问题
        $routerContent = preg_replace("/__DIR__\s*\.\s*'\/\.\.\/storage'/", "__DIR__ . '/storage'", $routerContent);
        $routerContent = preg_replace("/__DIR__\s*\.\s*'\/\.\.\/ui\/views\//", "__DIR__ . '/ui/views/", $routerContent);
        
        // 在单文件模式下，替换所有 render 方法使用 ViewRenderer
        // 使用 str_replace 直接替换整个方法体（更可靠）
        $routerContent = str_replace(
            '    private function renderLogin($error = null) {
        $viewPath = __DIR__ . \'/ui/views/login.php\';
        if (file_exists($viewPath)) {
            include $viewPath;
        } else {
            echo "View file not found: login.php";
        }
    }',
            '    private function renderLogin($error = null) {
        ViewRenderer::render(\'login\', [\'error\' => $error]);
    }',
            $routerContent
        );
        
        $routerContent = str_replace(
            '    private function renderDashboard($projects, $deployments, $envCheck = []) {
        $viewPath = __DIR__ . \'/ui/views/dashboard.php\';
        if (file_exists($viewPath)) {
            include $viewPath;
        } else {
            echo "View file not found: dashboard.php";
        }
    }',
            '    private function renderDashboard($projects, $deployments, $envCheck = []) {
        ViewRenderer::render(\'dashboard\', [\'projects\' => $projects, \'deployments\' => $deployments, \'envCheck\' => $envCheck]);
    }',
            $routerContent
        );
        
        $routerContent = str_replace(
            '    private function renderProjects($projects) {
        $viewPath = __DIR__ . \'/ui/views/projects.php\';
        if (file_exists($viewPath)) {
            include $viewPath;
        } else {
            echo "View file not found: projects.php";
        }
    }',
            '    private function renderProjects($projects) {
        ViewRenderer::render(\'projects\', [\'projects\' => $projects]);
    }',
            $routerContent
        );
        
        $routerContent = str_replace(
            '    private function renderProjectEdit($project, $servers) {
        $viewPath = __DIR__ . \'/ui/views/project_edit.php\';
        if (file_exists($viewPath)) {
            include $viewPath;
        } else {
            echo "View file not found: project_edit.php";
        }
    }',
            '    private function renderProjectEdit($project, $servers) {
        ViewRenderer::render(\'project_edit\', [\'project\' => $project, \'servers\' => $servers]);
    }',
            $routerContent
        );
        
        $routerContent = str_replace(
            '    private function renderServers($servers) {
        $viewPath = __DIR__ . \'/ui/views/servers.php\';
        if (file_exists($viewPath)) {
            include $viewPath;
        } else {
            echo "View file not found: servers.php";
        }
    }',
            '    private function renderServers($servers) {
        ViewRenderer::render(\'servers\', [\'servers\' => $servers]);
    }',
            $routerContent
        );
        
        $routerContent = str_replace(
            '    private function renderServerEdit($server) {
        $viewPath = __DIR__ . \'/ui/views/server_edit.php\';
        if (file_exists($viewPath)) {
            include $viewPath;
        } else {
            echo "View file not found: server_edit.php";
        }
    }',
            '    private function renderServerEdit($server) {
        ViewRenderer::render(\'server_edit\', [\'server\' => $server]);
    }',
            $routerContent
        );
        
        $routerContent = str_replace(
            '    private function renderDeployments($deployments) {
        $viewPath = __DIR__ . \'/ui/views/deployments.php\';
        if (file_exists($viewPath)) {
            include $viewPath;
        } else {
            echo "View file not found: deployments.php";
        }
    }',
            '    private function renderDeployments($deployments) {
        ViewRenderer::render(\'deployments\', [\'deployments\' => $deployments]);
    }',
            $routerContent
        );
        
        $routerContent = str_replace(
            '    private function renderChangePassword($error = null, $success = false, $required = false) {
        $viewPath = __DIR__ . \'/ui/views/change_password.php\';
        if (file_exists($viewPath)) {
            include $viewPath;
        } else {
            echo "View file not found: change_password.php";
        }
    }',
            '    private function renderChangePassword($error = null, $success = false, $required = false) {
        ViewRenderer::render(\'change_password\', [\'error\' => $error, \'success\' => $success, \'required\' => $required]);
    }',
            $routerContent
        );
        
        // 移除文件分隔注释以减小体积
        // $this->output .= "\n/* --- Router.php --- */\n";
        $this->output .= "\n" . $routerContent . "\n\n";
    }
    
    /**
     * 构建主入口代码
     */
    private function buildMainEntry() {
        return 'if(php_sapi_name()!==\'cli\'){try{Logger::init();Database::getInstance();$router=new Router();$router->handle();}catch(Throwable $e){error_log(\'Deployer Error: \' . $e->getMessage() . \' in \' . $e->getFile() . \' :\' . $e->getLine() . PHP_EOL . $e->getTraceAsString());if(!headers_sent()){header(\'Content-Type: text/html; charset=UTF-8\');}echo \'<h1>Error</h1><pre>\' . htmlspecialchars($e->getMessage() . PHP_EOL . $e->getFile() . \' :\' . $e->getLine() . PHP_EOL . $e->getTraceAsString()) . \'</pre>\';exit;}}';
    }
    
    /**
     * 清理代码
     */
    private function cleanCode($content) {
        // 只做最基本的清理，避免破坏代码结构
        // 移除多个连续空行（保留最多一个空行）
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
        
        // 检查括号平衡（仅检查，不自动修复，避免错误）
        $openCount = substr_count($content, '{');
        $closeCount = substr_count($content, '}');
        if ($openCount !== $closeCount) {
            echo "警告: 括号不平衡 (开: {$openCount}, 闭: {$closeCount})，请检查代码\n";
        }
        
        return $content;
    }
    
    /**
     * PHP 代码压缩（优化模式，安全压缩，类似 Logger.php 风格）
     */
    private function minifyPHP($content) {
        echo "压缩代码（Logger.php 风格）...\n";
        
        if (empty($content)) {
            echo "警告: 内容为空，跳过压缩\n";
            return $content;
        }
        
        $originalLength = strlen($content);
        
        // 先保护所有字符串内容（包括正则表达式中的字符串和 heredoc）
        // 使用字符级扫描，确保正确匹配所有字符串
        $strings = [];
        $stringIndex = 0;
        
        $pos = 0;
        $len = strlen($content);
        $protectedContent = '';
        
        while ($pos < $len) {
            // 检查 heredoc/nowdoc 语法 (<<<'IDENTIFIER' 或 <<<IDENTIFIER)
            if ($pos + 2 < $len && $content[$pos] === '<' && $content[$pos + 1] === '<' && $content[$pos + 2] === '<') {
                $startPos = $pos;
                $pos += 3;
                
                // 跳过空白字符
                while ($pos < $len && ($content[$pos] === ' ' || $content[$pos] === "\t")) {
                    $pos++;
                }
                
                // 检查是否带引号（nowdoc）
                $isQuoted = false;
                if ($pos < $len && ($content[$pos] === "'" || $content[$pos] === '"')) {
                    $isQuoted = true;
                    $pos++;
                }
                
                // 读取标识符
                $identifierStart = $pos;
                while ($pos < $len && preg_match('/[a-zA-Z0-9_]/', $content[$pos])) {
                    $pos++;
                }
                
                if ($pos === $identifierStart) {
                    // 不是有效的 heredoc，继续处理
                    $protectedContent .= substr($content, $startPos, $pos - $startPos);
                    continue;
                }
                
                $identifier = substr($content, $identifierStart, $pos - $identifierStart);
                
                if ($isQuoted && $pos < $len && ($content[$pos] === "'" || $content[$pos] === '"')) {
                    $pos++;
                }
                
                // 跳过换行符后的空白
                while ($pos < $len && ($content[$pos] === ' ' || $content[$pos] === "\t" || $content[$pos] === "\r" || $content[$pos] === "\n")) {
                    $pos++;
                }
                
                // 读取 heredoc 内容直到结束标识符
                $contentStart = $pos;
                $identifierLen = strlen($identifier);
                
                while ($pos < $len) {
                    // 检查是否遇到换行符
                    if ($content[$pos] === "\n" || $content[$pos] === "\r") {
                        // 检查下一行是否以标识符开始（heredoc 结束标识符必须在行首）
                        $checkPos = $pos;
                        if ($content[$pos] === "\r" && $pos + 1 < $len && $content[$pos + 1] === "\n") {
                            $checkPos += 2; // 跳过 \r\n
                        } else {
                            $checkPos++; // 跳过 \n 或单独的 \r
                        }
                        
                        // 跳过空白
                        while ($checkPos < $len && ($content[$checkPos] === ' ' || $content[$checkPos] === "\t")) {
                            $checkPos++;
                        }
                        
                        // 检查是否匹配标识符
                        if ($checkPos + $identifierLen <= $len) {
                            $match = substr($content, $checkPos, $identifierLen);
                            if ($match === $identifier) {
                                $afterIdentifier = $checkPos + $identifierLen;
                                // 检查标识符后是否有分号或换行或空白
                                $hasSemicolon = ($afterIdentifier < $len && $content[$afterIdentifier] === ';');
                                if ($afterIdentifier >= $len || 
                                    $hasSemicolon ||
                                    $content[$afterIdentifier] === "\n" || 
                                    $content[$afterIdentifier] === "\r" ||
                                    $content[$afterIdentifier] === ' ' ||
                                    $content[$afterIdentifier] === "\t") {
                                    // 找到结束标识符，包含分号（如果存在）
                                    $endPos = $afterIdentifier;
                                    if ($hasSemicolon) {
                                        $endPos++; // 包含分号
                                    }
                                    $heredocContent = substr($content, $startPos, $endPos - $startPos);
                                    $key = '___HEREDOC_' . $stringIndex++ . '___';
                                    $strings[$key] = $heredocContent;
                                    $protectedContent .= $key;
                                    $pos = $endPos;
                                    continue 2;
                                }
                            }
                        }
                    }
                    $pos++;
                }
                
                // 如果没有找到结束标识符，保留原内容
                $heredocContent = substr($content, $startPos, $pos - $startPos);
                $key = '___HEREDOC_' . $stringIndex++ . '___';
                $strings[$key] = $heredocContent;
                $protectedContent .= $key;
                continue;
            }
            
            // 检查普通字符串开始
            if ($content[$pos] === '"' || $content[$pos] === "'") {
                $quote = $content[$pos];
                $startPos = $pos;
                $pos++;
                
                // 读取字符串内容
                while ($pos < $len) {
                    if ($content[$pos] === '\\') {
                        $pos += 2; // 跳过转义字符
                        continue;
                    }
                    if ($content[$pos] === $quote) {
                        $pos++;
                        break;
                    }
                    $pos++;
                }
                
                // 提取字符串
                $stringContent = substr($content, $startPos, $pos - $startPos);
                $key = '___STRING_' . $stringIndex++ . '___';
                $strings[$key] = $stringContent;
                $protectedContent .= $key;
                continue;
            }
            
            $protectedContent .= $content[$pos];
            $pos++;
        }
        
        // 移除所有注释（单行和多行）- 在字符串保护之后
        $protectedContent = preg_replace('/\/\*[\s\S]*?\*\//', '', $protectedContent);
        $protectedContent = preg_replace('/\/\/[^\n]*/', '', $protectedContent);
        
        // 移除多余的空白字符
        // 移除分号前后的空格
        $protectedContent = preg_replace('/\s*;\s*/', ';', $protectedContent);
        // 移除逗号后的空格
        $protectedContent = preg_replace('/,\s+/', ',', $protectedContent);
        // 移除操作符前后的空格（但保留必要的）
        $protectedContent = preg_replace('/\s*([=+\-*\/%<>!&|])\s*=/', '$1=', $protectedContent);
        $protectedContent = preg_replace('/\s*([=+\-*\/%<>!&|])\s*([^=])/', '$1$2', $protectedContent);
        // 移除花括号前后的空格和换行
        $protectedContent = preg_replace('/\s*\{\s*/', '{', $protectedContent);
        $protectedContent = preg_replace('/\s*\}\s*/', '}', $protectedContent);
        // 移除括号前后的空格
        $protectedContent = preg_replace('/\s*\(\s*/', '(', $protectedContent);
        $protectedContent = preg_replace('/\s*\)\s*/', ')', $protectedContent);
        // 移除方括号前后的空格
        $protectedContent = preg_replace('/\s*\[\s*/', '[', $protectedContent);
        $protectedContent = preg_replace('/\s*\]\s*/', ']', $protectedContent);
        // 移除点号前后的空格
        $protectedContent = preg_replace('/\s*\.\s*/', '.', $protectedContent);
        
        // 恢复字符串
        $result = $protectedContent;
        foreach ($strings as $key => $value) {
            $result = str_replace($key, $value, $result);
        }
        
        // 压缩方法和类体：类似 Logger.php 的风格
        // 将每个方法体压缩为单行，方法定义和方法体在同一行
        $lines = explode("\n", $result);
        $compressedLines = [];
        $inMethod = false;
        $methodBody = '';
        $methodIndent = '';
        $braceLevel = 0;
        $methodDefLine = '';
        
        foreach ($lines as $line) {
            $trimmed = trim($line);
            
            // 跳过空行（在方法体内）
            if (empty($trimmed)) {
                if (!$inMethod) {
                    $compressedLines[] = '';
                }
                    continue;
            }
            
            // 检测类定义
            if (preg_match('/^class\s+\w+/', $trimmed)) {
                if ($inMethod) {
                    // 结束上一个方法，将方法体追加到方法定义行
                    $lastIndex = count($compressedLines) - 1;
                    if ($lastIndex >= 0 && !empty($methodBody)) {
                        $compressedLines[$lastIndex] .= $methodBody;
                    }
                    $methodBody = '';
                    $inMethod = false;
                }
                $compressedLines[] = $trimmed;
                continue;
            }
            
            // 检测方法定义
            if (preg_match('/^(public|private|protected|static)\s+.*function\s+\w+\s*\(/', $trimmed)) {
                if ($inMethod) {
                    // 结束上一个方法
                    $lastIndex = count($compressedLines) - 1;
                    if ($lastIndex >= 0 && !empty($methodBody)) {
                        $compressedLines[$lastIndex] .= $methodBody;
                    }
                    $methodBody = '';
                }
                
                // 获取方法定义的缩进
                $methodIndent = preg_match('/^(\s+)/', $line, $m) ? $m[1] : '    ';
                $methodDefLine = $trimmed;
                $braceLevel = substr_count($trimmed, '{') - substr_count($trimmed, '}');
                
                // 如果方法定义行已经包含完整的方法体
                if (strpos($trimmed, '{') !== false && strpos($trimmed, '}') !== false && $braceLevel == 0) {
                    // 单行方法，直接添加
                    $compressedLines[] = $methodIndent . $trimmed;
                    $inMethod = false;
                } else {
                    // 方法体需要继续
                    $inMethod = true;
                    if (strpos($trimmed, '{') !== false) {
                        // 方法定义行包含 {
                        $compressedLines[] = $methodIndent . $trimmed;
                    } else {
                        // 方法定义行不包含 {，需要添加
                        $compressedLines[] = $methodIndent . $trimmed . '{';
                        $braceLevel++;
                    }
                }
                continue;
            }
            
            // 在方法体内
            if ($inMethod) {
                $braceLevel += substr_count($trimmed, '{') - substr_count($trimmed, '}');
                
                // 将方法体内容追加到缓冲区
                $methodBody .= $trimmed;
                
                // 检查方法是否结束
                if ($braceLevel <= 0 && strpos($trimmed, '}') !== false) {
                    // 方法结束，将方法体追加到方法定义行
                    $lastIndex = count($compressedLines) - 1;
                    if ($lastIndex >= 0) {
                        $compressedLines[$lastIndex] .= $methodBody;
                    }
                    $methodBody = '';
                    $inMethod = false;
                    $braceLevel = 0;
                }
            } else {
                // 类外或方法间
                $compressedLines[] = $trimmed;
            }
        }
        
        // 处理最后一个方法
        if ($inMethod && !empty($methodBody)) {
            $lastIndex = count($compressedLines) - 1;
            if ($lastIndex >= 0) {
                $compressedLines[$lastIndex] .= $methodBody;
            }
        }
        
        $result = implode("\n", $compressedLines);
        
        // 清理多余的空行和空格
        $result = preg_replace('/\{\s*\n\s*/', '{', $result);
        $result = preg_replace('/\n\s*\}/', '}', $result);
        
        // 移除多个连续空行（保留最多一个）
        $result = preg_replace('/\n{3,}/', "\n\n", $result);
        
        // 移除行首的空行
        $result = preg_replace('/^\n+/', '', $result);
        
        $finalLength = strlen($result);
        if ($finalLength === 0) {
            echo "错误: 压缩后内容为空！\n";
            return '';
        }
        
        echo "压缩前: " . number_format($originalLength) . " bytes\n";
        echo "压缩后: " . number_format($finalLength) . " bytes\n";
        echo "压缩率: " . number_format((1 - $finalLength / $originalLength) * 100, 1) . "%\n";
        
        return $result;
    }
    
    /**
     * CSS 压缩
     */
    private function minifyCSS($css) {
        // 移除 CSS 注释
        $css = preg_replace('/\/\*[\s\S]*?\*\//', '', $css);
        
        // 移除多余空白和换行
        $css = preg_replace('/\s+/', ' ', $css);
        
        // 移除规则前后的空格
        $css = preg_replace('/\s*{\s*/', '{', $css);
        $css = preg_replace('/\s*}\s*/', '}', $css);
        $css = preg_replace('/\s*:\s*/', ':', $css);
        $css = preg_replace('/\s*;\s*/', ';', $css);
        $css = preg_replace('/\s*,\s*/', ',', $css);
        
        // 移除最后一个分号（可选优化）
        $css = preg_replace('/;}/', '}', $css);
        
        // 移除多余空格
        $css = preg_replace('/\s+/', ' ', $css);
        $css = trim($css);
        
        return $css;
    }

    /**
     * JavaScript 压缩
     */
    private function minifyJS($js) {
        // 移除块注释
        $js = preg_replace('/\/\*[\s\S]*?\*\//', '', $js);
        
        // 移除行注释（尽量避免误伤字符串中的 //）
        // 先保护字符串
        $strings = [];
        $js = preg_replace_callback('/(["\'])(?:\\\\.|(?!\1).)*\1/', function($m) use (&$strings) {
            $key = '___STRING_' . count($strings) . '___';
            $strings[$key] = $m[0];
            return $key;
        }, $js);
        
        // 移除行注释
        $js = preg_replace('/(^|[;{}()\[\]\s])\/\/[^\n]*$/m', '$1', $js);
        
        // 恢复字符串
        foreach ($strings as $key => $value) {
            $js = str_replace($key, $value, $js);
        }
        
        // 压缩空白
        $js = preg_replace('/\s+/', ' ', $js);
        
        // 去除符号两侧空白
        $js = preg_replace('/\s*([{}();,:=\[\]+\-<>\|&])\s*/', '$1', $js);
        
        // 规范分号
        $js = preg_replace('/;\s+/', ';', $js);
        
        return trim($js);
    }
}

// 主执行逻辑
if (php_sapi_name() === 'cli') {
    $compiler = new Compiler();
    $compiler->compile();
} else {
    echo "请在命令行运行此脚本: php compile.php\n";
}


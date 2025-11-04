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
        ];
    }
    
    /**
     * 主编译方法
     */
    public function compile() {
        echo "开始编译（优化模式）...\n";
        
        // 初始化输出内容
        $this->output = "<?php\n";
        $this->output .= "error_reporting(E_ALL);\n";
        $this->output .= "ini_set('display_errors', 1);\n";
        $this->output .= "ini_set('display_startup_errors', 1);\n";
        $this->output .= "date_default_timezone_set('Asia/Shanghai');\n\n";
        
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
            $this->output .= "\n/* --- 文件: " . $file . " --- */\n";
            $this->output .= $content . "\n";
        }
        
        // 处理视图文件并创建 ViewRenderer
        $this->output .= $this->buildViewRenderer();
        
        // 处理 Router 类
        $this->processRouter();
        
        // 添加主入口
        $this->output .= $this->buildMainEntry();
        
        // 代码清理和压缩
        $this->output = $this->cleanCode($this->output);
        
        if ($this->minify) {
            $this->output = $this->minifyPHP($this->output);
        }
        
        // 写入文件
        file_put_contents(__DIR__ . '/' . $this->outputFile, $this->output);
        
        $size = filesize(__DIR__ . '/' . $this->outputFile);
        echo "\n编译完成: " . $this->outputFile . "\n";
        echo "文件大小: " . number_format($size / 1024, 2) . " KB\n";
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
            // CSS 已经内联，保持原样
        }
        if ($file === 'ui/js.php') {
            // JS 已经内联，保持原样
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
        
        $this->output .= "\n/* --- Router.php --- */\n";
        $this->output .= $routerContent;
        $this->output .= "\n\n";
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
        // 确保类定义前有空格（但不要破坏已有的类定义）
        $content = preg_replace('/([^\\s{}])class\\s/', '$1 class ', $content);
        $content = preg_replace('/([^\\s{}])interface\\s/', '$1 interface ', $content);
        $content = preg_replace('/([^\\s{}])trait\\s/', '$1 trait ', $content);
        
        // 确保 } 和 class/interface/trait 之间有空格
        $content = preg_replace('/\\}(class|interface|trait)\\s/', '} $1 ', $content);
        
        // 检查括号平衡（仅检查，不自动修复，避免错误）
        $openCount = substr_count($content, '{');
        $closeCount = substr_count($content, '}');
        if ($openCount !== $closeCount) {
            echo "警告: 括号不平衡 (开: {$openCount}, 闭: {$closeCount})，请检查代码\n";
        }
        
        return $content;
    }
    
    /**
     * PHP 代码压缩（极简模式，只移除注释和多余空行）
     */
    private function minifyPHP($content) {
        echo "压缩代码（极简模式）...\n";
        
        // 移除多行注释
        $content = preg_replace('/\/\*[\s\S]*?\*\//', '', $content);
        
        // 移除单行注释（但要小心字符串中的 //）
        // 先保护字符串中的内容
        $strings = [];
        $content = preg_replace_callback('/(["\'])(?:\\\\.|(?!\1).)*\1/', function($m) use (&$strings) {
            $key = '___STRING_' . count($strings) . '___';
            $strings[$key] = $m[0];
            return $key;
        }, $content);
        
        // 移除单行注释（不在字符串中的）
        $content = preg_replace('/\/\/[^\n]*/', '', $content);
        
        // 恢复字符串
        foreach ($strings as $key => $value) {
            $content = str_replace($key, $value, $content);
        }
        
        // 移除行首行尾空白
        $content = preg_replace('/^[ \t]+/m', '', $content);
        $content = preg_replace('/[ \t]+$/m', '', $content);
        
        // 移除多个连续空行（保留最多一个空行）
        $content = preg_replace('/\n{3,}/', "\n\n", $content);
        
        // 不进行其他压缩操作，避免破坏语法
        return $content;
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


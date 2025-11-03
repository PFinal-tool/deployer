<?php
/**
 * 打包脚本 - 将所有模块合并成单文件（极致压缩版）
 */

class Compiler {
    private $files = [];
    private $views = [];
    private $output = '';
    private $outputFile = 'deployer-single.php';
    
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
    
    public function compile() {
        echo "开始编译（极致压缩模式）...\n";
        
        $this->output = "<?php\nerror_reporting(E_ALL);ini_set('display_errors',1);ini_set('display_startup_errors',1);date_default_timezone_set('Asia/Shanghai');\n";
        
        // 处理核心文件
        foreach ($this->files as $file) {
            $fullPath = __DIR__ . '/' . $file;
            if (!file_exists($fullPath)) {
                echo "警告: 文件不存在: " . $file . "\n";
                continue;
            }
            echo "处理: " . $file . "\n";
            $content = file_get_contents($fullPath);
            $content = preg_replace('/^<\?php\s*/', '', $content);
            $content = preg_replace('/\?>\s*$/', '', $content);
            
            // 修复单文件部署的路径问题
            if ($file === 'core/Logger.php') {
                $content = preg_replace("/__DIR__\s*\.\s*'\/\.\.\/storage\/logs'/", "__DIR__ . '/storage/logs'", $content);
            }
            if ($file === 'core/Database.php') {
                $content = preg_replace("/__DIR__\s*\.\s*'\/\.\.\/storage\/deployer\.db'/", "__DIR__ . '/storage/deployer.db'", $content);
            }
            
            $content = $this->minifyCode($content);
            $this->output .= "//" . $file . "\n" . $content . "\n";
        }
        
        // 处理视图文件并创建 ViewRenderer
        $this->output .= "//ViewRenderer\n";
        $this->output .= "class ViewRenderer{\n";
        $this->output .= "private static \$views=[];\n";
        $this->output .= "public static function init(){\n";
        $this->output .= "if(!empty(self::\$views))return;\n";
        
        foreach ($this->views as $view) {
            $viewPath = __DIR__ . '/ui/views/' . $view;
            if (file_exists($viewPath)) {
                echo "处理视图: " . $view . "\n";
                $viewContent = file_get_contents($viewPath);
                // 移除 PHP 开始标签（仅在文件开头）
                $viewContent = preg_replace('/^<\?php\s*/', '', $viewContent);
                
                // 移除文件开头的 php 标签（如果有）
                $viewContent = preg_replace('/^\\?>\s*/', '', $viewContent);
                
                // 移除 require_once 语句（单文件中类已内嵌）
                // 匹配多行 require_once（支持换行），使用更精确的模式
                $viewContent = preg_replace('/require_once\s+[^;]*;/s', '', $viewContent);
                $viewContent = preg_replace('/require\s+[^;]*;/s', '', $viewContent);
                $viewContent = preg_replace('/include_once\s+[^;]*;/s', '', $viewContent);
                $viewContent = preg_replace('/include\s+[^;]*;/s', '', $viewContent);
                
                // 移除不必要的变量赋值（如 $auth = new Auth();）
                // 使用双引号字符串并正确转义
                $pattern = "/\\\$auth\\s*=\\s*new\\s+Auth\\(\\);\\s*/s";
                $viewContent = preg_replace($pattern, '', $viewContent);
                
                // 只移除文件末尾的 PHP 结束标签（保留中间的，因为它们在 eval 中需要）
                // eval 会先输出结束标签，然后执行后面的内容
                // 所以视图中的 PHP 标签需要保留结束标签
                $viewContent = preg_replace('/\?>\s*$/', '', $viewContent);
                
                // 移除连续的空行
                $viewContent = preg_replace('/\n\s*\n+/', "\n", $viewContent);
                $viewContent = trim($viewContent);
                
                // 使用 base64 编码避免转义问题
                $viewContent = base64_encode($viewContent);
                $viewName = basename($view, '.php');
                // base64 编码后的字符串不需要转义，使用单引号避免转义问题
                $this->output .= 'self::$views[\'' . $viewName . '\']=base64_decode(\'' . $viewContent . '\');' . "\n";
            }
        }
        
        $this->output .= "}\n";
        $this->output .= "public static function render(\$view,\$vars=[]){\n";
        $this->output .= "self::init();\n";
        $this->output .= "if(!isset(self::\$views[\$view])){echo'View not found:'.\$view;return;}\n";
        $this->output .= "extract(\$vars,EXTR_SKIP);\n";
        $this->output .= "\$viewContent=self::\$views[\$view];ob_start();eval('?>'.(\$viewContent[0]==='<'?\$viewContent:'<?php '.\$viewContent));\$output=ob_get_clean();\$output=preg_replace('/^\\?>\s*/','',\$output);echo\$output;\n";
        $this->output .= "}\n";
        $this->output .= "}\n\n";
        
        // 处理 Router 类并替换渲染方法
        $routerPath = __DIR__ . '/core/Router.php';
        $routerContent = file_get_contents($routerPath);
        $routerContent = preg_replace('/^<\?php\s*/', '', $routerContent);
        $routerContent = preg_replace('/\?>\s*$/', '', $routerContent);
        
        // 替换渲染方法（匹配完整的方法体，包括嵌套花括号）
        $patterns = [
            '/private function renderLogin\([^)]*\)\s*\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s' => "private function renderLogin(\$error=null){ViewRenderer::render('login',['error'=>\$error]);}",
            '/private function renderDashboard\([^)]*\)\s*\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s' => "private function renderDashboard(\$projects,\$deployments){ViewRenderer::render('dashboard',['projects'=>\$projects,'deployments'=>\$deployments]);}",
            '/private function renderProjects\([^)]*\)\s*\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s' => "private function renderProjects(\$projects){ViewRenderer::render('projects',['projects'=>\$projects]);}",
            '/private function renderProjectEdit\([^)]*\)\s*\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s' => "private function renderProjectEdit(\$project,\$servers){ViewRenderer::render('project_edit',['project'=>\$project,'servers'=>\$servers]);}",
            '/private function renderServers\([^)]*\)\s*\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s' => "private function renderServers(\$servers){ViewRenderer::render('servers',['servers'=>\$servers]);}",
            '/private function renderServerEdit\([^)]*\)\s*\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s' => "private function renderServerEdit(\$server){ViewRenderer::render('server_edit',['server'=>\$server]);}",
            '/private function renderDeployments\([^)]*\)\s*\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s' => "private function renderDeployments(\$deployments){ViewRenderer::render('deployments',['deployments'=>\$deployments]);}",
        ];
        
        foreach ($patterns as $pattern => $replacement) {
            $routerContent = preg_replace($pattern, $replacement, $routerContent);
        }
        
        $routerContent = $this->minifyCode($routerContent);
        $this->output .= "//Router\n" . $routerContent . "\n";
        
        // 添加主入口（添加错误处理，默认显示错误）
        $this->output .= "if(php_sapi_name()!=='cli'){try{Logger::init();Database::getInstance();\$router=new Router();\$router->handle();}catch(Throwable \$e){error_log('Deployer Error: '.\$e->getMessage().' in '.\$e->getFile().':'.\$e->getLine().PHP_EOL.\$e->getTraceAsString());if(!headers_sent()){header('Content-Type: text/html; charset=UTF-8');}echo'<h1>Error</h1><pre>'.htmlspecialchars(\$e->getMessage().PHP_EOL.\$e->getFile().':'.\$e->getLine().PHP_EOL.\$e->getTraceAsString()).'</pre>';exit;}}\n";
        
        // 最终压缩
        $this->output = $this->minifyCode($this->output);
        
        file_put_contents(__DIR__ . '/' . $this->outputFile, $this->output);
        
        $size = filesize(__DIR__ . '/' . $this->outputFile);
        echo "\n编译完成: " . $this->outputFile . "\n";
        echo "文件大小: " . number_format($size / 1024, 2) . " KB\n";
    }
    
    private function minifyCode($content) {
        // 保护 heredoc/nowdoc
        $heredocs = [];
        $placeholder = '___HEREDOC___';
        $index = 0;
        
        // 提取 heredoc（包括 CSS）
        $content = preg_replace_callback(
            '/<<<[\'"]?(\w+)[\'"]?\s*\n(.*?)\n\1;?/s',
            function($m) use (&$heredocs, &$index, $placeholder) {
                $key = $placeholder . ($index++);
                // 如果是 CSS 内容，进行压缩
                if (strpos($m[2], '<style>') !== false || strpos($m[2], 'margin:') !== false) {
                    $cssContent = $m[2];
                    // 提取 CSS 内容（在 <style> 标签内）
                    if (preg_match('/<style>(.*?)<\/style>/s', $cssContent, $cssMatch)) {
                        $css = $cssMatch[1];
                        $cssMinified = $this->minifyCSS($css);
                        $cssContent = str_replace($cssMatch[1], $cssMinified, $cssContent);
                    }
                    // 保持 heredoc 语法正确
                    $heredocs[$key] = "<<<'{$m[1]}'\n{$cssContent}\n{$m[1]};";
                } else {
                    // 保持原始 heredoc 语法
                    $heredocs[$key] = "<<<'{$m[1]}'\n{$m[2]}\n{$m[1]};";
                }
                return $key;
            },
            $content
        );
        
        // 移除多行注释
        $content = preg_replace('/\/\*[\s\S]*?\*\//', '', $content);
        
        // 移除单行注释（小心字符串）
        $content = preg_replace('/(?<!["\'])\/\/.*$/m', '', $content);
        
        // 压缩空白（但要保护 SQL 语句中的关键字）
        // 先保护 SQL 关键字周围的空格
        $sqlKeywords = ['INSERT INTO', 'UPDATE', 'DELETE FROM', 'SELECT', 'FROM', 'WHERE', 'SET', 'VALUES'];
        foreach ($sqlKeywords as $keyword) {
            $content = preg_replace('/\b' . preg_quote($keyword, '/') . '\s+/', $keyword . ' ', $content);
            $content = preg_replace('/\s+' . preg_quote($keyword, '/') . '\b/', ' ' . $keyword, $content);
        }
        
        $content = preg_replace('/\s+/', ' ', $content);
        $content = preg_replace('/;\s+/', ';', $content);
        $content = preg_replace('/\s*{\s*/', '{', $content);
        $content = preg_replace('/\s*}\s*/', '}', $content);
        $content = preg_replace('/\s*\(\s*/', '(', $content);
        $content = preg_replace('/\s*\)\s*/', ')', $content);
        $content = preg_replace('/,\s+/', ',', $content);
        $content = preg_replace('/\s*=>\s*/', '=>', $content);
        
        // 修复 SQL 语句中表名前的空格（必须在压缩后执行）
        $content = preg_replace('/\"INSERT INTO\{(\$table)\}/', '"INSERT INTO {$1}', $content);
        $content = preg_replace('/\"UPDATE\{(\$table)\}/', '"UPDATE {$1}', $content);
        $content = preg_replace('/\"DELETE FROM\{(\$table)\}/', '"DELETE FROM {$1}', $content);
        $content = preg_replace('/SET\{(\$setStr)\}/', 'SET {$1}', $content);
        $content = preg_replace('/WHERE\{(\$where)\}/', 'WHERE {$1}', $content);
        
        // 修复 WHERE 关键字后的空格
        $content = preg_replace('/\{(\$table)\}WHERE/', '{$1} WHERE', $content);
        
        // 恢复 heredoc
        foreach ($heredocs as $key => $value) {
            $content = str_replace($key, $value, $content);
        }
        
        return trim($content);
    }
    
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
}

if (php_sapi_name() === 'cli') {
    $compiler = new Compiler();
    $compiler->compile();
} else {
    echo "请在命令行运行此脚本: php compile.php\n";
}

<?php
/**
 * Deployer - 单文件代码部署工具
 * 类似 Adminer.php 的单文件风格
 */

// 错误报告（开发时可开启）
error_reporting(E_ALL);
ini_set('display_errors', 0);

// 设置时区
date_default_timezone_set('Asia/Shanghai');

// 自动加载类文件（开发模式）
spl_autoload_register(function ($class) {
    $paths = [
        __DIR__ . '/core/' . $class . '.php',
        __DIR__ . '/drivers/' . $class . '.php',
        __DIR__ . '/plugins/' . $class . '.php',
        __DIR__ . '/lang/' . $class . '.php',
    ];
    
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            break;
        }
    }
});

// 初始化日志
Logger::init();

// 初始化数据库
Database::getInstance();

// 处理路由
$router = new Router();
$router->handle();


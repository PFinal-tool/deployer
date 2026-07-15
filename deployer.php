<?php
/**
 * Deployer - 单文件代码部署工具
 */

define('DEPLOYER_ROOT', __DIR__);
include DEPLOYER_ROOT . '/core/bootstrap.inc.php';

Logger::init();
fn_runtime_environment_probe();
Database::getInstance();

$router = new Router();
$router->handle();

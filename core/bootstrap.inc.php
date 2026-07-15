<?php
include DEPLOYER_ROOT . '/core/version.inc.php';
include DEPLOYER_ROOT . '/core/paths.inc.php';
include DEPLOYER_ROOT . '/core/lzw.inc.php';

if (isset($_GET['file'])) {
    include DEPLOYER_ROOT . '/ui/file.inc.php';
}

fn_check_allowed_ip();

error_reporting(E_ALL);
ini_set('display_errors', '0');
date_default_timezone_set('Asia/Shanghai');

if (!isset($_GET['file'])) {
    fn_send_security_headers();
}

include DEPLOYER_ROOT . '/core/Logger.php';
include DEPLOYER_ROOT . '/lang/zh.php';
include DEPLOYER_ROOT . '/core/functions.php';
include DEPLOYER_ROOT . '/core/Database.php';
include DEPLOYER_ROOT . '/core/SecureStorage.php';
include DEPLOYER_ROOT . '/core/Validator.php';
include DEPLOYER_ROOT . '/core/CSRF.php';
include DEPLOYER_ROOT . '/core/RateLimiter.php';
include DEPLOYER_ROOT . '/core/AuditLogger.php';
include DEPLOYER_ROOT . '/core/Auth.php';
include DEPLOYER_ROOT . '/core/SSHExecutor.php';
include DEPLOYER_ROOT . '/core/GitDeployer.php';
include DEPLOYER_ROOT . '/core/Deployer.php';
include DEPLOYER_ROOT . '/plugins/PluginInterface.php';
include DEPLOYER_ROOT . '/plugins/ComposerPlugin.php';
include DEPLOYER_ROOT . '/plugins/ArtisanPlugin.php';
include DEPLOYER_ROOT . '/ui/layout.php';
include DEPLOYER_ROOT . '/ui/render.php';
include DEPLOYER_ROOT . '/core/controllers/BaseController.php';
include DEPLOYER_ROOT . '/core/controllers/AuthController.php';
include DEPLOYER_ROOT . '/core/controllers/ProjectController.php';
include DEPLOYER_ROOT . '/core/controllers/ServerController.php';
include DEPLOYER_ROOT . '/core/controllers/DeploymentController.php';
include DEPLOYER_ROOT . '/core/controllers/ApiController.php';
include DEPLOYER_ROOT . '/core/Router.php';

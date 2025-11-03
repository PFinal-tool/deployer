<?php
/**
 * 插件接口
 */
interface PluginInterface {
    public function shouldRun($project);
    public function execute($sshExecutor, $project);
}


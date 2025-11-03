<?php
/**
 * 语言包（中文）
 */
class Lang {
    private static $strings = [
        'title' => 'Deployer - 代码部署工具',
        'login' => '登录',
        'logout' => '退出',
        'dashboard' => '仪表板',
        'projects' => '项目',
        'servers' => '服务器',
        'deployments' => '部署历史',
        'add_project' => '添加项目',
        'edit_project' => '编辑项目',
        'delete_project' => '删除项目',
        'deploy' => '部署',
        'rollback' => '回滚',
        'status' => '状态',
        'success' => '成功',
        'failed' => '失败',
        'running' => '运行中',
        'project_name' => '项目名称',
        'repo_url' => '仓库地址',
        'branch' => '分支',
        'deploy_path' => '部署路径',
        'server' => '服务器',
        'actions' => '操作',
        'username' => '用户名',
        'password' => '密码',
        'submit' => '提交',
        'cancel' => '取消',
        'save' => '保存',
        'delete' => '删除',
        'edit' => '编辑',
        'view' => '查看',
        'test_connection' => '测试连接',
        'server_name' => '服务器名称',
        'host' => '主机',
        'port' => '端口',
        'key_path' => '密钥路径',
        'output' => '输出',
        'error' => '错误',
        'started_at' => '开始时间',
        'finished_at' => '完成时间',
        'commit_hash' => '提交哈希',
        'commit_message' => '提交信息',
    ];
    
    public static function get($key, $default = null) {
        return self::$strings[$key] ?? $default ?? $key;
    }
}


<?php
/**
 * 输入验证类
 * 用于验证和过滤用户输入
 */
class Validator {
    /**
     * 验证项目 ID
     * 
     * @param mixed $id 项目 ID
     * @return int 验证后的项目 ID
     * @throws InvalidArgumentException 如果 ID 无效
     */
    public static function validateProjectId($id): int {
        if ($id === null || $id === '') { throw new InvalidArgumentException("项目 ID 不能为空"); }
        if (!is_numeric($id)) { throw new InvalidArgumentException("项目 ID 必须是数字"); }
        $id = (int)$id;
        if ($id <= 0) { throw new InvalidArgumentException("项目 ID 必须大于 0"); }
        return $id;
    }
    
    /**
     * 验证服务器 ID
     * 
     * @param mixed $id 服务器 ID
     * @return int 验证后的服务器 ID
     * @throws InvalidArgumentException 如果 ID 无效
     */
    public static function validateServerId($id): int {
        if ($id === null || $id === '') { throw new InvalidArgumentException("服务器 ID 不能为空"); }
        if (!is_numeric($id)) { throw new InvalidArgumentException("服务器 ID 必须是数字"); }
        $id = (int)$id;
        if ($id <= 0) { throw new InvalidArgumentException("服务器 ID 必须大于 0"); }
        return $id;
    }
    
    /**
     * 验证部署 ID
     * 
     * @param mixed $id 部署 ID
     * @return int 验证后的部署 ID
     * @throws InvalidArgumentException 如果 ID 无效
     */
    public static function validateDeploymentId($id): int {
        if ($id === null || $id === '') { throw new InvalidArgumentException("部署 ID 不能为空"); }
        if (!is_numeric($id)) { throw new InvalidArgumentException("部署 ID 必须是数字"); }
        $id = (int)$id;
        if ($id <= 0) { throw new InvalidArgumentException("部署 ID 必须大于 0"); }
        return $id;
    }
    
    /**
     * 验证 Git 分支名或 Tag 名
     * 
     * @param string $branch 分支名或 Tag 名
     * @return string 验证后的分支名或 Tag 名
     * @throws InvalidArgumentException 如果分支名或 Tag 名无效
     */
    public static function validateBranch(string $branch): string {
        if (empty($branch)) { throw new InvalidArgumentException("分支名或 Tag 名不能为空"); }
        if (!preg_match('/^[a-zA-Z0-9_\-\.\/]+$/', $branch)) { throw new InvalidArgumentException("分支名或 Tag 名格式无效：只能包含字母、数字、下划线、连字符、点和斜杠"); }
        if (strlen($branch) > 255) { throw new InvalidArgumentException("分支名或 Tag 名长度不能超过 255 个字符"); }
        if (strpos($branch, '..') !== false) { throw new InvalidArgumentException("分支名或 Tag 名不能包含 '..'"); }
        return $branch;
    }
    
    /**
     * 验证 Git 仓库 URL
     * 
     * @param string $url 仓库 URL
     * @return string 验证后的 URL
     * @throws InvalidArgumentException 如果 URL 无效
     */
    public static function validateRepoUrl(string $url): string {
        if (empty($url)) { throw new InvalidArgumentException("仓库 URL 不能为空"); }
        if (!filter_var($url, FILTER_VALIDATE_URL)) { if (!preg_match('/^git@[a-zA-Z0-9\-\.]+:[a-zA-Z0-9_\-\.\/]+\.git$/', $url)) { throw new InvalidArgumentException("仓库 URL 格式无效"); } }
        if (preg_match('/^(https?|git):\/\//', $url)) { } elseif (preg_match('/^git@/', $url)) { } else { throw new InvalidArgumentException("仓库 URL 必须使用 http、https 或 git@ 协议"); }
        return $url;
    }
    
    /**
     * 验证部署路径
     * 
     * @param string $path 部署路径
     * @return string 验证后的路径
     * @throws InvalidArgumentException 如果路径无效
     */
    public static function validateDeployPath(string $path): string {
        if (empty($path)) { throw new InvalidArgumentException("部署路径不能为空"); }
        if (strpos($path, '/') !== 0) { throw new InvalidArgumentException("部署路径必须是绝对路径（以 / 开头）"); }
        if (strpos($path, '..') !== false) { throw new InvalidArgumentException("部署路径不能包含 '..'"); }
        if (strpos($path, "\0") !== false) { throw new InvalidArgumentException("部署路径不能包含空字节"); }
        return rtrim($path, '/');
    }
    
    /**
     * 验证主机地址
     * 
     * @param string $host 主机地址
     * @return string 验证后的主机地址
     * @throws InvalidArgumentException 如果主机地址无效
     */
    public static function validateHost(string $host): string {
        if (empty($host)) { throw new InvalidArgumentException("主机地址不能为空"); }
        if (!filter_var($host, FILTER_VALIDATE_IP) && !filter_var($host, FILTER_VALIDATE_DOMAIN)) { if (strpos($host, ':') !== false) { $parts = explode(':', $host); if (count($parts) !== 2 || !filter_var($parts[0], FILTER_VALIDATE_IP)) { throw new InvalidArgumentException("主机地址格式无效"); } } else { throw new InvalidArgumentException("主机地址必须是有效的 IP 地址或域名"); } }
        return $host;
    }
    
    /**
     * 验证端口号
     * 
     * @param mixed $port 端口号
     * @return int 验证后的端口号
     * @throws InvalidArgumentException 如果端口号无效
     */
    public static function validatePort($port): int {
        if ($port === null || $port === '') { return 22; }
        if (!is_numeric($port)) { throw new InvalidArgumentException("端口号必须是数字"); }
        $port = (int)$port;
        if ($port < 1 || $port > 65535) { throw new InvalidArgumentException("端口号必须在 1-65535 之间"); }
        return $port;
    }
    
    /**
     * 验证用户名
     * 
     * @param string $username 用户名
     * @return string 验证后的用户名
     * @throws InvalidArgumentException 如果用户名无效
     */
    public static function validateUsername(string $username): string {
        if (empty($username)) { throw new InvalidArgumentException("用户名不能为空"); }
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $username)) { throw new InvalidArgumentException("用户名格式无效：只能包含字母、数字、下划线、连字符和点"); }
        if (strlen($username) > 32) { throw new InvalidArgumentException("用户名长度不能超过 32 个字符"); }
        return $username;
    }
    
    /**
     * 验证提交哈希
     * 
     * @param string $hash 提交哈希
     * @return string 验证后的哈希
     * @throws InvalidArgumentException 如果哈希无效
     */
    public static function validateCommitHash(string $hash): string {
        if (empty($hash)) { throw new InvalidArgumentException("提交哈希不能为空"); }
        if (!preg_match('/^[a-f0-9]{7,40}$/i', $hash)) { throw new InvalidArgumentException("提交哈希格式无效"); }
        return $hash;
    }
    
    /**
     * 清理和验证字符串输入
     * 
     * @param string $input 输入字符串
     * @param int $maxLength 最大长度
     * @param bool $allowEmpty 是否允许为空
     * @return string 清理后的字符串
     */
    public static function sanitizeString(string $input, int $maxLength = 255, bool $allowEmpty = false): string {
        $input = trim($input);
        if (empty($input) && !$allowEmpty) { throw new InvalidArgumentException("输入不能为空"); }
        $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input);
        if (preg_match('/--\s*$/', $input)) { throw new InvalidArgumentException("输入不能包含SQL注释符号（--）"); }
        if (preg_match('/#\s*$/', $input)) { throw new InvalidArgumentException("输入不能包含SQL注释符号（#）"); }
        if (preg_match('/\/\*.*\*\//', $input)) { throw new InvalidArgumentException("输入不能包含SQL块注释符号（/* */）"); }
        if (preg_match('/;\x00/i', $input)) { throw new InvalidArgumentException("输入不能包含分号后跟空字节"); }
        if (preg_match('/--/', $input)) { throw new InvalidArgumentException("输入不能包含SQL注释符号（--）"); }
        if (preg_match('/#/', $input)) { throw new InvalidArgumentException("输入不能包含SQL注释符号（#）"); }
        if (strlen($input) > $maxLength) { throw new InvalidArgumentException("输入长度不能超过 {$maxLength} 个字符"); }
        return $input;
    }
    
    /**
     * 验证密码（确保不包含SQL注释符号，但允许其他特殊字符）
     * 
     * @param string $password 密码
     * @param bool $allowEmpty 是否允许为空
     * @return string 验证后的密码
     * @throws InvalidArgumentException 如果密码无效
     */
    public static function validatePassword(string $password, bool $allowEmpty = true): string {
        $password = trim($password);
        if (empty($password) && !$allowEmpty) { throw new InvalidArgumentException("密码不能为空"); }
        if (empty($password)) { return $password; }
        if (preg_match('/--\s*$/', $password)) { throw new InvalidArgumentException("密码不能包含SQL注释符号（--）"); }
        if (preg_match('/#\s*$/', $password)) { throw new InvalidArgumentException("密码不能包含SQL注释符号（#）"); }
        if (preg_match('/\/\*.*\*\//', $password)) { throw new InvalidArgumentException("密码不能包含SQL块注释符号（/* */）"); }
        if (preg_match('/;\x00/i', $password)) { throw new InvalidArgumentException("密码不能包含分号后跟空字节"); }
        if (preg_match('/--/', $password)) { throw new InvalidArgumentException("密码不能包含SQL注释符号（--）"); }
        if (preg_match('/#/', $password)) { throw new InvalidArgumentException("密码不能包含SQL注释符号（#）"); }
        return $password;
    }
    
    /**
     * 验证整数范围
     * 
     * @param mixed $value 要验证的值
     * @param int $min 最小值
     * @param int $max 最大值
     * @return int 验证后的整数
     * @throws InvalidArgumentException 如果值无效
     */
    public static function validateIntRange($value, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): int {
        if (!is_numeric($value)) { throw new InvalidArgumentException("值必须是数字"); }
        $value = (int)$value;
        if ($value < $min || $value > $max) { throw new InvalidArgumentException("值必须在 {$min} 到 {$max} 之间"); }
        return $value;
    }
}


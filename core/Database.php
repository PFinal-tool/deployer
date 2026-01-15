<?php
/**
 * SQLite 数据库操作类
 */
class Database {
    private static $instance = null;
    private $db = null;
    private $dbFile = null;
    
    // 允许的表名白名单（防止SQL注入）
    private static $allowedTables = ['users', 'servers', 'projects', 'deployments'];
    
    // 各表的字段名白名单（防止SQL注入）
    private static $allowedFields = [
        'users' => ['id', 'username', 'password', 'is_default_password', 'created_at'],
        'servers' => ['id', 'name', 'host', 'port', 'username', 'key_path', 'key_content', 'password', 'created_at', 'updated_at'],
        'projects' => ['id', 'name', 'repo_url', 'branch', 'deploy_path', 'server_id', 'git_username', 'git_password', 'pre_deploy_script', 'post_deploy_script', 'webhook_enabled', 'webhook_token', 'created_at', 'updated_at'],
        'deployments' => ['id', 'project_id', 'branch', 'commit_hash', 'commit_message', 'status', 'output', 'error', 'started_at', 'finished_at']
    ];
    
    private function __construct($dbFile = null) {
        if ($dbFile === null) {
            $dbFile = __DIR__ . '/../storage/deployer.db';
        }
        
        $dir = dirname($dbFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        
        $this->dbFile = $dbFile;
        $this->db = new PDO('sqlite:' . $dbFile);
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        $this->initTables();
    }
    
    public static function getInstance($dbFile = null) {
        if (self::$instance === null) {
            self::$instance = new self($dbFile);
        }
        return self::$instance;
    }
    
    private function initTables() {
        // 用户表
        $this->db->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            is_default_password INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // 兼容已有表，增加 is_default_password 字段
        $cols = $this->db->query("PRAGMA table_info(users)")->fetchAll();
        $hasDefaultPasswordFlag = false;
        foreach ($cols as $col) {
            if (($col['name'] ?? '') === 'is_default_password') {
                $hasDefaultPasswordFlag = true;
                break;
            }
        }
        if (!$hasDefaultPasswordFlag) {
            $this->db->exec("ALTER TABLE users ADD COLUMN is_default_password INTEGER DEFAULT 0");
        }
        
        // 服务器表
        $this->db->exec("CREATE TABLE IF NOT EXISTS servers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            host TEXT NOT NULL,
            port INTEGER DEFAULT 22,
            username TEXT NOT NULL,
            key_path TEXT,
            key_content TEXT,
            password TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // 兼容已有表，增加 password 字段
        $cols = $this->db->query("PRAGMA table_info(servers)")->fetchAll();
        $hasPassword = false;
        foreach ($cols as $col) {
            if (($col['name'] ?? '') === 'password') { $hasPassword = true; break; }
        }
        if (!$hasPassword) {
            $this->db->exec("ALTER TABLE servers ADD COLUMN password TEXT");
        }
        
        // 项目表
        $this->db->exec("CREATE TABLE IF NOT EXISTS projects (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            repo_url TEXT NOT NULL,
            branch TEXT DEFAULT 'master',
            deploy_path TEXT NOT NULL,
            server_id INTEGER NOT NULL,
            git_username TEXT,
            git_password TEXT,
            pre_deploy_script TEXT,
            post_deploy_script TEXT,
            webhook_enabled INTEGER DEFAULT 0,
            webhook_token TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (server_id) REFERENCES servers(id)
        )");
        
        // 兼容已有表，增加 git_username 和 git_password 字段
        $cols = $this->db->query("PRAGMA table_info(projects)")->fetchAll();
        $hasGitUsername = false;
        $hasGitPassword = false;
        foreach ($cols as $col) {
            if (($col['name'] ?? '') === 'git_username') { $hasGitUsername = true; }
            if (($col['name'] ?? '') === 'git_password') { $hasGitPassword = true; }
        }
        if (!$hasGitUsername) {
            $this->db->exec("ALTER TABLE projects ADD COLUMN git_username TEXT");
        }
        if (!$hasGitPassword) {
            $this->db->exec("ALTER TABLE projects ADD COLUMN git_password TEXT");
        }
        
        // 部署历史表
        $this->db->exec("CREATE TABLE IF NOT EXISTS deployments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            project_id INTEGER NOT NULL,
            branch TEXT NOT NULL,
            commit_hash TEXT,
            commit_message TEXT,
            status TEXT DEFAULT 'pending',
            output TEXT,
            error TEXT,
            started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            finished_at DATETIME,
            FOREIGN KEY (project_id) REFERENCES projects(id)
        )");
        
        // 创建默认管理员用户（如果不存在）
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute(['admin']);
        if ($stmt->fetchColumn() == 0) {
            $password = password_hash('admin', PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("INSERT INTO users (username, password, is_default_password) VALUES (?, ?, 1)");
            $stmt->execute(['admin', $password]);
        } else {
            // 检查现有 admin 用户是否使用默认密码（兼容旧数据）
            $user = $this->fetchOne("SELECT id, password, is_default_password FROM users WHERE username = ?", ['admin']);
            if ($user && ($user['is_default_password'] ?? 0) == 0) {
                // 验证密码是否为默认密码
                if (password_verify('admin', $user['password'])) {
                    $this->update('users', ['is_default_password' => 1], 'id = ?', [$user['id']]);
                }
            }
        }
    }
    
    public function query($sql, $params = []) {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    public function fetchOne($sql, $params = []) {
        $result = $this->query($sql, $params)->fetch();
        
        // 如果是查询结果，解密敏感字段
        if ($result && is_array($result)) {
            // 根据表结构判断需要解密的字段
            if (isset($result['password']) || isset($result['key_content'])) {
                // 可能是 servers 表
                $decryptFields = [];
                if (isset($result['password'])) $decryptFields[] = 'password';
                if (isset($result['key_content'])) $decryptFields[] = 'key_content';
                if (class_exists('SecureStorage')) {
                    $result = SecureStorage::decryptFields($result, $decryptFields);
                }
            } elseif (isset($result['git_password'])) {
                // 可能是 projects 表
                if (class_exists('SecureStorage')) {
                    $result = SecureStorage::decryptFields($result, ['git_password']);
                }
            }
        }
        
        return $result;
    }
    
    public function fetchAll($sql, $params = []) {
        $results = $this->query($sql, $params)->fetchAll();
        
        // 批量解密敏感字段
        if (!empty($results) && is_array($results[0])) {
            $firstRow = $results[0];
            $decryptFields = [];
            
            if (isset($firstRow['password']) || isset($firstRow['key_content'])) {
                if (isset($firstRow['password'])) $decryptFields[] = 'password';
                if (isset($firstRow['key_content'])) $decryptFields[] = 'key_content';
            } elseif (isset($firstRow['git_password'])) {
                $decryptFields[] = 'git_password';
            }
            
            if (!empty($decryptFields) && class_exists('SecureStorage')) {
                foreach ($results as &$row) {
                    $row = SecureStorage::decryptFields($row, $decryptFields);
                }
                unset($row);
            }
        }
        
        return $results;
    }
    
    /**
     * 验证表名是否在白名单中
     */
    private function validateTableName($table) {
        if (!in_array($table, self::$allowedTables, true)) {
            throw new InvalidArgumentException("无效的表名: {$table}");
        }
        return $table;
    }
    
    /**
     * 验证字段名是否在白名单中
     */
    private function validateFieldNames($table, $fields) {
        if (!isset(self::$allowedFields[$table])) {
            throw new InvalidArgumentException("表 {$table} 没有定义字段白名单");
        }
        $allowed = self::$allowedFields[$table];
        foreach ($fields as $field) {
            if (!in_array($field, $allowed, true)) {
                throw new InvalidArgumentException("表 {$table} 中不允许使用字段: {$field}");
            }
        }
        return true;
    }
    
    /**
     * 验证WHERE子句（严格验证，防止SQL注入和WAF误报）
     */
    private function validateWhereClause($table, $where) {
        if (empty($where) || !is_string($where)) {
            throw new InvalidArgumentException("WHERE子句不能为空且必须是字符串");
        }
        
        $where = trim($where);
        
        // 禁止SQL注释符号（防止注释注入攻击）
        if (preg_match('/--|\/\*|\*\/|#/', $where)) {
            throw new InvalidArgumentException("WHERE子句不能包含SQL注释符号");
        }
        
        // 禁止SQL关键字（防止SQL注入）
        $forbiddenKeywords = ['union', 'select', 'insert', 'update', 'delete', 'drop', 'create', 'alter', 
                             'exec', 'execute', 'script', 'javascript', 'onload', 'onerror', 'or', 'and'];
        $whereLower = strtolower($where);
        foreach ($forbiddenKeywords as $keyword) {
            if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/i', $whereLower)) {
                throw new InvalidArgumentException("WHERE子句不能包含SQL关键字: {$keyword}");
            }
        }
        
        // 禁止引号、分号等特殊字符
        if (preg_match('/[\'";`]/', $where)) {
            throw new InvalidArgumentException("WHERE子句不能包含引号或分号");
        }
        
        // 严格验证格式：只允许 "字段名 = ?" 或 "字段名 = :参数名"
        if (!preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*([?:][a-zA-Z0-9_]*)$/', $where, $matches)) {
            throw new InvalidArgumentException("WHERE子句格式无效，只允许: 字段名 = ? 或 字段名 = :参数名");
        }
        
        // 验证字段名是否在白名单中
        $fieldName = $matches[1];
        if (!isset(self::$allowedFields[$table]) || !in_array($fieldName, self::$allowedFields[$table], true)) {
            throw new InvalidArgumentException("WHERE子句中的字段名不在白名单中: {$fieldName}");
        }
        
        return $where;
    }
    
    public function insert($table, $data) {
        // 验证表名
        $table = $this->validateTableName($table);
        
        // 验证字段名
        $fields = array_keys($data);
        $this->validateFieldNames($table, $fields);
        
        // 加密敏感字段
        if ($table === 'servers') {
            $encryptFields = ['password', 'key_content'];
            if (class_exists('SecureStorage')) {
                $data = SecureStorage::encryptFields($data, $encryptFields);
            }
        } elseif ($table === 'projects') {
            $encryptFields = ['git_password'];
            if (class_exists('SecureStorage')) {
                $data = SecureStorage::encryptFields($data, $encryptFields);
            }
        }
        
        // 使用白名单字段名构建SQL（防止注入）
        $validFields = [];
        $validData = [];
        foreach ($fields as $field) {
            if (in_array($field, self::$allowedFields[$table], true)) {
                $validFields[] = $field;
                $validData[$field] = $data[$field];
            }
        }
        
        if (empty($validFields)) {
            throw new InvalidArgumentException("没有有效的字段可以插入");
        }
        
        $placeholders = ':' . implode(', :', $validFields);
        $fieldsStr = implode(', ', $validFields);
        
        $sql = "INSERT INTO {$table} ({$fieldsStr}) VALUES ({$placeholders})";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($validData);
        
        return $this->db->lastInsertId();
    }
    
    public function update($table, $data, $where, $whereParams = []) {
        // 验证表名
        $table = $this->validateTableName($table);
        
        // 验证字段名
        $fields = array_keys($data);
        $this->validateFieldNames($table, $fields);
        
        // 验证WHERE子句
        $where = $this->validateWhereClause($table, $where);
        
        // 加密敏感字段
        if ($table === 'servers') {
            $encryptFields = ['password', 'key_content'];
            if (class_exists('SecureStorage')) {
                $data = SecureStorage::encryptFields($data, $encryptFields);
            }
        } elseif ($table === 'projects') {
            $encryptFields = ['git_password'];
            if (class_exists('SecureStorage')) {
                $data = SecureStorage::encryptFields($data, $encryptFields);
            }
        }
        
        // 使用白名单字段名构建SET子句（防止注入）
        $setParts = [];
        $validData = [];
        foreach ($fields as $field) {
            if (in_array($field, self::$allowedFields[$table], true)) {
                $setParts[] = "{$field} = :{$field}";
                $validData[$field] = $data[$field];
            }
        }
        
        if (empty($setParts)) {
            throw new InvalidArgumentException("没有有效的字段可以更新");
        }
        
        $setStr = implode(', ', $setParts);
        
        // 将 WHERE 子句中的 ? 替换为命名参数
        $whereParamsNamed = [];
        $wherePlaceholder = $where;
        $paramIndex = 0;
        
        // 如果 WHERE 子句包含 ?，将其替换为命名参数
        if (strpos($where, '?') !== false) {
            $wherePlaceholder = preg_replace_callback('/\?/', function() use (&$paramIndex, &$whereParamsNamed, $whereParams) {
                $paramName = ':where_param_' . $paramIndex++;
                if (isset($whereParams[$paramIndex - 1])) {
                    $whereParamsNamed[$paramName] = $whereParams[$paramIndex - 1];
                }
                return $paramName;
            }, $where);
        } else {
            // 如果没有 ?，假设 WHERE 子句已经使用命名参数
            $whereParamsNamed = $whereParams;
        }
        
        $sql = "UPDATE {$table} SET {$setStr} WHERE {$wherePlaceholder}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge($validData, $whereParamsNamed));
        
        return $stmt->rowCount();
    }
    
    public function delete($table, $where, $whereParams = []) {
        // 验证表名
        $table = $this->validateTableName($table);
        
        // 验证WHERE子句
        $where = $this->validateWhereClause($table, $where);
        
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($whereParams);
        
        return $stmt->rowCount();
    }
    
    public function getPDO() {
        return $this->db;
    }
}


<?php
/**
 * SQLite 数据库操作类
 */
class Database {
    private static $instance = null;
    private $db = null;
    private $dbFile = null;
    
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
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
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
            $stmt = $this->db->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
            $stmt->execute(['admin', $password]);
        }
    }
    
    public function query($sql, $params = []) {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }
    
    public function fetchOne($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }
    
    public function insert($table, $data) {
        $fields = array_keys($data);
        $placeholders = ':' . implode(', :', $fields);
        $fieldsStr = implode(', ', $fields);
        
        $sql = "INSERT INTO {$table} ({$fieldsStr}) VALUES ({$placeholders})";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);
        
        return $this->db->lastInsertId();
    }
    
    public function update($table, $data, $where, $whereParams = []) {
        $setParts = [];
        foreach (array_keys($data) as $field) {
            $setParts[] = "{$field} = :{$field}";
        }
        $setStr = implode(', ', $setParts);
        
        $sql = "UPDATE {$table} SET {$setStr} WHERE {$where}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge($data, $whereParams));
        
        return $stmt->rowCount();
    }
    
    public function delete($table, $where, $whereParams = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($whereParams);
        
        return $stmt->rowCount();
    }
    
    public function getPDO() {
        return $this->db;
    }
}


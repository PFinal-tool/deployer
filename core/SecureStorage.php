<?php
/**
 * 敏感数据加密存储类
 * 用于加密存储 SSH 密钥、密码、Git 密码等敏感信息
 */
class SecureStorage {
    private static $key = null;
    private static $cipher = 'AES-256-CBC';
    
    /**
     * 获取加密密钥
     * 从环境变量或配置文件读取，如果不存在则生成并保存
     */
    private static function getKey(): string {
        if (self::$key !== null) {
            return self::$key;
        }
        
        // 优先从环境变量读取
        $key = getenv('DEPLOYER_ENCRYPTION_KEY');
        
        // 如果环境变量不存在，尝试从配置文件读取
        if (empty($key)) {
            $configFile = __DIR__ . '/../storage/.encryption_key';
            if (file_exists($configFile)) {
                $key = trim(file_get_contents($configFile));
            } else {
                // 生成新的密钥并保存
                $key = self::generateKey();
                @file_put_contents($configFile, $key);
                @chmod($configFile, 0600); // 仅所有者可读写
            }
        }
        
        // 确保密钥长度为 32 字节（256 位）
        if (strlen($key) < 32) {
            $key = hash('sha256', $key, true);
        } elseif (strlen($key) > 32) {
            $key = substr($key, 0, 32);
        }
        
        self::$key = $key;
        return self::$key;
    }
    
    /**
     * 生成加密密钥
     */
    private static function generateKey(): string {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes(32));
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            return bin2hex(openssl_random_pseudo_bytes(32));
        } else {
            // fallback（不安全，但总比没有好）
            return hash('sha256', uniqid('', true) . microtime(true) . mt_rand());
        }
    }
    
    /**
     * 加密数据
     * 
     * @param string $data 要加密的数据
     * @return string base64 编码的加密数据（格式：iv + encrypted_data）
     */
    public static function encrypt(string $data): string {
        if (empty($data)) {
            return '';
        }
        
        $key = self::getKey();
        $ivLength = openssl_cipher_iv_length(self::$cipher);
        
        if ($ivLength === false) {
            throw new Exception('Invalid cipher algorithm');
        }
        
        $iv = openssl_random_pseudo_bytes($ivLength);
        if ($iv === false) {
            throw new Exception('Failed to generate IV');
        }
        
        $encrypted = openssl_encrypt($data, self::$cipher, $key, OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) {
            throw new Exception('Encryption failed');
        }
        
        // 返回格式：iv + encrypted_data（都进行 base64 编码）
        return base64_encode($iv . $encrypted);
    }
    
    /**
     * 解密数据
     * 
     * @param string $encrypted 加密的数据（base64 编码）
     * @return string 解密后的原始数据
     */
    public static function decrypt(string $encrypted): string {
        if (empty($encrypted)) {
            return '';
        }
        
        $key = self::getKey();
        $data = base64_decode($encrypted, true);
        
        if ($data === false) {
            throw new Exception('Invalid encrypted data format');
        }
        
        $ivLength = openssl_cipher_iv_length(self::$cipher);
        if ($ivLength === false || strlen($data) < $ivLength) {
            throw new Exception('Invalid encrypted data length');
        }
        
        $iv = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);
        
        $decrypted = openssl_decrypt($encrypted, self::$cipher, $key, OPENSSL_RAW_DATA, $iv);
        if ($decrypted === false) {
            throw new Exception('Decryption failed');
        }
        
        return $decrypted;
    }
    
    /**
     * 安全地存储敏感数据到数据库
     * 自动检测是否已加密，如果未加密则加密后存储
     * 
     * @param string $data 原始数据
     * @return string 加密后的数据（如果原始数据为空则返回空字符串）
     */
    public static function store(string $data): string {
        if (empty($data)) {
            return '';
        }
        
        // 检查是否已经加密（加密后的数据以特定前缀标识）
        if (self::isEncrypted($data)) {
            return $data;
        }
        
        return 'ENC:' . self::encrypt($data);
    }
    
    /**
     * 从数据库安全地读取敏感数据
     * 自动检测是否已加密，如果加密则解密
     * 
     * @param string $stored 存储的数据
     * @return string 解密后的原始数据（如果存储数据为空则返回空字符串）
     */
    public static function retrieve(string $stored): string {
        if (empty($stored)) {
            return '';
        }
        
        // 检查是否已加密
        if (self::isEncrypted($stored)) {
            // 移除前缀并解密
            $encrypted = substr($stored, 4); // 移除 'ENC:' 前缀
            try {
                return self::decrypt($encrypted);
            } catch (Exception $e) {
                Logger::error("Failed to decrypt data: " . $e->getMessage());
                return ''; // 解密失败返回空字符串
            }
        }
        
        // 未加密的数据（兼容旧数据）
        return $stored;
    }
    
    /**
     * 检查数据是否已加密
     * 
     * @param string $data 要检查的数据
     * @return bool 是否已加密
     */
    private static function isEncrypted(string $data): bool {
        return strpos($data, 'ENC:') === 0;
    }
    
    /**
     * 批量加密字段（用于数据库迁移）
     * 
     * @param array $data 数据数组
     * @param array $encryptFields 需要加密的字段名列表
     * @return array 加密后的数据数组
     */
    public static function encryptFields(array $data, array $encryptFields): array {
        foreach ($encryptFields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                $data[$field] = self::store($data[$field]);
            }
        }
        return $data;
    }
    
    /**
     * 批量解密字段（用于从数据库读取）
     * 
     * @param array $data 数据数组
     * @param array $decryptFields 需要解密的字段名列表
     * @return array 解密后的数据数组
     */
    public static function decryptFields(array $data, array $decryptFields): array {
        foreach ($decryptFields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                $data[$field] = self::retrieve($data[$field]);
            }
        }
        return $data;
    }
}


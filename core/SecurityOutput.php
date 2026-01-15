<?php
/**
 * 安全输出工具类
 * 用于防止XSS攻击
 */
class SecurityOutput {
    /**
     * 安全地输出HTML内容
     * 
     * @param string $string 要输出的字符串
     * @param int $flags htmlspecialchars的标志
     * @param string $encoding 字符编码
     * @return string 转义后的字符串
     */
    public static function escape($string, $flags = ENT_QUOTES, $encoding = 'UTF-8') {
        if ($string === null) {
            return '';
        }
        return htmlspecialchars($string, $flags, $encoding);
    }
    
    /**
     * 安全地输出HTML属性
     * 
     * @param string $string 要输出的字符串
     * @return string 转义后的字符串
     */
    public static function escapeAttr($string) {
        if ($string === null) {
            return '';
        }
        return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    /**
     * 安全地输出URL
     * 
     * @param string $url 要输出的URL
     * @return string 转义后的URL
     */
    public static function escapeUrl($url) {
        if ($url === null) {
            return '';
        }
        return htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    
    /**
     * 安全地输出JavaScript内容
     * 
     * @param string $string 要输出的字符串
     * @return string 转义后的字符串
     */
    public static function escapeJs($string) {
        if ($string === null) {
            return '';
        }
        return json_encode($string, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }
    
    /**
     * 递归转义数组中的所有值
     * 
     * @param array $data 要转义的数组
     * @return array 转义后的数组
     */
    public static function escapeArray($data) {
        if (!is_array($data)) {
            return self::escape($data);
        }
        
        $escaped = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $escaped[$key] = self::escapeArray($value);
            } else {
                $escaped[$key] = self::escape($value);
            }
        }
        
        return $escaped;
    }
    
    /**
     * 安全地输出原始HTML（只允许特定标签）
     * 
     * @param string $html 要输出的HTML
     * @param array $allowedTags 允许的标签
     * @return string 过滤后的HTML
     */
    public static function escapeHtml($html, $allowedTags = null) {
        if ($html === null) {
            return '';
        }
        
        // 如果没有指定允许的标签，默认允许基本格式化标签
        if ($allowedTags === null) {
            $allowedTags = '<p><br><strong><em><u><ol><ul><li><a><h1><h2><h3><h4><h5><h6><pre><code>';
        }
        
        return strip_tags($html, $allowedTags);
    }
}
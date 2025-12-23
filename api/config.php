<?php
/**
 * 数据库配置文件
 * 
 * 重要：请根据您的实际环境修改以下配置
 * 
 * 安全建议：
 * 1. 不要使用root用户
 * 2. 使用强密码
 * 3. 设置适当的文件权限 (chmod 640)
 * 4. 考虑使用环境变量存储敏感信息
 */

// 数据库连接配置
$db_config = [
    'host' => 'localhost',             // 数据库主机
    'port' => 3306,                  // 数据库端口
    'dbname' => 'dbname',  // 数据库名
    'username' => 'username', // 数据库用户名
    'password' => 'password', // 数据库密码
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ]
];

/**
 * 获取数据库连接
 */
function getDBConnection() {
    global $db_config;
    
    try {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $db_config['host'],
            $db_config['port'],
            $db_config['dbname'],
            $db_config['charset']
        );
        
        return new PDO($dsn, $db_config['username'], $db_config['password'], $db_config['options']);
        
    } catch (PDOException $e) {
        error_log("数据库连接失败: " . $e->getMessage());
        return null;
    }
}

/**
 * 环境变量支持
 * 
 * 如果设置了环境变量，优先使用环境变量覆盖配置
 */
if (!empty($_ENV['DB_HOST'])) {
    $db_config['host'] = $_ENV['DB_HOST'];
}
if (!empty($_ENV['DB_PORT'])) {
    $db_config['port'] = (int)$_ENV['DB_PORT'];
}
if (!empty($_ENV['DB_NAME'])) {
    $db_config['dbname'] = $_ENV['DB_NAME'];
}
if (!empty($_ENV['DB_USER'])) {
    $db_config['username'] = $_ENV['DB_USER'];
}
if (!empty($_ENV['DB_PASS'])) {
    $db_config['password'] = $_ENV['DB_PASS'];
}
?>
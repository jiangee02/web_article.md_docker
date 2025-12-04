<?php
// config.php - 数据库配置 (从 .env 文件读取)
define('DB_HOST', getenv('DB_HOST') ?: 'mysql');
define('DB_USER', 'root');
define('DB_PASS', getenv('DB_ROOT_PASSWORD') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'article');
define('REDIS_HOST', getenv('REDIS_HOST') ?: 'redis');
define('REDIS_PASS', getenv('REDIS_PASSWORD') ?: '');
define('REDIS_PORT', getenv('REDIS_PORT') ?: '6379');
define('DB_CHARSET', 'utf8mb4');

// 验证必需的环境变量
if (empty(getenv('DB_ROOT_PASSWORD'))) {
    // 生产环境应记录日志，这里简单提示
    error_log('警告: 数据库密码(DB_ROOT_PASSWORD)未在环境变量中设置，使用默认空值。');
}

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("数据库连接失败: " . $e->getMessage());
}

// 可选：如果需要Redis，可在此处添加Redis连接函数
?>

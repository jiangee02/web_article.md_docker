<?php
// test_redis.php - 测试 Redis 连接
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h3>Redis 连接测试</h3>";

// Redis 配置 - 从你的 config.php 中获取相同配置
$redis_host = getenv('REDIS_HOST') ?: 'redis';
$redis_port = getenv('REDIS_PORT') ?: 6379;
$redis_pass = getenv('REDIS_PASSWORD') ?: '';

echo "配置信息:<br>";
echo "主机: $redis_host<br>";
echo "端口: $redis_port<br>";
echo "密码: " . ($redis_pass ? '已设置' : '无') . "<br><br>";

// 检查 Redis 扩展是否安装
if (!extension_loaded('redis')) {
    die("<span style='color: red;'>✗ Redis PHP 扩展未安装</span><br>
         请安装 Redis 扩展: sudo apt-get install php-redis 或 pecl install redis");
}

try {
    // 创建 Redis 连接
    $redis = new Redis();
    
    echo "尝试连接到 Redis...<br>";
    
    // 设置超时时间
    $timeout = 3; // 3秒超时
    
    // 连接 Redis
    $connected = $redis->connect($redis_host, $redis_port, $timeout);
    
    if (!$connected) {
        throw new Exception("连接失败");
    }
    
    echo "<span style='color: green;'>✓ Redis 连接成功</span><br><br>";
    
    // 认证（如果有密码）
    if ($redis_pass) {
        if ($redis->auth($redis_pass)) {
            echo "<span style='color: green;'>✓ Redis 认证成功</span><br>";
        } else {
            echo "<span style='color: red;'>✗ Redis 认证失败</span><br>";
        }
    }
    
    // 测试基本操作
    echo "<h4>测试基本操作</h4>";
    
    // 1. 设置一个测试键
    $testKey = 'test_key_' . time();
    $testValue = 'Hello Redis!';
    
    if ($redis->set($testKey, $testValue)) {
        echo "✓ 设置键: $testKey = $testValue<br>";
    } else {
        echo "✗ 设置键失败<br>";
    }
    
    // 2. 获取键值
    $retrievedValue = $redis->get($testKey);
    if ($retrievedValue === $testValue) {
        echo "✓ 获取键值: $testKey = $retrievedValue<br>";
    } else {
        echo "✗ 获取键值失败<br>";
    }
    
    // 3. 设置过期时间
    if ($redis->expire($testKey, 60)) {
        echo "✓ 设置键过期时间: 60秒<br>";
    } else {
        echo "✗ 设置过期时间失败<br>";
    }
    
    // 4. 获取剩余生存时间
    $ttl = $redis->ttl($testKey);
    echo "键剩余生存时间: $ttl 秒<br>";
    
    // 5. 删除测试键
    if ($redis->del($testKey) > 0) {
        echo "✓ 删除测试键成功<br>";
    } else {
        echo "✗ 删除测试键失败<br>";
    }
    
    // 6. 测试列表操作
    echo "<h4>测试列表操作</h4>";
    $listKey = 'test_list_' . time();
    
    $redis->rPush($listKey, 'item1', 'item2', 'item3');
    $listLength = $redis->lLen($listKey);
    echo "列表长度: $listLength<br>";
    
    $firstItem = $redis->lPop($listKey);
    echo "弹出第一个元素: $firstItem<br>";
    
    $redis->del($listKey);
    
    // 7. 获取 Redis 信息
    echo "<h4>Redis 服务器信息</h4>";
    $info = $redis->info();
    
    echo "Redis 版本: " . ($info['redis_version'] ?? '未知') . "<br>";
    echo "运行天数: " . ($info['uptime_in_days'] ?? '未知') . " 天<br>";
    echo "内存使用: " . ($info['used_memory_human'] ?? '未知') . "<br>";
    echo "连接数: " . ($info['connected_clients'] ?? '未知') . "<br>";
    
    // 8. 测试发布/订阅
    echo "<h4>测试发布/订阅 (简单测试)</h4>";
    $channel = 'test_channel_' . time();
    
    // 创建一个简单的订阅测试
    if ($redis->publish($channel, '测试消息')) {
        echo "✓ 发布消息成功<br>";
    } else {
        echo "✗ 发布消息失败<br>";
    }
    
    // 获取当前数据库大小
    $dbSize = $redis->dbSize();
    echo "当前数据库键数量: $dbSize<br>";
    
    echo "<br><span style='color: green; font-weight: bold;'>✅ Redis 测试完成，所有功能正常！</span>";
    
} catch (Exception $e) {
    echo "<span style='color: red;'>✗ Redis 连接失败: " . $e->getMessage() . "</span><br>";
    
    // 提供调试建议
    echo "<h4>可能的问题和解决方案:</h4>";
    echo "<ol>";
    echo "<li>Redis 服务是否运行？运行: <code>docker ps | grep redis</code></li>";
    echo "<li>Redis 容器名是否正确？当前配置主机名为: <strong>$redis_host</strong></li>";
    echo "<li>网络是否互通？尝试在容器内测试: <code>docker-compose exec php ping redis</code></li>";
    echo "<li>Redis 是否需要密码？当前密码设置: " . ($redis_pass ? '是' : '否') . "</li>";
    echo "<li>检查防火墙/端口: Redis 默认端口 6379</li>";
    echo "</ol>";
    
    // 测试连接到 localhost 作为备用
    echo "<h4>备用测试（尝试 localhost）:</h4>";
    try {
        $redis2 = new Redis();
        if ($redis2->connect('localhost', 6379, 2)) {
            echo "<span style='color: green;'>✓ 可以连接到 localhost:6379</span><br>";
            $redis2->close();
        }
    } catch (Exception $e2) {
        echo "<span style='color: red;'>✗ 也无法连接到 localhost: " . $e2->getMessage() . "</span><br>";
    }
}
?>
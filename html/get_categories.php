<?php
// get_categories.php - 获取分类数据
require_once 'config.php';

$sql = "SELECT name, COUNT(articles.id) as article_count 
        FROM categories 
        LEFT JOIN articles ON categories.name = articles.category AND articles.status = 'published'
        GROUP BY categories.name 
        ORDER BY categories.name";

$stmt = $pdo->query($sql);
$categories = $stmt->fetchAll();

header('Content-Type: application/json');
echo json_encode($categories);
?>
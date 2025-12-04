<?php
// get_articles.php - 获取文章数据
require_once 'config.php';

// 获取参数
$category = isset($_GET['category']) ? $_GET['category'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10; // 每页显示数量
$offset = ($page - 1) * $limit;

// 构建查询条件
$whereConditions = ["status = 'published'"];
$params = [];

if (!empty($category) && $category !== '全部') {
    $whereConditions[] = "category = ?";
    $params[] = $category;
}

if (!empty($search)) {
    $whereConditions[] = "(title LIKE ? OR excerpt LIKE ? OR tags LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereClause = implode(' AND ', $whereConditions);

// 获取文章总数
$countSql = "SELECT COUNT(*) as total FROM articles WHERE $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalArticles = $countStmt->fetch()['total'];
$totalPages = ceil($totalArticles / $limit);

// 获取文章列表
$sql = "SELECT id, title, excerpt, category, tags, view_count, 
               DATE_FORMAT(created_at, '%Y-%m-%d') as created_date 
        FROM articles 
        WHERE $whereClause 
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?";

$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$articles = $stmt->fetchAll();

// 返回JSON数据
header('Content-Type: application/json');
echo json_encode([
    'articles' => $articles,
    'totalPages' => $totalPages,
    'currentPage' => $page,
    'totalArticles' => $totalArticles
]);
?>
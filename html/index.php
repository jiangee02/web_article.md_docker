<?php
// articles.php - 动态文章列表页面
require_once 'config.php';

// 获取参数
$currentCategory = isset($_GET['category']) ? $_GET['category'] : '';
$currentSearch = isset($_GET['search']) ? $_GET['search'] : '';
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10; // 每页显示数量
$offset = ($currentPage - 1) * $limit;

// 获取所有分类
$categorySql = "SELECT DISTINCT category FROM articles WHERE status = 'published' ORDER BY category";
$categoryStmt = $pdo->query($categorySql);
$categories = $categoryStmt->fetchAll(PDO::FETCH_COLUMN);

// 构建查询条件
$whereConditions = ["status = 'published'"];
$params = [];

if (!empty($currentCategory) && $currentCategory !== '全部') {
    $whereConditions[] = "category = ?";
    $params[] = $currentCategory;
}

if (!empty($currentSearch)) {
    $whereConditions[] = "(title LIKE ? OR excerpt LIKE ? OR tags LIKE ?)";
    $searchTerm = "%$currentSearch%";
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
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>文章列表 - Jiangee's Blog</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/layout.css">
    <link rel="stylesheet" href="css/article_index.css">
    <link rel="stylesheet" href="css/all.min.css">

</head>
<body>
    <!-- 视频背景 -->
    <div class="video-background">
        <video id="bg-video" autoplay muted loop>
            <source src="picture/bg.mp4" type="video/mp4">
            您的浏览器不支持视频标签。
        </video>
        <div class="video-overlay"></div>
    </div>

    <!-- 头部导航 -->
    <header id="header">
        <div class="header-content">
            <div class="logo">
                <i class="fas fa-code"></i>
                <img src="picture/logo.png" alt="Jiangee's Blog" class="logo-image">
            </div>
            <nav>
                <ul>
                    <li><a href="http://47.114.86.117"><i class="fas fa-home"></i> 首页</a></li>
                    <li><a href="#"><i class="fas fa-file-alt"></i> 文章</a></li>
                    <li><a href="#"><i class="fas fa-user"></i> 关于</a></li>
                    <li><a href="#"><i class="fas fa-envelope"></i> 留言板</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- 文章页面英雄区域 -->
    <section class="articles-hero">
        <h1>个人文章</h1>
        <p>记录学习过程中的思考与实践，分享技术心得与解决方案</p>
    </section>

    <!-- 主要内容 -->
    <div class="content-container">
        <div class="articles-container">
            <!-- 筛选和搜索区域 -->
            <div class="articles-filters">
                <div class="filter-categories">
                    <button class="filter-btn <?= empty($currentCategory) ? 'active' : '' ?>" 
                            data-category="">全部</button>
                    <?php foreach ($categories as $category): ?>
                    <button class="filter-btn <?= $currentCategory === $category ? 'active' : '' ?>" 
                            data-category="<?= htmlspecialchars($category) ?>">
                        <?= htmlspecialchars($category) ?>
                    </button>
                    <?php endforeach; ?>
                </div>
                <!-- <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="搜索文章..." value="<?= htmlspecialchars($currentSearch) ?>" id="search-input">
                </div> -->
            </div>

            <!-- 文章列表 -->
            <div class="articles-list" id="articles-list">
                <?php if (empty($articles)): ?>
                    <div class="no-articles">
                        <p>暂无相关文章</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($articles as $article): ?>
                    <div class="article-item">
                        <div class="article-header">
                            <div class="article-meta">
                                <div class="article-date"><?= $article['created_date'] ?></div>
                                <span class="article-category"><?= htmlspecialchars($article['category']) ?></span>
                            </div>
                            <div class="article-views">
                                <i class="far fa-eye"></i> <?= $article['view_count'] ?>
                            </div>
                        </div>
                        <h3 class="article-title">
                            <a href="article_detail.php?id=<?= $article['id'] ?>">
                                <?= htmlspecialchars($article['title']) ?>
                            </a>
                        </h3>
                        <p class="article-excerpt"><?= htmlspecialchars($article['excerpt']) ?></p>
                        <div class="article-footer">
                            <div class="article-tags">
                                <?php if (!empty($article['tags'])): 
                                    $tags = explode(',', $article['tags']);
                                    foreach ($tags as $tag): ?>
                                    <span class="article-tag"><?= trim(htmlspecialchars($tag)) ?></span>
                                <?php endforeach; endif; ?>
                            </div>
                            <a href="article_detail.php?id=<?= $article['id'] ?>" class="read-more">
                                阅读全文 <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- 分页 -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($currentPage > 1): ?>
                    <a href="?page=<?= $currentPage - 1 ?>&category=<?= urlencode($currentCategory) ?>&search=<?= urlencode($currentSearch) ?>" class="pagination-btn">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?= $i ?>&category=<?= urlencode($currentCategory) ?>&search=<?= urlencode($currentSearch) ?>" 
                       class="pagination-btn <?= $i == $currentPage ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($currentPage < $totalPages): ?>
                    <a href="?page=<?= $currentPage + 1 ?>&category=<?= urlencode($currentCategory) ?>&search=<?= urlencode($currentSearch) ?>" class="pagination-btn">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 返回顶部按钮 -->
    <div class="back-to-top" id="back-to-top">
        <i class="fas fa-chevron-up"></i>
    </div>

    <!-- 页脚 -->
    <footer>
        <div class="footer-content">
            <div class="footer-column">
                <h3>联系我</h3>

                    <a href="mailto:jiangee02@qq.com"><i class="fas fa-envelope"></i> 电子邮件</a>
                    <a href="#"><i class="fab fa-bilibili"></i> B站主页</a>
                    <a href="#"><i class="fab fa-github"></i> GitHub</a>

            </div>
        </div>
    </footer>

    <script>
        // 滚动效果
        const header = document.getElementById('header');
        const backToTop = document.getElementById('back-to-top');
        
        // 滚动时改变导航栏透明度
        window.addEventListener('scroll', function() {
            const scrollPosition = window.scrollY;
            
            // 导航栏效果
            if (scrollPosition > 100) {
                header.style.backgroundColor = 'rgba(255, 255, 255, 0.25)';
                header.style.boxShadow = '0 4px 15px rgba(0, 0, 0, 0.1)';
            } else {
                header.style.backgroundColor = 'rgba(255, 255, 255, 0.15)';
                header.style.boxShadow = '0 2px 10px rgba(0, 0, 0, 0.1)';
            }
            
            // 显示/隐藏返回顶部按钮
            if (scrollPosition > 500) {
                backToTop.classList.add('visible');
            } else {
                backToTop.classList.remove('visible');
            }
        });
        
        // 返回顶部功能
        backToTop.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        // 分类筛选功能
        document.querySelectorAll('.filter-btn').forEach(button => {
            button.addEventListener('click', function() {
                const category = this.getAttribute('data-category');
                const search = document.getElementById('search-input').value;
                window.location.href = `?category=${encodeURIComponent(category)}&search=${encodeURIComponent(search)}`;
            });
        });

        // 搜索功能
        // const searchInput = document.getElementById('search-input');
        // let searchTimeout;
        
        // searchInput.addEventListener('input', function() {
        //     clearTimeout(searchTimeout);
        //     searchTimeout = setTimeout(() => {
        //         const category = document.querySelector('.filter-btn.active').getAttribute('data-category');
        //         const search = this.value;
        //         window.location.href = `?category=${encodeURIComponent(category)}&search=${encodeURIComponent(search)}`;
        //     }, 500);
        // });

        // 回车键搜索
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const category = document.querySelector('.filter-btn.active').getAttribute('data-category');
                const search = this.value;
                window.location.href = `?category=${encodeURIComponent(category)}&search=${encodeURIComponent(search)}`;
            }
        });
    </script>
</body>
</html>

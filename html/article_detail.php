<?php
// article_detail.php - 
require_once 'config.php';
require_once 'Parsedown.php';



$articleId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($articleId <= 0) {
    header('Location: articles.php');
    exit;
}

// 获取文章详情
$sql = "SELECT * FROM articles WHERE id = ? AND status = 'published'";
$stmt = $pdo->prepare($sql);
$stmt->execute([$articleId]);
$article = $stmt->fetch();

if (!$article) {
    header('Location: articles.php');
    exit;
}

// ============================================
// 直接使用 Parsedown 转换 Markdown
// ============================================
$parsedown = new Parsedown();
$parsedown->setSafeMode(true); // 安全模式，防止XSS攻击

// 直接转换 Markdown，不进行任何高亮处理
if (isset($article['markdown_content'])) {
    $article['html_content'] = $parsedown->text($article['markdown_content']);
}


// 判断是否显示原始 Markdown
$showMarkdown = isset($_GET['raw']) && $_GET['raw'] === '1';

// 更新阅读量
$updateSql = "UPDATE articles SET view_count = view_count + 1 WHERE id = ?";
$pdo->prepare($updateSql)->execute([$articleId]);

// 获取相关文章
$relatedSql = "SELECT id, title FROM articles 
               WHERE category = ? AND id != ? AND status = 'published' 
               ORDER BY created_at DESC LIMIT 5";
$relatedStmt = $pdo->prepare($relatedSql);
$relatedStmt->execute([$article['category'], $articleId]);
$relatedArticles = $relatedStmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($article['title']) ?> - Jiangee's Blog</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/article_detail.css">
      <!-- 使用highlight.js 主题样式 -->
    <link rel="stylesheet" href="highlight/styles/vs2015.css">

    <style>
        * {
   	    user-select: text;article_detail
        }
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --accent-color: #e74c3c;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --text-color: #333;
            --border-radius: 12px;
            --box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }       
    </style>

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
                    <li><a href="index.php"><i class="fas fa-file-alt"></i> 文章</a></li>
                    <li><a href="#"><i class="fas fa-user"></i> 关于</a></li>
                    <li><a href="#"><i class="fas fa-envelope"></i> 留言板</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- 主要内容 -->
    <div class="content-container">
        <div class="article-layout">
            <!-- 文章主体内容 -->
            <div class="article-main">
                <div class="article-header">
                    <h1 class="article-title"><?= htmlspecialchars($article['title']) ?></h1>
                    <div class="article-meta">
                        <span><i class="far fa-calendar"></i> <?= $article['created_at'] ?></span>
                        <span><i class="fas fa-folder"></i> <?= htmlspecialchars($article['category']) ?></span>
                        <span><i class="far fa-eye"></i> <?= $article['view_count'] + 1 ?></span>
                    </div>
                    <div class="article-actions">
                        <a href="index.php" class="action-btn">返回列表</a>
                    </div>
                </div>
                
                <div class="article-content">
                    <?php if ($showMarkdown && !empty($article['markdown_content'])): ?>
                        <div class="raw-markdown"><?= htmlspecialchars($article['markdown_content']) ?></div>
                    <?php else: ?>
                        <div class="markdown-content" id="article-content">
                            <?= isset($article['html_content']) ? $article['html_content'] : '暂无内容' ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($relatedArticles)): ?>
                <div class="related-articles">
                    <h3>相关文章</h3>
                    <ul>
                        <?php foreach ($relatedArticles as $related): ?>
                        <li><a href="article_detail.php?id=<?= $related['id'] ?>"><?= htmlspecialchars($related['title']) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- 侧边栏目录 -->
            <div class="article-sidebar">
                <div class="toc-card">
                    <h3 class="toc-title">文章目录</h3>
                    <div id="toc-container">
                        <!-- 目录将通过JavaScript动态生成 -->
                    </div>
                </div>
            </div>
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
                <div class="contact-links">
                    <a href="mailto:jiangee02@qq.com"><i class="fas fa-envelope"></i> 电子邮件</a>
                    <a href="#"><i class="fab fa-bilibili"></i> B站主页</a>
                    <a href="#"><i class="fab fa-github"></i> GitHub</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- 引入本地的 highlight.js 和相关文件 -->
    <!-- 使用压缩版以提高性能 -->
    <script src="highlight/highlight.js"></script>
    <script src="highlight/languages/python.js"></script>
    <script src="highlight/languages/bash.js"></script>
    <script src="highlight/languages/markdown.js"></script>
    <script src="highlight/languages/shell.js"></script>
    <script src="highlight/languages/yaml.min.js"></script>
    <script src="highlight/languages/dockerfile.js"></script>
    <script src="highlight/languages/nginx.js"></script>
    <script src="highlight/languages/php.min.js"></script>
    <script src="highlight/languages/sql.min.js"></script>

    
    <script>hljs.highlightAll();</script>
    
        <script>
        // 生成文章目录
        function generateTOC() {
            const content = document.getElementById('article-content');
            if (!content) return;
            
            const headings = content.querySelectorAll('h1, h2, h3, h4');
            const tocContainer = document.getElementById('toc-container');
            
            if (headings.length === 0) {
                tocContainer.innerHTML = '<p>暂无目录</p>';
                return;
            }
            
            let tocHTML = '<ul class="toc-list">';
            
            headings.forEach((heading, index) => {
                // 为标题添加ID（如果还没有）
                if (!heading.id) {
                    heading.id = 'heading-' + index;
                }
                
                const level = parseInt(heading.tagName.substring(1));
                const text = heading.textContent;
                
                tocHTML += `<li class="toc-h${level}"><a href="#${heading.id}">${text}</a></li>`;
            });
            
            tocHTML += '</ul>';
            tocContainer.innerHTML = tocHTML;
        }
        
               
        // 滚动效果
        const header = document.getElementById('header');
        const backToTop = document.getElementById('back-to-top');
        
        window.addEventListener('scroll', function() {
            const scrollPosition = window.scrollY;
            
            if (scrollPosition > 100) {
                header.style.backgroundColor = 'rgba(255, 255, 255, 0.25)';
                header.style.boxShadow = '0 4px 15px rgba(0, 0, 0, 0.1)';
            } else {
                header.style.backgroundColor = 'rgba(255, 255, 255, 0.15)';
                header.style.boxShadow = '0 2px 10px rgba(0, 0, 0, 0.1)';
            }
            
            if (scrollPosition > 500) {
                backToTop.classList.add('visible');
            } else {
                backToTop.classList.remove('visible');
            }
        });
        
        backToTop.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
        
        // 页面加载完成后执行
        document.addEventListener('DOMContentLoaded', function() {
            generateTOC();
        });
    </script>

 
</body>
</html>

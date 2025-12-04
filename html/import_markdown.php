<?php
// import_markdown.php - 增强版 Markdown 导入脚本
require_once 'config.php';
require_once 'Parsedown.php';

class MarkdownImporter {
    private $pdo;
    private $parsedown;
    private $importedMap = [];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->parsedown = new Parsedown();
        $this->parsedown->setSafeMode(false);
    }
    
  
    /**
     * 主导入函数
     */
    public function importFromDirectory($directory, $options = []) {
        $defaultOptions = [
            'clear_existing'       => false,      // 是否清空现有文章
            'backup_before_clear'  => true,       // 清空前是否备份
            'order_by_filename'    => true,       // 是否按文件名排序
            'default_category'     => '未分类',   // 默认分类
            'default_status'       => 'published' // 默认状态
        ];
        $options = array_merge($defaultOptions, $options);

        echo "开始【增量导入】Markdown 文件（阅读量永久保留）\n";
        echo "目录: $directory\n";

        if ($options['clear_existing']) {
            echo "警告：你勾选了“清空现有文章”，这会导致阅读量归零！\n";
            $this->clearArticles($options['backup_before_clear']);
        }

        $files = $this->getSortedFiles($directory, $options['order_by_filename']);
        if (empty($files)) {
            echo "未找到 .md 文件\n";
            return 0;
        }

        $stats = ['inserted'=>0, 'updated'=>0, 'skipped'=>0, 'error'=>0];

        foreach ($files as $fileInfo) {
            $result = $this->importSingleFileSmart($fileInfo['file'], $options);
            $stats[$result['action']]++;

            $mark = $result['action'] === 'inserted' ? "✅新增" :
                    ($result['action'] === 'updated' ? "✅更新" :
                    ($result['action'] === 'skipped' ? "✅跳过" : "❌失败"));

            echo "$mark: {$result['title']}" . ($result['id'] ?? '') . "\n";
        }

        echo "\n导入完成！新增 {$stats['inserted']}，更新 {$stats['updated']}，跳过 {$stats['skipped']}，失败 {$stats['error']}\n";
        return $stats['inserted'] + $stats['updated'];
    }

    /**
     * 清空文章表
     */
    private function clearArticles($backup = true) {
        try {
            if ($backup) {
                // 创建备份表
                $backupTable = 'articles_backup_' . date('Ymd_His');
                $this->pdo->exec("CREATE TABLE $backupTable SELECT * FROM articles");
                echo "数据已备份到表: $backupTable\n";
            }
            
            // 清空文章表
            $this->pdo->exec("TRUNCATE TABLE articles");
            echo "文章表已清空\n";
            
        } catch (Exception $e) {
            throw new Exception("清空文章表失败: " . $e->getMessage());
        }
    }
    
    /**
     * 获取排序后的文件列表
     */
    private function getSortedFiles($directory, $orderByFilename = true) {
        $files = glob($directory . '/*.md');
        
        if (!$orderByFilename) {
            return array_map(function($file) {
                return ['file' => $file];
            }, $files);
        }
        
        // 按文件名排序
        $fileList = [];
        foreach ($files as $file) {
            $filename = basename($file, '.md');
            $order = $this->extractOrderFromFilename($filename);
            
            $fileList[] = [
                'file' => $file,
                'filename' => $filename,
                'order' => $order
            ];
        }
        
        // 按提取的顺序号排序
        usort($fileList, function($a, $b) {
            return $a['order'] - $b['order'];
        });
        
        return $fileList;
    }
    
    /**
     * 从文件名提取顺序号
     */
    private function extractOrderFromFilename($filename) {
        // 支持多种格式:
        // 01-文章标题.md
        // 01_文章标题.md  
        // 01.文章标题.md
        // 01 文章标题.md
        if (preg_match('/^(\d+)[\s\-_\.]?(.*)$/', $filename, $matches)) {
            return intval($matches[1]);
        }
        
        // 如果没有数字前缀，返回一个大数确保排在后面
        return 9999;
    }
    
    /**
     * 智能导入单个文件：根据完整原始文件名（含前缀数字）精确去重
     */
    private function importSingleFileSmart($filePath, $options) {
        try {
            if (!file_exists($filePath)) {
                return ['action' => 'error', 'error' => '文件不存在'];
            }

            // 【关键】用带 .md 后缀前的完整文件名作为唯一标识（例如：01-git-intro.md）
            $fullFilename = basename($filePath);                  // 01-git-intro.md
            $filenameNoExt = basename($filePath, '.md');          // 01-git-intro

            $rawContent = file_get_contents($filePath);
            if ($rawContent === false || trim($rawContent) === '') {
                return ['action' => 'error', 'error' => '文件为空'];
            }

            // 解析内容和元数据
            $metadata = $this->extractMetadata($rawContent, $filenameNoExt, $options);
            $htmlContent = $this->parsedown->text($rawContent);

            // 第一步：精确匹配曾经导入过的原始文件名（推荐加一个隐藏字段保存）
            $existing = $this->findByOriginalFilename($fullFilename);

            // 第二步：如果没找到，再用标题模糊匹配（防止重命名情况）
            if (!$existing) {
                $existing = $this->findByTitle($metadata['title']);
            }

            // 如果已存在且内容完全一致 → 跳过（不更新 updated_at）
            if ($existing && $existing['markdown_content'] === $rawContent) {
                return [
                    'action' => 'skipped',
                    'title' => $metadata['title'],
                    'id'    => $existing['id']
                ];
            }

            // 需要更新或新增
            $now = date('Y-m-d H:i:s');

            if ($existing) {
                // 更新操作：保留 view_count、created_at、id
                $sql = "UPDATE articles SET 
                            title = ?, excerpt = ?, content = ?, markdown_content = ?,
                            category = ?, tags = ?, status = ?, 
                            original_filename = ?,          -- 保存原始文件名（用于下次精准去重）
                            updated_at = NOW()
                        WHERE id = ?";

                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    $metadata['title'],
                    $metadata['excerpt'],
                    $htmlContent,
                    $rawContent,
                    $metadata['category'],
                    $metadata['tags'],
                    $metadata['status'],
                    $fullFilename,          // 关键：保存原始文件名
                    $existing['id']
                ]);

                return [
                    'action' => 'updated',
                    'title'  => $metadata['title'],
                    'id'     => $existing['id']
                ];
            } else {
                // 新增
                $sql = "INSERT INTO articles 
                        (title, excerpt, content, markdown_content, category, tags, status, 
                        created_at, updated_at, original_filename, view_count)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, 0)";

                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    $metadata['title'],
                    $metadata['excerpt'],
                    $htmlContent,
                    $rawContent,
                    $metadata['category'],
                    $metadata['tags'],
                    $metadata['status'],
                    $metadata['created_at'],
                    $fullFilename   // 第一次导入时就保存原始文件名
                ]);

                return [
                    'action' => 'inserted',
                    'title'  => $metadata['title'],
                    'id'     => $this->pdo->lastInsertId()
                ];
            }

        } catch (Exception $e) {
            return ['action' => 'error', 'error' => $e->getMessage()];
        }
    }
    
    /**
     * 根据原始文件名精确查找（最靠谱）
     */
    private function findByOriginalFilename($filename) {
        $stmt = $this->pdo->prepare("SELECT * FROM articles WHERE original_filename = ? LIMIT 1");
        $stmt->execute([$filename]);
        return $stmt->fetch();
    }

    /**
     * 标题模糊匹配（备选方案）
     */
    private function findByTitle($title) {
        $stmt = $this->pdo->prepare("SELECT * FROM articles WHERE title = ? LIMIT 1");
        $stmt->execute([$title]);
        return $stmt->fetch();
    }

    /**
     * 从文件内容提取元数据
     */
    private function extractMetadata($content, $filename, $options) {
        $metadata = [
            'title' => $this->cleanTitle($filename),
            'excerpt' => mb_substr(strip_tags($content), 0, 150) . '...',
            'category' => $options['default_category'],
            'tags' => '',
            'status' => $options['default_status'],
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // 尝试从 YAML Front Matter 提取元数据
        $hasFrontMatter = preg_match('/^---\s*\R(.*?)\R---\s*\R(.*)$/s', $content, $matches);
        
        if ($hasFrontMatter) {
            $frontMatter = $matches[1];
            $content = $matches[2]; // 移除 Front Matter 后的内容
            
            // 解析 YAML Front Matter
            $this->parseFrontMatter($frontMatter, $metadata);
        }
        

        
        // 如果没有 Front Matter 中的摘要，从内容生成摘要
        if ($metadata['excerpt'] === mb_substr(strip_tags($content), 0, 150) . '...') {
            $metadata['excerpt'] = $this->generateExcerpt($content, $hasFrontMatter ? $matches[2] : $content);
        }
        
        return $metadata;
    }
    
    /**
     * 清理标题（移除数字前缀等）
     */
    private function cleanTitle($filename) {
        // 移除数字前缀和分隔符
        $cleanName = preg_replace('/^\d+[\s\-_\.]?/', '', $filename);
        // 将下划线、连字符转换为空格
        $cleanName = str_replace(['_', '-'], ' ', $cleanName);
        // 首字母大写
        $cleanName = ucfirst($cleanName);
        
        return $cleanName;
    }
    
    /**
     * 解析 Front Matter
     */
    private function parseFrontMatter($frontMatter, &$metadata) {
        $lines = explode("\n", $frontMatter);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            if (preg_match('/^(\w+):\s*(.*)$/', $line, $matches)) {
                $key = strtolower(trim($matches[1]));
                $value = trim($matches[2]);
                
                switch ($key) {
                    case 'title':
                        $metadata['title'] = trim($value, '"\'');
                        break;
                    case 'category':
                        $metadata['category'] = trim($value, '"\'');
                        break;
                    case 'tags':
                        $metadata['tags'] = trim($value, '"\'');
                        break;
                    case 'excerpt':
                        $metadata['excerpt'] = trim($value, '"\'');
                        break;
                    case 'status':
                        $metadata['status'] = in_array($value, ['published', 'draft']) ? $value : 'published';
                        break;
                    case 'date':
                    case 'created_at':
                        $metadata['created_at'] = date('Y-m-d H:i:s', strtotime($value));
                        break;
                }
            }
        }
    }
    
    // /**
    //  * 获取内容的第一行（跳过空行）
    //  */
    // private function getFirstContentLine($content) {
    //     $lines = explode("\n", $content);
    //     foreach ($lines as $line) {
    //         $line = trim($line);
    //         if (!empty($line) && !preg_match('/^[#\-*>=]/', $line)) {
    //             return $line;
    //         }
    //     }
    //     return '';
    // }
    
    /**
     * 生成文章摘要
     */
    private function generateExcerpt($content, $plainContent = null) {
        if ($plainContent === null) {
            $plainContent = strip_tags($content);
        }
        
        $excerpt = mb_substr($plainContent, 0, 200);
        
        // 确保在完整的句子处截断
        $lastPeriod = strrpos($excerpt, '。');
        $lastExclamation = strrpos($excerpt, '！');
        $lastQuestion = strrpos($excerpt, '？');
        
        $cutPos = max($lastPeriod, $lastExclamation, $lastQuestion);
        
        if ($cutPos > 50) { // 确保摘要不要太短
            $excerpt = mb_substr($excerpt, 0, $cutPos + 3); // +3 为了包含标点符号
        }
        
        return $excerpt . (mb_strlen($plainContent) > 200 ? '...' : '');
    }
}

/**
 * 命令行使用说明
 */
function showUsage() {
    echo "Markdown 文件导入工具\n\n";
    echo "用法: php import_markdown.php [目录] [选项]\n\n";
    echo "选项:\n";
    echo "  --clear          导入前清空现有文章\n";
    echo "  --no-backup      清空时不备份数据\n";
    echo "  --no-order       不按文件名排序\n";
    echo "  --category=名称  设置默认分类\n";
    echo "  --status=状态    设置默认状态 (published/draft)\n";
    echo "  --help          显示此帮助信息\n\n";
    echo "示例:\n";
    echo "  php import_markdown.php ./markdown_files --clear --category=技术文章\n";
    echo "  php import_markdown.php ./my_articles --clear --no-order\n";
}

/**
 * 解析命令行参数
 */
function parseCommandLineArgs($argv) {
    $options = [
        'directory' => './markdown_files' // 默认目录
    ];
    
    // 第一个参数可能是目录
    if (isset($argv[1]) && !str_starts_with($argv[1], '--')) {
        $options['directory'] = $argv[1];
        array_shift($argv);
    }
    
    foreach ($argv as $arg) {
        if ($arg === '--clear') {
            $options['clear_existing'] = true;
        } elseif ($arg === '--no-backup') {
            $options['backup_before_clear'] = false;
        } elseif ($arg === '--no-order') {
            $options['order_by_filename'] = false;
        } elseif (str_starts_with($arg, '--category=')) {
            $options['default_category'] = substr($arg, 11);
        } elseif (str_starts_with($arg, '--status=')) {
            $options['default_status'] = substr($arg, 9);
        } elseif ($arg === '--help') {
            showUsage();
            exit(0);
        }
    }
    
    return $options;
}

// 命令行执行入口
if (php_sapi_name() === 'cli') {
    $options = parseCommandLineArgs($argv);
    
    try {
        $importer = new MarkdownImporter($pdo);
        $importedCount = $importer->importFromDirectory(
            $options['directory'], 
            array_diff_key($options, ['directory' => true])
        );
        
        exit($importedCount > 0 ? 0 : 1);
        
    } catch (Exception $e) {
        echo "错误: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// 网页执行入口（如果需要）
if (isset($_GET['action']) && $_GET['action'] === 'import') {
    header('Content-Type: text/plain; charset=utf-8');
    
    $directory = $_GET['directory'] ?? './markdown_files';
    $options = [
        'clear_existing' => isset($_GET['clear']),
        'backup_before_clear' => !isset($_GET['no_backup']),
        'order_by_filename' => !isset($_GET['no_order']),
        'default_category' => $_GET['category'] ?? '未分类',
        'default_status' => $_GET['status'] ?? 'published'
    ];
    
    try {
        $importer = new MarkdownImporter($pdo);
        $importedCount = $importer->importFromDirectory($directory, $options);
        
        echo "导入完成！成功导入 $importedCount 篇文章。";
        
    } catch (Exception $e) {
        echo "导入失败: " . $e->getMessage();
    }
}
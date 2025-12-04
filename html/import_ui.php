<?php
// import_ui.php - 导入管理界面
require_once 'config.php';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Markdown 导入工具</title>
    <style>
        .container { max-width: 600px; margin: 50px auto; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #3498db; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; }
        .result { margin-top: 20px; padding: 15px; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Markdown 文件增量导入工具</h1>
        <div style="background:#e8f5e8;padding:15px;border-radius:6px;margin:20px 0;font-size:14px;">
            <strong style="color:#27ae60;">安全增量模式</strong><br><br>
            • 相同文件（01-title.md）只会更新内容<br>
            • 阅读量、创建时间永久保留，不会归零<br>
            • 只有真正有变动的文章才会更新时间<br>
            • 只有你手动勾选“清空现有文章”才会删除旧数据
        </div>
        <form id="importForm">
            <div class="form-group">
                <label for="directory">Markdown 文件目录:</label>
                <input type="text" id="directory" name="directory" value="./md_files" required>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="clear" > 导入前清空现有文章
                </label>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="backup" checked> 清空前备份数据
                </label>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="order" checked> 按文件名排序
                </label>
            </div>
            
            <div class="form-group">
                <label for="category">默认分类:</label>
                <input type="text" id="category" name="category" value="未分类">
            </div>
            
            <div class="form-group">
                <label for="status">默认状态:</label>
                <select id="status" name="status">
                    <option value="published">已发布</option>
                    <option value="draft">草稿</option>
                </select>
            </div>
            
            <button type="submit">开始导入</button>
        </form>
        
        <div id="result"></div>
    </div>

    <script>
        document.getElementById('importForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const params = new URLSearchParams();
            
            params.append('action', 'import');
            for (let [key, value] of formData) {
                if (key === 'backup') {
                    if (!value) params.append('no_backup', '1');
                } else if (key === 'order') {
                    if (!value) params.append('no_order', '1');
                } else {
                    params.append(key, value);
                }
            }
            
            fetch('import_markdown.php?' + params.toString())
                .then(response => response.text())
                .then(result => {
                    document.getElementById('result').innerHTML = 
                        '<div class="success">' + result + '</div>';
                })
                .catch(error => {
                    document.getElementById('result').innerHTML = 
                        '<div class="error">导入失败: ' + error + '</div>';
                });
        });
    </script>
</body>
</html>

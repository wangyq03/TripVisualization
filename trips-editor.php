<?php
session_start();

// 检查是否已登录
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.html');
    exit();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>行程编辑 - 路线可视化展示系统</title>
    
    <!-- 公共样式 -->
    <link rel="stylesheet" href="css/common.css">
    
    <!-- 页面特定样式 -->
    <style>
        .preview-section {
            margin-top: 2rem;
            padding: 1.5rem;
            background: white;
            border-radius: 8px;
            border: 1px solid rgba(102, 126, 234, 0.2);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .preview-section h3 {
            color: #333;
            margin: 0 0 1rem 0;
            font-size: 1.25rem;
        }
        
        .preview-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            border: 1px solid #e1e5e9;
        }
        
        .preview-table th,
        .preview-table td {
            padding: 0.75rem;
            text-align: left;
            border: 1px solid #e1e5e9;
        }
        
        .preview-table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
        }
        
        .preview-table tr:hover {
            background: rgba(102, 126, 234, 0.05);
        }
        

        
        .file-info {
            margin-top: 1rem;
            padding: 1rem;
            background: rgba(40, 167, 69, 0.05);
            border-radius: 8px;
            border: 1px solid rgba(40, 167, 69, 0.2);
            display: none;
        }
        
        .format-example {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 6px;
            border-left: 4px solid #667eea;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
    </style>
    
    <!-- 百度统计 -->
    <script>
    var _hmt = _hmt || [];
    (function() {
      var hm = document.createElement("script");
      hm.src = "https://hm.baidu.com/hm.js?739d66c954a69a41a8630902e089bf79";
      var s = document.getElementsByTagName("script")[0]; 
      s.parentNode.insertBefore(hm, s);
    })();
    </script>
</head>
<body>
    <div class="main-content">
        <!-- 导航栏 -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="nav-brand">路线可视化展示系统</a>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="index.php" class="nav-link">
                        <span class="nav-icon">🗺️</span>
                        地图展示
                    </a>
                </li>
                <li class="nav-item">
                    <a href="trips-editor.php" class="nav-link active">
                        <span class="nav-icon">📝</span>
                        行程编辑
                    </a>
                </li>
                <li class="nav-item">
                    <a href="cities-manager.php" class="nav-link">
                        <span class="nav-icon">📍</span>
                        城市管理
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <div class="page-container">
        <!-- 文件上传 -->
        <div class="card">
            <h2 class="card-title">📁 上传行程文件</h2>
                
                <div class="upload-area" id="upload-area">
                    <div class="upload-icon">📤</div>
                    <div class="upload-text">点击选择文件或拖拽到此处</div>
                    <div class="upload-hint">支持 .csv、.xlsx、.xls 格式文件</div>
                    <input type="file" id="csv-file" accept=".csv,.xlsx,.xls" style="display: none;">
                </div>

                <div id="file-info" class="file-info">
                    <strong>选择的文件：</strong> <span id="file-name"></span><br>
                    <strong>文件大小：</strong> <span id="file-size"></span><br>
                    <strong>数据行数：</strong> <span id="data-count"></span>
                </div>

                <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                    <button id="preview-btn" class="btn btn-secondary" disabled>
                        <span>👁️</span> 预览数据
                    </button>
                    <button id="upload-btn" class="btn btn-primary" disabled>
                        <span>⬆️</span> 确认替换
                    </button>
                    <button id="download-template-btn" class="btn btn-success">
                        <span>📥</span> 下载模板
                    </button>
                </div>
            </div>

            <!-- 数据预览 -->
            <div id="preview-section" class="preview-section" style="display: none;">
                <h3>📋 数据预览</h3>
                <div id="preview-content" style="max-height: 400px; overflow-y: auto;"></div>
                <div style="margin-top: 1rem;">
                    <button id="confirm-upload" class="btn btn-danger">
                        <span>⚠️</span> 确认替换现有数据
                    </button>
                    <button id="cancel-upload" class="btn btn-secondary">
                        <span>❌</span> 取消操作
                    </button>
                </div>
            </div>

            <!-- 当前数据统计 -->
            <div class="card">
                <h2 class="card-title">📊 当前数据统计</h2>
                <div class="stats">
                    <div class="stat-card">
                        <div class="stat-number" id="current-trips">0</div>
                        <div class="stat-label">现有行程数</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number" id="current-cities">0</div>
                        <div class="stat-label">涉及城市数</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-date" id="date-range">-</div>
                        <div class="stat-label">日期范围</div>
                    </div>
                </div>
            </div>

            <!-- 格式要求 -->
            <div class="card">
                <h2 class="card-title">📋 文件格式要求</h2>
                <div class="alert alert-info">
                    <strong>支持格式：</strong>CSV (.csv)、Excel (.xlsx / .xls)
                    <div class="format-example" style="margin-top: 1rem;">
                        <strong>CSV/Excel格式示例：</strong><br>
                        <table style="width: 100%; margin-top: 0.5rem; border-collapse: collapse;">
                            <thead>
                                <tr style="background: rgba(102, 126, 234, 0.1);">
                                    <th style="padding: 0.5rem; border: 1px solid #ddd;">日期 (date)</th>
                                    <th style="padding: 0.5rem; border: 1px solid #ddd;">出发地 (origin)</th>
                                    <th style="padding: 0.5rem; border: 1px solid #ddd;">目的地 (destination)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td style="padding: 0.5rem; border: 1px solid #ddd;">2025-01-15</td>
                                    <td style="padding: 0.5rem; border: 1px solid #ddd;">北京</td>
                                    <td style="padding: 0.5rem; border: 1px solid #ddd;">上海</td>
                                </tr>
                                <tr>
                                    <td style="padding: 0.5rem; border: 1px solid #ddd;">2025-01-20</td>
                                    <td style="padding: 0.5rem; border: 1px solid #ddd;">上海</td>
                                    <td style="padding: 0.5rem; border: 1px solid #ddd;">深圳</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <small style="color: #666; display: block; margin-top: 1rem;">
                        <strong>说明：</strong><br>
                        • 列名：支持中英文（date/日期、origin/出发地、destination/目的地）<br>
                        • 日期：支持多种格式（YYYY-MM-DD、YYYY/MM/DD、Excel日期序列号）<br>
                        • 城市：中文名称，需与系统中的城市一致<br>
                        • 编码：CSV文件请使用UTF-8编码
                    </small>
                    <div style="margin-top: 1rem; padding: 0.75rem; background: rgba(255, 193, 7, 0.1); border-radius: 6px; border-left: 4px solid #ffc107;">
                        ⚠️ <strong>重要提醒：</strong>上传将<strong>全量替换</strong>现有数据，操作前请务必确认！
                    </div>
                </div>
            </div>
        </div>
<<<<<<< HEAD

        <!-- 底部区域 -->
        <footer class="footer">
            <div class="footer-container">
                <div class="footer-content">
                    <div class="footer-links">
                        <a href="https://tyt-maps.coolqing.com/exchangetools/" target="_blank" class="footer-link">
                            <span class="footer-link-icon">🔧</span>
                            新代调发货转换手机号工具
                        </a>
                    </div>
                    <div class="footer-text">
                        © <?php echo date('Y'); ?> 路线可视化展示系统
                    </div>
                </div>
            </div>
        </footer>
    </div>
    <!-- 百度统计 -->
    <script>
    var _hmt = _hmt || [];
    (function() {
      var hm = document.createElement("script");
      hm.src = "https://hm.baidu.com/hm.js?739d66c954a69a41a8630902e089bf79";
      var s = document.getElementsByTagName("script")[0]; 
      s.parentNode.insertBefore(hm, s);
    })();
    </script>
    <!-- JavaScript -->
=======
    </div>
>>>>>>> 38d2b0755fbbc3d10ba914acf4143cc3cdc98e1e
    <!-- SheetJS for Excel parsing -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    
    <script src="js/trips-editor.js?v=2025012001"></script>
    
    <!-- 用户菜单组件 -->
    <script src="js/user-menu.js"></script>
</body>
</html>
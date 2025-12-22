<?php
session_start();

// 检查是否已登录
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.html');
    exit();
}

// 检查用户权限 - 只有admin可以访问城市管理
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    // 如果不是admin，重定向到首页
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>城市管理 - 路线可视化展示系统</title>
    
    <!-- 公共样式 -->
    <link rel="stylesheet" href="css/common.css">
    
    <!-- 页面特定样式 -->
    <style>
        .cities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .city-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border: 2px solid transparent;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .city-card:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.2);
        }
        
        .city-name {
            font-size: 1.3rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .city-name::before {
            content: "📍";
        }
        
        .city-info {
            margin-bottom: 0.5rem;
            color: #555;
            display: flex;
            justify-content: space-between;
        }
        
        .city-label {
            font-weight: 500;
            color: #666;
        }
        
        .city-value {
            font-family: 'Courier New', monospace;
            background: rgba(102, 126, 234, 0.1);
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        
        .city-note {
            margin-top: 0.5rem;
            padding: 0.5rem;
            background: rgba(255, 193, 7, 0.1);
            border-radius: 6px;
            border-left: 3px solid #ffc107;
            font-size: 0.85rem;
            color: #856404;
        }
        
        .delete-city {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: #dc3545;
            color: white;
            border: none;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            transition: all 0.3s ease;
        }
        
        .delete-city:hover {
            background: #c82333;
            transform: scale(1.1);
        }
        
        .data-input-section {
            background: rgba(102, 126, 234, 0.05);
            border: 2px dashed #667eea;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .format-guide {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid #667eea;
            margin-bottom: 1rem;
        }
        
        .format-guide h4 {
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .format-guide .example {
            background: #e9ecef;
            padding: 0.75rem;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-box {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .stat-box .number {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 0.5rem;
        }
        
        .stat-box .label {
            color: #666;
            font-size: 0.9rem;
        }
        
        .validation-error {
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.3);
            border-radius: 6px;
            padding: 1rem;
            margin-top: 1rem;
            color: #721c24;
            display: none;
        }
        
        .validation-success {
            background: rgba(40, 167, 69, 0.1);
            border: 1px solid rgba(40, 167, 69, 0.3);
            border-radius: 6px;
            padding: 1rem;
            margin-top: 1rem;
            color: #155724;
            display: none;
        }
    </style>
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
                    <a href="trips-editor.php" class="nav-link">
                        <span class="nav-icon">📝</span>
                        行程编辑
                    </a>
                </li>
                <li class="nav-item">
                    <a href="cities-manager.php" class="nav-link active">
                        <span class="nav-icon">📍</span>
                        城市管理
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <div class="page-container">
        <div class="page-content">
            <!-- 统计信息 -->
            <div class="stats-row">
                <div class="stat-box">
                    <div class="number" id="total-cities">0</div>
                    <div class="label">总城市数</div>
                </div>
                <div class="stat-box">
                    <div class="number" id="valid-cities">0</div>
                    <div class="label">有效坐标</div>
                </div>
                <div class="stat-box">
                    <div class="number" id="with-notes">0</div>
                    <div class="label">带备注</div>
                </div>
                <div class="stat-box">
                    <div class="number" id="recent-updates">0</div>
                    <div class="label">今日更新</div>
                </div>
            </div>

            <!-- 数据录入区域 -->
            <div class="card">
                <h2 class="card-title">➕ 批量添加城市</h2>
                
                <div class="data-input-section">
                    <div class="format-guide">
                        <h4>📝 格式要求</h4>
                        <p>每行一个城市，格式为：<strong>城市,北纬,东经,备注</strong></p>
                        <div class="example">
                            北京,39.9042,116.4074,首都<br>
                            上海,31.2304,121.4737,经济中心<br>
                            广州,23.1291,113.2644,南方门户
                        </div>
                        <small style="color: #666;">
                            • 城市名称：中文名称，如"北京"<br>
                            • 北纬：十进制格式，如 39.9042<br>
                            • 东经：十进制格式，如 116.4074<br>
                            • 备注：可选，可留空
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="cities-data">城市数据（多行输入）</label>
                        <textarea 
                            id="cities-data" 
                            class="form-textarea" 
                            placeholder="请输入城市数据，每行一个城市...&#10;例如：&#10;北京,39.9042,116.4074,首都&#10;上海,31.2304,121.4737"
                            rows="8"
                        ></textarea>
                    </div>

                    <div class="validation-error" id="validation-error">
                        <!-- 验证错误信息将显示在这里 -->
                    </div>

                    <div class="validation-success" id="validation-success">
                        <!-- 验证成功信息将显示在这里 -->
                    </div>

                    <div style="margin-top: 1.5rem; display: flex; gap: 1rem;">
                        <button id="validate-btn" class="btn btn-secondary">
                            <span>✅</span> 验证数据
                        </button>
                        <button id="add-cities-btn" class="btn btn-primary">
                            <span>➕</span> 添加城市
                        </button>
                        <button id="clear-btn" class="btn btn-secondary">
                            <span>🗑️</span> 清空
                        </button>
                    </div>
                </div>
            </div>

            <!-- 现有城市列表 -->
            <div class="card">
                <h2 class="card-title">🏙️ 现有城市列表</h2>
                <div id="cities-list">
                    <div class="loading">正在加载城市数据...</div>
                </div>
            </div>
        </div>
    <!-- JavaScript -->
    <script src="js/cities-manager.js?v=2024011903"></script>
    
    <!-- 用户菜单组件 -->
    <script src="js/user-menu.js"></script>
</body>
</html>
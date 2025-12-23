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
    <title>地图展示 - 路线可视化展示系统</title>
    
    <!-- 公共样式 - 高德地图版本 -->
    <link rel="stylesheet" href="css/common.css">
    
    <!-- 高德地图 CSS -->
    <style>
        .map-container {
            position: relative;
        }
        
        #map {
            height: 500px !important;
            width: 100% !important;
            border-radius: 8px;
        }
        
        /* 全屏模式下的地图 */
        .map-container.fullscreen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw !important;
            height: 100vh !important;
            z-index: 9999;
            background: white;
        }
        
        .map-container.fullscreen #map {
            height: 100vh !important;
            width: 100vw !important;
            border-radius: 0;
        }
        
        /* 全屏按钮 */
        .fullscreen-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 100;
            background: white;
            border: 2px solid #667eea;
            color: #667eea;
            padding: 10px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
        }
        
        .fullscreen-btn:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .fullscreen-btn:active {
            transform: translateY(0);
        }
        
        .fullscreen-icon {
            font-size: 16px;
        }
        
        /* 退出全屏按钮 */
        .map-container.fullscreen .fullscreen-btn {
            background: #f44336;
            border-color: #f44336;
            color: white;
        }
        
        .map-container.fullscreen .fullscreen-btn:hover {
            background: #d32f2f;
            border-color: #d32f2f;
        }
        
        /* 地图加载提示 */
        .map-loading {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            color: #004085;
            padding: 15px;
            margin: 15px 0;
            border-radius: 8px;
            display: none;
        }
        
        .trip-item {
            background: white;
            padding: 1rem;
            margin-bottom: 0.75rem;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .trip-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            border-color: #667eea;
        }
        
        .trip-item.selected {
            background: #667eea;
            border-color: #5568d3;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.5);
        }
        
        .trip-item.selected .trip-date,
        .trip-item.selected .trip-route {
            color: white;
        }
        
        .trip-item.selected .arrow {
            color: white;
        }
        
        .trip-date {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 0.25rem;
        }
        
        .trip-route {
            font-weight: 600;
            color: #333;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .arrow {
            color: #667eea;
            font-weight: bold;
        }
        
        .no-results {
            text-align: center;
            padding: 3rem;
            color: #999;
        }
        
        .no-results::before {
            content: "📍";
            display: block;
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        /* 地图图例 */
        .map-legend {
            position: absolute;
            top: 10px;
            left: 10px;
            z-index: 100;
            background: white;
            padding: 10px 14px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            font-size: 13px;
        }
        
        .map-legend-title {
            font-weight: bold;
            margin-bottom: 8px;
            color: #333;
            font-size: 13px;
            border-bottom: 2px solid #667eea;
            padding-bottom: 4px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            margin-bottom: 6px;
            gap: 8px;
        }
        
        .legend-item:last-child {
            margin-bottom: 0;
        }
        
        .legend-icon {
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .legend-icon svg {
            width: 100%;
            height: 100%;
        }
        
        .legend-label {
            color: #555;
            font-size: 12px;
        }
        
        /* 时序开关 - 独立模块 */
        .sequence-toggle-control {
            position: absolute;
            top: 10px;
            left: 150px;
            z-index: 100;
            background: white;
            padding: 8px 12px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .sequence-toggle-label {
            font-size: 13px;
            color: #555;
            white-space: nowrap;
            font-weight: 500;
        }
        
        /* 开关样式 */
        .switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
        }
        
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .3s;
            border-radius: 24px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: #667eea;
        }
        
        input:checked + .slider:before {
            transform: translateX(20px);
        }
        
        .slider:hover {
            box-shadow: 0 0 4px rgba(102, 126, 234, 0.5);
        }
        
        /* 全屏模式下的控件位置 */
        .map-container.fullscreen .map-legend {
            top: 10px;
            left: 10px;
        }
        
        .map-container.fullscreen .sequence-toggle-control {
            top: 10px;
            left: 150px;
        }
        
        /* 全屏模式下的行程列表侧边栏 */
        .fullscreen-trip-list {
            position: fixed;
            top: 0;
            right: -220px;
            width: 180px;
            height: 100vh;
            background: white;
            box-shadow: -2px 0 8px rgba(0, 0, 0, 0.2);
            z-index: 10000;
            transition: right 0.3s ease;
            display: none;
            flex-direction: column;
        }
        
        .map-container.fullscreen .fullscreen-trip-list {
            display: flex;
        }
        
        .fullscreen-trip-list.expanded {
            right: 0;
        }
        
        /* 展开按钮 */
        .trip-list-toggle-btn {
            position: fixed;
            top: 50%;
            right: 0;
            transform: translateY(-50%);
            background: white;
            border: 2px solid #667eea;
            border-right: none;
            color: #667eea;
            padding: 20px 8px;
            border-radius: 8px 0 0 8px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            writing-mode: vertical-rl;
            text-orientation: mixed;
            box-shadow: -2px 0 8px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
            z-index: 9999;
            display: none;
        }
        
        .map-container.fullscreen .trip-list-toggle-btn {
            display: block;
        }
        
        .fullscreen-trip-list.expanded + .trip-list-toggle-btn {
            display: none;
        }
        
        .trip-list-toggle-btn:hover {
            background: #667eea;
            color: white;
            transform: translateY(-50%) translateX(-3px);
        }
        
        /* 行程列表头部 */
        .fullscreen-trip-list-header {
            display: flex;
            align-items: center;
            padding: 10px;
            background: #667eea;
            color: white;
            font-size: 15px;
            font-weight: 600;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            flex-shrink: 0;
        }
        
        .fullscreen-trip-list-close {
            cursor: pointer;
            margin-right: 8px;
            font-size: 16px;
            font-weight: bold;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 3px;
            transition: background 0.2s;
        }
        
        .fullscreen-trip-list-close:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        /* 行程列表内容 */
        .fullscreen-trip-list-content {
            flex: 1;
            overflow-y: auto;
            padding: 8px;
        }
        
        /* 行程分组容器 */
        .trip-group {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 6px;
            margin-bottom: 10px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .fullscreen-trip-item {
            background: #f8f9fa;
            padding: 8px 8px 8px 12px;
            margin-bottom: 6px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 1px solid transparent;
            font-size: 11px;
            position: relative;
        }
        
        .trip-group .fullscreen-trip-item {
            margin-bottom: 5px;
            background: white;
        }
        
        .trip-group .fullscreen-trip-item:last-child {
            margin-bottom: 0;
        }
        
        .fullscreen-trip-item:hover {
            background: #e7f3ff;
            border-color: #667eea;
            transform: translateX(-2px);
        }
        
        .fullscreen-trip-item.selected {
            background: #667eea;
            border-color: #5568d3;
            color: white;
        }
        
        .fullscreen-trip-item.selected .fullscreen-trip-date,
        .fullscreen-trip-item.selected .fullscreen-trip-arrow {
            color: rgba(255, 255, 255, 0.9);
        }
        
        .fullscreen-trip-item.selected .fullscreen-trip-route {
            color: white;
        }
        
        .fullscreen-trip-date {
            font-size: 12px;
            color: #666;
            margin-bottom: 3px;
        }
        
        .fullscreen-trip-route {
            font-weight: 600;
            color: #333;
            font-size: 15px;
            line-height: 1.3;
        }
        
        .fullscreen-trip-arrow {
            color: #667eea;
            font-size: 10px;
            margin: 0 2px;
        }
        
        /* 滚动条样式 */
        .fullscreen-trip-list-content::-webkit-scrollbar {
            width: 6px;
        }
        
        .fullscreen-trip-list-content::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        .fullscreen-trip-list-content::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 3px;
        }
        
        .fullscreen-trip-list-content::-webkit-scrollbar-thumb:hover {
            background: #5568d3;
        }
    </style>

</head>
<body>
    <div class="main-content">
        <!-- 导航栏 -->
<<<<<<< HEAD
    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="nav-brand">路线可视化展示系统</a>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="index.php" class="nav-link active">
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
                    <a href="cities-manager.php" class="nav-link">
                        <span class="nav-icon">📍</span>
                        城市管理
                    </a>
                </li>
            </ul>
        </div>
    </nav>
=======
        <nav class="navbar">
            <div class="nav-container">
                <a href="index.php" class="nav-brand">路线可视化展示系统</a>
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="index.php" class="nav-link active">
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
                        <a href="cities-manager.php" class="nav-link">
                            <span class="nav-icon">📍</span>
                            城市管理
                        </a>
                    </li>
                </ul>
            </div>
        </nav>
>>>>>>> 38d2b0755fbbc3d10ba914acf4143cc3cdc98e1e

        <div class="page-container">
            <!-- 日期筛选 -->
            <div class="card">
                <h2 class="card-title">📅 日期筛选</h2>
                <form id="filter-form" style="display: flex; gap: 1rem; align-items: end; flex-wrap: wrap; justify-content: space-between;">
                    <div style="display: flex; gap: 1rem; align-items: end; flex: 1;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" for="start-date">开始日期</label>
                            <input type="date" id="start-date" name="start_date" max="" class="form-input">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" for="end-date">结束日期</label>
                            <input type="date" id="end-date" name="end_date" max="" class="form-input">
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <span>🔍</span> 筛选行程
                        </button>
                    </div>
                    <button type="button" id="refresh-cache" class="btn btn-secondary" onclick="forceRefresh()" style="margin-left: auto;">
                        <span>🔄</span> 强制刷新
                    </button>
                </form>
            </div>

            <!-- 地图区域 -->
            <div class="card">
                <h2 class="card-title">🗺️ 路线地图</h2>
                <div id="map-loading" class="map-loading">
                    <strong>📍 地图加载中：</strong> 
                    正在加载高德地图服务...
                </div>
                <div class="map-container" id="map-container">
                    <button class="fullscreen-btn" id="fullscreen-btn" title="全屏显示">
                        <span class="fullscreen-icon">⛶</span>
                        <span class="fullscreen-text">全屏</span>
                    </button>
                    <div id="map"></div>
                    
                    <!-- 地图图例 -->
                    <div class="map-legend">
                        <div class="map-legend-title">📍 图例说明</div>
                        
                        <div class="legend-item">
                            <div class="legend-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                    <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" 
                                        fill="#4CAF50" stroke="white" stroke-width="1"/>
                                </svg>
                            </div>
                            <span class="legend-label">周期起始点</span>
                        </div>
                        
                        <div class="legend-item">
                            <div class="legend-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                    <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" 
                                        fill="#f44336" stroke="white" stroke-width="1"/>
                                </svg>
                            </div>
                            <span class="legend-label">周期结束点</span>
                        </div>
                        
                        <div class="legend-item">
                            <div class="legend-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 8 8">
                                    <circle cx="4" cy="4" r="3" fill="#4CAF50" stroke="white" stroke-width="1"/>
                                </svg>
                            </div>
                            <span class="legend-label">行程起点</span>
                        </div>
                        
                        <div class="legend-item">
                            <div class="legend-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 8 8">
                                    <circle cx="4" cy="4" r="3" fill="#f44336" stroke="white" stroke-width="1"/>
                                </svg>
                            </div>
                            <span class="legend-label">行程终点</span>
                        </div>
                    <!-- 时序开关 -->
                    <div class="sequence-toggle-control">
                        <span class="sequence-toggle-label">显示时序</span>
                        <label class="switch">
                            <input type="checkbox" id="show-sequence-toggle">
                            <span class="slider"></span>
                        </label>
                    </div>
                    
                    <!-- 全屏模式下的行程列表侧边栏 -->
                    <div class="fullscreen-trip-list" id="fullscreen-trip-list">
                        <div class="fullscreen-trip-list-header">
                            <div class="fullscreen-trip-list-close" id="fullscreen-trip-list-close">>></div>
                            <span>📋 行程列表</span>
                        </div>
                        <div class="fullscreen-trip-list-content" id="fullscreen-trip-list-content">
                            <div class="loading">正在加载...</div>
                        </div>
                    </div>
                    
                    <!-- 展开行程列表按钮 -->
                    <button class="trip-list-toggle-btn" id="trip-list-toggle-btn">
                        行程列表
                    </button>
                </div>
            </div>

            <!-- 统计和行程列表 -->
            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 2rem;">
                <!-- 统计信息 -->
                <div class="card">
                    <h2 class="card-title">📊 数据统计</h2>
                    <div class="stats">
                        <div class="stat-card">
                            <div class="stat-number" id="total-trips">0</div>
                            <div class="stat-label">总行程数</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number" id="unique-cities">0</div>
                            <div class="stat-label">涉及城市</div>
                        </div>
                    </div>
<<<<<<< HEAD
                <!-- 时序开关 -->
                <div class="sequence-toggle-control">
                    <span class="sequence-toggle-label">显示时序</span>
                    <label class="switch">
                        <input type="checkbox" id="show-sequence-toggle">
                        <span class="slider"></span>
                    </label>
                </div>
                
                <!-- 全屏模式下的行程列表侧边栏 -->
                <div class="fullscreen-trip-list" id="fullscreen-trip-list">
                    <div class="fullscreen-trip-list-header">
                        <div class="fullscreen-trip-list-close" id="fullscreen-trip-list-close">>></div>
                        <span>📋 行程列表</span>
                    </div>
                    <div class="fullscreen-trip-list-content" id="fullscreen-trip-list-content">
                        <div class="loading">正在加载...</div>
=======
                </div>
                
                <!-- 行程列表 -->
                <div class="card">
                    <h2 class="card-title">📋 行程列表</h2>
                    <div id="trip-list" style="max-height: 400px; overflow-y: auto;">
                        <div class="loading">正在加载行程数据...</div>
>>>>>>> 38d2b0755fbbc3d10ba914acf4143cc3cdc98e1e
                    </div>
                </div>
            </div>
        </div>

        <!-- 高德地图 API (使用免费版本，无需申请key) -->
        <script src="https://webapi.amap.com/maps?v=2.0&key=YOUR_AMAP_KEY"></script>
        
        <!-- 地图初始化脚本 -->
        <script>
            // 显示地图加载提示
            document.addEventListener('DOMContentLoaded', function() {
                const loadingEl = document.getElementById('map-loading');
                if (loadingEl) {
                    loadingEl.style.display = 'block';
                    // 地图加载成功后隐藏提示
                    setTimeout(() => {
                        loadingEl.style.display = 'none';
                    }, 2000);
                }
                
                // 全屏功能初始化
                initFullscreenButton();
            });
            
            // 全屏功能
            function initFullscreenButton() {
                const fullscreenBtn = document.getElementById('fullscreen-btn');
                const mapContainer = document.getElementById('map-container');
                const fullscreenIcon = fullscreenBtn.querySelector('.fullscreen-icon');
                const fullscreenText = fullscreenBtn.querySelector('.fullscreen-text');
                
                let isFullscreen = false;
                
                fullscreenBtn.addEventListener('click', function() {
                    if (!isFullscreen) {
                        // 进入全屏
                        mapContainer.classList.add('fullscreen');
                        fullscreenIcon.textContent = '⛶';
                        fullscreenText.textContent = '退出全屏';
                        isFullscreen = true;
                        
                        // 触发地图resize事件以适应新尺寸
                        if (window.map) {
                            setTimeout(() => {
                                map.resize();
                            }, 100);
                        }
                        
                        // 初始化全屏行程列表
                        initFullscreenTripList();
                    } else {
                        // 退出全屏
                        mapContainer.classList.remove('fullscreen');
                        fullscreenIcon.textContent = '⛶';
                        fullscreenText.textContent = '全屏';
                        isFullscreen = false;
                        
                        // 关闭行程列表
                        const tripList = document.getElementById('fullscreen-trip-list');
                        if (tripList) {
                            tripList.classList.remove('expanded');
                        }
                        
                        // 触发地图resize事件
                        if (window.map) {
                            setTimeout(() => {
                                map.resize();
                            }, 100);
                        }
                    }
                });
                
                // ESC键退出全屏
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && isFullscreen) {
                        fullscreenBtn.click();
                    }
                });
            }
            
            // 初始化全屏行程列表
            function initFullscreenTripList() {
                const toggleBtn = document.getElementById('trip-list-toggle-btn');
                const tripList = document.getElementById('fullscreen-trip-list');
                const closeBtn = document.getElementById('fullscreen-trip-list-close');
                
                if (!toggleBtn || !tripList || !closeBtn) return;
                
                // 展开按钮
                toggleBtn.addEventListener('click', function() {
                    tripList.classList.add('expanded');
                });
                
                // 关闭按钮
                closeBtn.addEventListener('click', function() {
                    tripList.classList.remove('expanded');
                });
            }
            
            // 更新全屏行程列表内容（由 map.js 调用）
            window.updateFullscreenTripList = function(trips, colorIndices) {
                const content = document.getElementById('fullscreen-trip-list-content');
                if (!content) return;
                
                if (trips.length === 0) {
                    content.innerHTML = '<div style="text-align: center; color: #999; padding: 20px 10px; font-size: 15px;">暂无行程</div>';
                    return;
                }
                
                const colors = ['#667eea', '#764ba2', '#f093fb', '#f5576c', '#4facfe', '#43e97b'];
                
                // 生成行程HTML，为连贯的行程添加颜色边框
                let tripHtml = '';
                let currentGroupColor = null;
                let groupStartIndex = 0;
                
                trips.forEach((trip, index) => {
                    const colorIndex = colorIndices[index];
                    const color = colors[colorIndex];
                    const isLastTrip = index === trips.length - 1;
                    const nextColorIndex = isLastTrip ? null : colorIndices[index + 1];
                    
                    // 检查是否是新分组的开始
                    const isGroupStart = currentGroupColor !== colorIndex;
                    // 检查是否是分组的结束
                    const isGroupEnd = isLastTrip || nextColorIndex !== colorIndex;
                    
                    if (isGroupStart) {
                        // 开始新分组
                        if (currentGroupColor !== null) {
                            tripHtml += '</div>'; // 关闭上一个分组
                        }
                        tripHtml += `<div class="trip-group" style="border-left: 4px solid ${color}; padding-left: 8px;">`;
                        currentGroupColor = colorIndex;
                        groupStartIndex = index;
                    }
                    
                    tripHtml += `
                        <div class="fullscreen-trip-item" 
                            data-date="${trip.date}" 
                            data-origin="${trip.origin}" 
                            data-destination="${trip.destination}"
                            data-index="${index}"
                            onmouseover="hoverTripOnMap('${trip.date}', '${trip.origin}', '${trip.destination}', true, ${index})"
                            onmouseout="hoverTripOnMap('${trip.date}', '${trip.origin}', '${trip.destination}', false, ${index})"
                            onclick="selectTripOnMap('${trip.date}', '${trip.origin}', '${trip.destination}', this, ${index})">
                            <div class="fullscreen-trip-date">${trip.date}</div>
                            <div class="fullscreen-trip-route">
                                ${trip.origin}<span class="fullscreen-trip-arrow">→</span>${trip.destination}
                            </div>
                        </div>`;
                    
                    if (isGroupEnd) {
                        tripHtml += '</div>'; // 关闭当前分组
                        currentGroupColor = null;
                    }
                });
                
                content.innerHTML = tripHtml;
            };
            
            // 鼠标悬停在行程列表项上时，高亮地图上的曲线（状态2）
            window.hoverTripOnMap = function(date, origin, destination, isHover) {
                if (typeof window.hoverPolylineOnMap === 'function') {
                    window.hoverPolylineOnMap(date, origin, destination, isHover);
                }
            };
            
            // 点击选中行程列表项时，高亮并添加流动效果（状态3）
            window.selectTripOnMap = function(date, origin, destination, element, listIndex = null) {
                // 移除其他项的选中状态
                const allItems = document.querySelectorAll('.fullscreen-trip-item');
                allItems.forEach(item => item.classList.remove('selected'));
                
                // 添加当前项的选中状态
                if (element) {
                    element.classList.add('selected');
                }
                
                // 调用地图高亮函数，传递索引参数
                highlightTrip(date, origin, destination, listIndex);
            };
            
            // 强制刷新功能
            function forceRefresh() {
                window.location.reload(true);
            }
        </script>
        
        <!-- 自定义JavaScript - 高德地图版本 -->
        <script src="js/map.js?v=2025012001"></script>
        
        <!-- 用户菜单组件 -->
        <script src="js/user-menu.js"></script>
    </div>
<<<<<<< HEAD

    <!-- 高德地图 API (使用免费版本，无需申请key) -->
    <script src="https://webapi.amap.com/maps?v=2.0&key=YOUR_AMAP_KEY"></script>
    
    <!-- 地图初始化脚本 -->
    <script>
        // 显示地图加载提示
        document.addEventListener('DOMContentLoaded', function() {
            const loadingEl = document.getElementById('map-loading');
            if (loadingEl) {
                loadingEl.style.display = 'block';
                // 地图加载成功后隐藏提示
                setTimeout(() => {
                    loadingEl.style.display = 'none';
                }, 2000);
            }
            
            // 全屏功能初始化
            initFullscreenButton();
        });
        
        // 全屏功能
        function initFullscreenButton() {
            const fullscreenBtn = document.getElementById('fullscreen-btn');
            const mapContainer = document.getElementById('map-container');
            const fullscreenIcon = fullscreenBtn.querySelector('.fullscreen-icon');
            const fullscreenText = fullscreenBtn.querySelector('.fullscreen-text');
            
            let isFullscreen = false;
            
            fullscreenBtn.addEventListener('click', function() {
                if (!isFullscreen) {
                    // 进入全屏
                    mapContainer.classList.add('fullscreen');
                    fullscreenIcon.textContent = '⛶';
                    fullscreenText.textContent = '退出全屏';
                    isFullscreen = true;
                    
                    // 触发地图resize事件以适应新尺寸
                    if (window.map) {
                        setTimeout(() => {
                            map.resize();
                        }, 100);
                    }
                    
                    // 初始化全屏行程列表
                    initFullscreenTripList();
                } else {
                    // 退出全屏
                    mapContainer.classList.remove('fullscreen');
                    fullscreenIcon.textContent = '⛶';
                    fullscreenText.textContent = '全屏';
                    isFullscreen = false;
                    
                    // 关闭行程列表
                    const tripList = document.getElementById('fullscreen-trip-list');
                    if (tripList) {
                        tripList.classList.remove('expanded');
                    }
                    
                    // 触发地图resize事件
                    if (window.map) {
                        setTimeout(() => {
                            map.resize();
                        }, 100);
                    }
                }
            });
            
            // ESC键退出全屏
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && isFullscreen) {
                    fullscreenBtn.click();
                }
            });
        }
        
        // 初始化全屏行程列表
        function initFullscreenTripList() {
            const toggleBtn = document.getElementById('trip-list-toggle-btn');
            const tripList = document.getElementById('fullscreen-trip-list');
            const closeBtn = document.getElementById('fullscreen-trip-list-close');
            
            if (!toggleBtn || !tripList || !closeBtn) return;
            
            // 展开按钮
            toggleBtn.addEventListener('click', function() {
                tripList.classList.add('expanded');
            });
            
            // 关闭按钮
            closeBtn.addEventListener('click', function() {
                tripList.classList.remove('expanded');
            });
        }
        
        // 更新全屏行程列表内容（由 map.js 调用）
        window.updateFullscreenTripList = function(trips, colorIndices) {
            const content = document.getElementById('fullscreen-trip-list-content');
            if (!content) return;
            
            if (trips.length === 0) {
                content.innerHTML = '<div style="text-align: center; color: #999; padding: 20px 10px; font-size: 15px;">暂无行程</div>';
                return;
            }
            
            const colors = ['#667eea', '#764ba2', '#f093fb', '#f5576c', '#4facfe', '#43e97b'];
            
            // 生成行程HTML，为连贯的行程添加颜色边框
            let tripHtml = '';
            let currentGroupColor = null;
            let groupStartIndex = 0;
            
            trips.forEach((trip, index) => {
                const colorIndex = colorIndices[index];
                const color = colors[colorIndex];
                const isLastTrip = index === trips.length - 1;
                const nextColorIndex = isLastTrip ? null : colorIndices[index + 1];
                
                // 检查是否是新分组的开始
                const isGroupStart = currentGroupColor !== colorIndex;
                // 检查是否是分组的结束
                const isGroupEnd = isLastTrip || nextColorIndex !== colorIndex;
                
                if (isGroupStart) {
                    // 开始新分组
                    if (currentGroupColor !== null) {
                        tripHtml += '</div>'; // 关闭上一个分组
                    }
                    tripHtml += `<div class="trip-group" style="border-left: 4px solid ${color}; padding-left: 8px;">`;
                    currentGroupColor = colorIndex;
                    groupStartIndex = index;
                }
                
                tripHtml += `
                    <div class="fullscreen-trip-item" 
                         data-date="${trip.date}" 
                         data-origin="${trip.origin}" 
                         data-destination="${trip.destination}"
                         data-index="${index}"
                         onmouseover="hoverTripOnMap('${trip.date}', '${trip.origin}', '${trip.destination}', true, ${index})"
                         onmouseout="hoverTripOnMap('${trip.date}', '${trip.origin}', '${trip.destination}', false, ${index})"
                         onclick="selectTripOnMap('${trip.date}', '${trip.origin}', '${trip.destination}', this, ${index})">
                        <div class="fullscreen-trip-date">${trip.date}</div>
                        <div class="fullscreen-trip-route">
                            ${trip.origin}<span class="fullscreen-trip-arrow">→</span>${trip.destination}
                        </div>
                    </div>`;
                
                if (isGroupEnd) {
                    tripHtml += '</div>'; // 关闭当前分组
                    currentGroupColor = null;
                }
            });
            
            content.innerHTML = tripHtml;
        };
        
        // 鼠标悬停在行程列表项上时，高亮地图上的曲线（状态2）
        window.hoverTripOnMap = function(date, origin, destination, isHover) {
            if (typeof window.hoverPolylineOnMap === 'function') {
                window.hoverPolylineOnMap(date, origin, destination, isHover);
            }
        };
        
        // 点击选中行程列表项时，高亮并添加流动效果（状态3）
        window.selectTripOnMap = function(date, origin, destination, element, listIndex = null) {
            // 移除其他项的选中状态
            const allItems = document.querySelectorAll('.fullscreen-trip-item');
            allItems.forEach(item => item.classList.remove('selected'));
            
            // 添加当前项的选中状态
            if (element) {
                element.classList.add('selected');
            }
            
            // 调用地图高亮函数，传递索引参数
            highlightTrip(date, origin, destination, listIndex);
        };
        
        // 强制刷新功能
        function forceRefresh() {
            window.location.reload(true);
        }
    </script>
    
    <!-- 自定义JavaScript - 高德地图版本 -->
    <script src="js/map.js?v=2025012001"></script>
    
        </div>

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
    <!-- 用户菜单组件 -->
    <script src="js/user-menu.js"></script>
=======
>>>>>>> 38d2b0755fbbc3d10ba914acf4143cc3cdc98e1e
</body>
</html>
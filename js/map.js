let map;
let markers = [];
let polylines = [];
let sequenceMarkers = []; // 存储序号标记

// 记录相同路线的计数，用于生成不同的弧度
const routeCounters = {};

// 生成平滑贝塞尔曲线路径点
function generateSmoothCurve(startPoint, endPoint, offset) {
    const startLat = startPoint[1];
    const startLng = startPoint[0];
    const endLat = endPoint[1];
    const endLng = endPoint[0];
    
    // 计算中点
    const midLat = (startLat + endLat) / 2;
    const midLng = (startLng + endLng) / 2;
    
    // 计算方向角度
    const angle = Math.atan2(endLat - startLat, endLng - startLng);
    
    // 计算控制点（贝塞尔曲线的顶点）
    const controlLat = midLat + Math.cos(angle + Math.PI/2) * offset;
    const controlLng = midLng + Math.sin(angle + Math.PI/2) * offset;
    
    // 生成平滑曲线的路径点
    const curvePoints = [];
    const numPoints = 50; // 增加点数使曲线更平滑
    
    // 确保起点精确匹配
    curvePoints.push([startLng, startLat]);
    
    // 生成中间点
    for (let i = 1; i < numPoints; i++) {
        const t = i / numPoints;
        // 二次贝塞尔曲线公式
        const lat = (1 - t) * (1 - t) * startLat + 2 * (1 - t) * t * controlLat + t * t * endLat;
        const lng = (1 - t) * (1 - t) * startLng + 2 * (1 - t) * t * controlLng + t * t * endLng;
        
        curvePoints.push([lng, lat]); // 高德地图使用 [经度, 纬度] 格式
    }
    
    // 确保终点精确匹配
    curvePoints.push([endLng, endLat]);
    
    return curvePoints;
}

// 计算两点之间的距离（简化版）
function getDistance(point1, point2) {
    const lat1 = point1[1];
    const lng1 = point1[0];
    const lat2 = point2[1];
    const lng2 = point2[0];
    
    const R = 6371; // 地球半径（公里）
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLng = (lng2 - lng1) * Math.PI / 180;
    const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
              Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
              Math.sin(dLng/2) * Math.sin(dLng/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return R * c;
}

// 初始化地图
function initMap() {
    try {
        console.log('开始初始化高德地图...');
        
        // 创建高德地图实例
        map = new AMap.Map('map', {
            zoom: 7,
            center: [118.0, 39.5], // 京津冀地区中心 [经度, 纬度]
            viewMode: '2D',
            mapStyle: 'amap://styles/normal', // 标准样式
            features: ['bg', 'road', 'building', 'point'], // 显示背景、道路、建筑、POI点
            showIndoorMap: false,
            showLabel: true, // 显示地图文字标记
            zooms: [3, 18], // 地图缩放范围
            labelzIndex: 130 // 标注层级
        });
        
        // 添加地图控件（高德地图 2.0 版本）
        AMap.plugin(['AMap.Scale', 'AMap.ToolBar'], function() {
            // 添加比例尺
            map.addControl(new AMap.Scale());
            // 添加工具条
            map.addControl(new AMap.ToolBar());
        });
        
        console.log('高德地图初始化成功');
        
    } catch (error) {
        console.error('地图初始化失败:', error);
        document.getElementById('map').innerHTML = `
            <div style="
                display: flex; 
                align-items: center; 
                justify-content: center; 
                height: 500px; 
                background: #f0f0f0; 
                border: 2px dashed #ccc;
                margin: 10px;
                border-radius: 5px;
                color: #666;
                text-align: center;
                padding: 20px;
            ">
                <div>
                    <h3>地图加载失败</h3>
                    <p>请尝试以下解决方案：</p>
                    <ul style="text-align: left; display: inline-block;">
                        <li>检查网络连接</li>
                        <li>刷新页面重试</li>
                        <li>联系管理员检查API配置</li>
                    </ul>
                </div>
            </div>
        `;
    }
}

// 清除地图上的所有标记和线条
function clearMap() {
    if (!map) return;
    
    // 清除流动动画
    if (selectedPolylineInterval) {
        clearInterval(selectedPolylineInterval);
        selectedPolylineInterval = null;
    }
    
    // 清除流动标记点
    flowingMarkers.forEach(marker => map.remove(marker));
    flowingMarkers = [];
    
    // 重置选中状态
    selectedPolyline = null;
    
    // 清除所有标记
    markers.forEach(marker => {
        map.remove(marker);
    });
    markers = [];
    
    // 清除序号标记
    sequenceMarkers.forEach(marker => {
        map.remove(marker);
    });
    sequenceMarkers = [];
    
    // 清除所有折线
    polylines.forEach(polyline => {
        map.remove(polyline);
    });
    polylines = [];
    
    // 重置路线计数器
    for (let key in routeCounters) {
        routeCounters[key] = 0;
    }
}

// 添加行程到地图
function addTripToMap(trip, cities, isStartPoint = false, isEndPoint = false, sequenceNumber = null, colorIndex = 0) {
    const originCity = cities[trip.origin];
    const destCity = cities[trip.destination];
    
    if (!originCity || !destCity) {
        console.warn('城市信息未找到:', trip);
        return;
    }
    
    // 创建路线键（区分方向，往返路线分开处理）
    const forwardRouteKey = trip.origin + '-' + trip.destination;
    const backwardRouteKey = trip.destination + '-' + trip.origin;
    
    // 获取或初始化路线计数器
    if (!routeCounters[forwardRouteKey]) routeCounters[forwardRouteKey] = 0;
    if (!routeCounters[backwardRouteKey]) routeCounters[backwardRouteKey] = 0;
    
    // 计算路线索引（用于计算弧线偏移，不再用于颜色）
    const forwardIndex = routeCounters[forwardRouteKey];
    const backwardIndex = routeCounters[backwardRouteKey];
    const routeIndex = forwardIndex;
    
    // 增加当前方向的计数器
    routeCounters[forwardRouteKey]++;
    
    // 起点和终点坐标 [经度, 纬度]
    const originPos = [originCity.longitude, originCity.latitude];
    const destPos = [destCity.longitude, destCity.latitude];
    
    // 创建起点标记（根据是否为起始点使用不同样式）
    let originIcon, originTitle, originInfoContent;
    
    if (isStartPoint) {
        // 起始点：较大的绿色星形标记
        // 使用 UTF-8 编码的 data URI，避免 btoa 的 Latin1 限制
        const svgString = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
            <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" 
                  fill="#4CAF50" stroke="white" stroke-width="1.5"/>
        </svg>`;
        const svgBlob = new Blob([svgString], {type: 'image/svg+xml;charset=utf-8'});
        const svgUrl = URL.createObjectURL(svgBlob);
        
        originIcon = new AMap.Icon({
            size: new AMap.Size(24, 24),
            image: svgUrl,
            imageSize: new AMap.Size(24, 24)
        });
        originTitle = `起始点 - ${trip.origin}`;
        originInfoContent = `<div style="padding: 8px;"><strong>🏁 起始点</strong><br>${trip.origin}<br>${trip.date}</div>`;
    } else {
        // 普通起点：小的绿色圆点
        const svgString = `<svg xmlns="http://www.w3.org/2000/svg" width="8" height="8">
            <circle cx="4" cy="4" r="3" fill="#4CAF50" stroke="white" stroke-width="1.5"/>
        </svg>`;
        const svgBlob = new Blob([svgString], {type: 'image/svg+xml;charset=utf-8'});
        const svgUrl = URL.createObjectURL(svgBlob);
        
        originIcon = new AMap.Icon({
            size: new AMap.Size(8, 8),
            image: svgUrl,
            imageSize: new AMap.Size(8, 8)
        });
        originTitle = trip.origin;
        originInfoContent = `<div style="padding: 8px;"><strong>起点</strong><br>${trip.origin}<br>${trip.date}</div>`;
    }
    
    const originMarkerConfig = {
        position: originPos,
        icon: originIcon,
        offset: isStartPoint ? new AMap.Pixel(-12, -12) : new AMap.Pixel(-4, -4),
        title: originTitle,
        zIndex: isStartPoint ? 200 : 100
    };
    
    const originMarker = new AMap.Marker(originMarkerConfig);
    
    // 起点鼠标悬停显示信息
    originMarker.on('mouseover', function() {
        const infoWindow = new AMap.InfoWindow({
            content: originInfoContent,
            offset: new AMap.Pixel(0, isStartPoint ? -20 : -10)
        });
        infoWindow.open(map, originMarker.getPosition());
        originMarker._infoWindow = infoWindow;
    });
    
    originMarker.on('mouseout', function() {
        if (originMarker._infoWindow) {
            originMarker._infoWindow.close();
        }
    });
    
    // 创建终点标记（根据是否为结束点使用不同样式）
    let destIcon, destTitle, destInfoContent;
    
    if (isEndPoint) {
        // 结束点：较大的红色星形标记
        const svgString = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24">
            <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" 
                  fill="#f44336" stroke="white" stroke-width="1.5"/>
        </svg>`;
        const svgBlob = new Blob([svgString], {type: 'image/svg+xml;charset=utf-8'});
        const svgUrl = URL.createObjectURL(svgBlob);
        
        destIcon = new AMap.Icon({
            size: new AMap.Size(24, 24),
            image: svgUrl,
            imageSize: new AMap.Size(24, 24)
        });
        destTitle = `结束点 - ${trip.destination}`;
        destInfoContent = `<div style="padding: 8px;"><strong>🏁 结束点</strong><br>${trip.destination}<br>${trip.date}</div>`;
    } else {
        // 普通终点：小的红色圆点
        const svgString = `<svg xmlns="http://www.w3.org/2000/svg" width="8" height="8">
            <circle cx="4" cy="4" r="3" fill="#f44336" stroke="white" stroke-width="1.5"/>
        </svg>`;
        const svgBlob = new Blob([svgString], {type: 'image/svg+xml;charset=utf-8'});
        const svgUrl = URL.createObjectURL(svgBlob);
        
        destIcon = new AMap.Icon({
            size: new AMap.Size(8, 8),
            image: svgUrl,
            imageSize: new AMap.Size(8, 8)
        });
        destTitle = trip.destination;
        destInfoContent = `<div style="padding: 8px;"><strong>终点</strong><br>${trip.destination}<br>${trip.date}</div>`;
    }
    
    const destMarkerConfig = {
        position: destPos,
        icon: destIcon,
        offset: isEndPoint ? new AMap.Pixel(-12, -12) : new AMap.Pixel(-4, -4),
        title: destTitle,
        zIndex: isEndPoint ? 200 : 100
    };
    
    const destMarker = new AMap.Marker(destMarkerConfig);
    
    // 终点鼠标悬停显示信息
    destMarker.on('mouseover', function() {
        const infoWindow = new AMap.InfoWindow({
            content: destInfoContent,
            offset: new AMap.Pixel(0, isEndPoint ? -20 : -10)
        });
        infoWindow.open(map, destMarker.getPosition());
        destMarker._infoWindow = infoWindow;
    });
    
    destMarker.on('mouseout', function() {
        if (destMarker._infoWindow) {
            destMarker._infoWindow.close();
        }
    });
    
    // 添加标记到地图
    map.add([originMarker, destMarker]);
    markers.push(originMarker, destMarker);
    
    // 计算弧线路径
    const distance = getDistance(originPos, destPos);
    const maxOffset = Math.min(distance * 0.001, 0.5);
    
    // 计算偏移
    let offsetDirection = 1;
    let offsetMultiplier = 1;
    
    if (backwardIndex > 0 || forwardIndex > 0) {
        const isForwardRoute = forwardIndex <= backwardIndex;
        
        if (isForwardRoute) {
            offsetDirection = 1;
            offsetMultiplier = 1 + forwardIndex * 0.3;
        } else {
            offsetDirection = -1;
            offsetMultiplier = 1 + backwardIndex * 0.3;
        }
        
        console.log(`路线分列: ${trip.origin}->${trip.destination}, 正向:${forwardIndex}, 反向:${backwardIndex}, 方向:${offsetDirection > 0 ? '右' : '左'}, 倍数:${offsetMultiplier}`);
    }
    
    const offset = maxOffset * offsetMultiplier * offsetDirection;
    
    // 生成平滑曲线路径
    const curvePath = generateSmoothCurve(originPos, destPos, offset);
    
    // 颜色列表（使用传入的 colorIndex 而不是 routeIndex）
    const colors = ['#667eea', '#764ba2', '#f093fb', '#f5576c', '#4facfe', '#43e97b'];
    
    // 创建曲线（默认实线）
    const polyline = new AMap.Polyline({
        path: curvePath,
        strokeColor: colors[colorIndex],
        strokeWeight: 3,
        strokeOpacity: 0.8,
        strokeStyle: 'solid', // 默认实线
        isOutline: false,
        showDir: true,
        extData: { // 存储行程信息
            origin: trip.origin,
            destination: trip.destination,
            date: trip.date,
            routeIndex: routeIndex + 1,
            colorIndex: colorIndex
        }
    });
    
    // 创建路线信息提示框
    const polylineInfoWindow = new AMap.InfoWindow({
        content: `
            <div style="padding: 8px 12px; font-family: Arial, sans-serif;">
                <div style="font-size: 14px; font-weight: bold; color: #333; margin-bottom: 6px;">
                    📍 ${trip.origin} → ${trip.destination}
                </div>
                <div style="font-size: 12px; color: #666; margin-bottom: 4px;">
                    📅 日期: ${trip.date}
                </div>
                <div style="font-size: 11px; color: #999;">
                    第 ${routeIndex + 1} 次行程
                </div>
            </div>
        `,
        offset: new AMap.Pixel(0, -10)
    });
    
    // 鼠标移入效果（状态2）：加粗并显示信息
    polyline.on('mouseover', function(e) {
        // 如果不是当前选中的曲线，才应用悬停效果
        if (selectedPolyline !== polyline) {
            polyline.setOptions({
                strokeWeight: 5,
                strokeOpacity: 1,
                zIndex: 100
            });
        }
        // 显示信息窗体
        polylineInfoWindow.open(map, e.lnglat);
    });
    
    // 鼠标移出效果：恢复默认（状态1）
    polyline.on('mouseout', function() {
        // 如果不是当前选中的曲线，恢复默认样式
        if (selectedPolyline !== polyline) {
            polyline.setOptions({
                strokeWeight: 3,
                strokeOpacity: 0.8,
                zIndex: 10
            });
        }
        // 关闭信息窗体
        polylineInfoWindow.close();
    });
    
    // 点击事件（状态3）：高亮显示并添加流动动画
    polyline.on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // 调用高亮函数（会应用状态3的效果）
        highlightTrip(trip.date, trip.origin, trip.destination);
        
        // 点击时信息窗体保持打开
        polylineInfoWindow.open(map, e.lnglat);
    });
    
    // 添加折线到地图
    map.add(polyline);
    polylines.push(polyline);
    
    // 如果有序号，在路线中点添加序号标记
    if (sequenceNumber !== null) {
        const colors = ['#667eea', '#764ba2', '#f093fb', '#f5576c', '#4facfe', '#43e97b'];
        const midIndex = Math.floor(curvePath.length / 2);
        const midPoint = curvePath[midIndex];
        
        const sequenceMarker = new AMap.Marker({
            position: midPoint,
            content: `<div style="
                background: white;
                border: 2px solid ${colors[colorIndex]};
                color: ${colors[colorIndex]};
                font-weight: bold;
                font-size: 12px;
                padding: 2px 6px;
                border-radius: 50%;
                min-width: 24px;
                height: 24px;
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 2px 4px rgba(0,0,0,0.3);
            ">${sequenceNumber}</div>`,
            offset: new AMap.Pixel(-12, -12),
            zIndex: 150
        });
        
        map.add(sequenceMarker);
        sequenceMarkers.push(sequenceMarker);
        
        // 默认隐藏序号标记
        sequenceMarker.hide();
    }
}

// 在地图上显示所有行程
function displayTripsOnMap(trips, cities) {
    clearMap();
    
    if (trips.length === 0) {
        return;
    }
    
    // 按日期和原始顺序排序行程
    const sortedTrips = [...trips].sort((a, b) => {
        const dateCompare = a.date.localeCompare(b.date);
        if (dateCompare !== 0) return dateCompare;
        // 同一天保持原始顺序（假设 trips 数组已按数据库顺序）
        return trips.indexOf(a) - trips.indexOf(b);
    });
    
    // 找出最早的行程（同一天的第一条记录）
    let earliestTrip = null;
    let earliestDate = null;
    
    for (let trip of sortedTrips) {
        if (!earliestDate || trip.date < earliestDate) {
            earliestDate = trip.date;
            earliestTrip = trip;
        } else if (trip.date === earliestDate) {
            // 同一天，保持第一条记录
            continue;
        } else {
            // 已经找到最早的日期，跳出
            break;
        }
    }
    
    // 找出最晚的行程（同一天的最后一条记录）
    let latestTrip = null;
    let latestDate = null;
    
    for (let i = sortedTrips.length - 1; i >= 0; i--) {
        const trip = sortedTrips[i];
        if (!latestDate || trip.date > latestDate) {
            latestDate = trip.date;
            latestTrip = trip;
        } else if (trip.date === latestDate) {
            // 同一天，继续向前找第一条（因为是倒序遍历，会找到该天的第一条）
            latestTrip = trip;
        } else {
            // 已经找到最晚的日期，跳出
            break;
        }
    }
    
    console.log('起始点行程:', earliestTrip);
    console.log('结束点行程:', latestTrip);
    
    // 分析行程连贯性并分配颜色
    const colors = ['#667eea', '#764ba2', '#f093fb', '#f5576c', '#4facfe', '#43e97b'];
    const tripColorMap = new Map(); // 存储每个行程的颜色索引
    let currentColorIndex = 0;
    let lastDestination = null;
    
    sortedTrips.forEach((trip, index) => {
        // 如果是第一个行程，或者当前行程的起点与上一行程的终点不同，则使用新颜色
        if (index === 0 || trip.origin !== lastDestination) {
            // 开启新的颜色段
            currentColorIndex = (currentColorIndex + (index === 0 ? 0 : 1)) % colors.length;
        }
        
        tripColorMap.set(index, currentColorIndex);
        lastDestination = trip.destination;
        
        console.log(`行程${index + 1}: ${trip.origin}->${trip.destination}, 颜色索引: ${currentColorIndex}, 连贯: ${index === 0 ? '起始' : (trip.origin === sortedTrips[index - 1]?.destination ? '是' : '否')}`);
    });
    
    // 添加所有行程（使用排序后的顺序）
    sortedTrips.forEach((trip, index) => {
        const originCity = cities[trip.origin];
        const destCity = cities[trip.destination];
        
        if (originCity && destCity) {
            const isStartPoint = earliestTrip && 
                                 trip.date === earliestTrip.date && 
                                 trip.origin === earliestTrip.origin &&
                                 trip.destination === earliestTrip.destination;
            
            const isEndPoint = latestTrip && 
                              trip.date === latestTrip.date && 
                              trip.origin === latestTrip.origin &&
                              trip.destination === latestTrip.destination;
            
            // 获取该行程的颜色索引
            const colorIndex = tripColorMap.get(index);
            
            // 传递序号（从1开始）和颜色索引
            addTripToMap(trip, cities, isStartPoint, isEndPoint, index + 1, colorIndex);
        }
    });
    
    // 自动调整地图视图以显示所有标记
    if (markers.length > 0) {
        map.setFitView(markers, true, [50, 50, 50, 50]);
    }
}

// 切换序号显示
function toggleSequenceDisplay(show) {
    sequenceMarkers.forEach(marker => {
        if (show) {
            marker.show();
        } else {
            marker.hide();
        }
    });
}

// 渲染行程列表
function renderTripList(trips) {
    const tripListElement = document.getElementById('trip-list');
    
    if (trips.length === 0) {
        tripListElement.innerHTML = '<div class="no-results">没有找到符合条件的行程</div>';
        // 更新全屏行程列表
        if (typeof window.updateFullscreenTripList === 'function') {
            window.updateFullscreenTripList([], []);
        }
        return;
    }
    
    // 按日期和原始顺序排序行程（与地图显示逻辑一致）
    const sortedTrips = [...trips].sort((a, b) => {
        const dateCompare = a.date.localeCompare(b.date);
        if (dateCompare !== 0) return dateCompare;
        return trips.indexOf(a) - trips.indexOf(b);
    });
    
    // 分析行程连贯性并分配颜色（与地图逻辑一致）
    const colors = ['#667eea', '#764ba2', '#f093fb', '#f5576c', '#4facfe', '#43e97b'];
    const tripColorIndices = [];
    let currentColorIndex = 0;
    let lastDestination = null;
    
    sortedTrips.forEach((trip, index) => {
        if (index === 0 || trip.origin !== lastDestination) {
            currentColorIndex = (currentColorIndex + (index === 0 ? 0 : 1)) % colors.length;
        }
        tripColorIndices.push(currentColorIndex);
        lastDestination = trip.destination;
    });
    
    const tripHtml = sortedTrips.map((trip) => `
        <div class="trip-item" 
             data-date="${trip.date}" 
             data-origin="${trip.origin}" 
             data-destination="${trip.destination}"
             onmouseover="hoverTripOnMap('${trip.date}', '${trip.origin}', '${trip.destination}', true)"
             onmouseout="hoverTripOnMap('${trip.date}', '${trip.origin}', '${trip.destination}', false)"
             onclick="selectMainTripItem('${trip.date}', '${trip.origin}', '${trip.destination}', this)">
            <div class="trip-date">${trip.date}</div>
            <div class="trip-route">
                ${trip.origin}
                <span class="arrow">→</span>
                ${trip.destination}
            </div>
        </div>
    `).join('');
    
    tripListElement.innerHTML = tripHtml;
    
    // 更新全屏行程列表，传递颜色索引
    if (typeof window.updateFullscreenTripList === 'function') {
        window.updateFullscreenTripList(sortedTrips, tripColorIndices);
    }
}

// 主页面行程列表项选中处理
window.selectMainTripItem = function(date, origin, destination, element) {
    // 移除其他项的选中状态
    const allItems = document.querySelectorAll('.trip-item');
    allItems.forEach(item => item.classList.remove('selected'));
    
    // 添加当前项的选中状态
    if (element) {
        element.classList.add('selected');
    }
    
    // 调用地图高亮函数
    highlightTrip(date, origin, destination);
};

// 主页面行程列表项悬停处理（使用相同的函数）
window.hoverTripOnMap = function(date, origin, destination, isHover) {
    if (typeof window.hoverPolylineOnMap === 'function') {
        window.hoverPolylineOnMap(date, origin, destination, isHover);
    }
};

// 记录当前选中的曲线
let selectedPolyline = null;
let selectedPolylineInterval = null;
let flowingMarkers = []; // 流动动画的标记点

// 行程列表悬停时高亮对应曲线（状态2）
window.hoverPolylineOnMap = function(date, origin, destination, isHover) {
    // 查找对应的曲线
    const targetPolyline = polylines.find(polyline => {
        const extData = polyline.getExtData();
        return extData && extData.date === date && 
               extData.origin === origin && 
               extData.destination === destination;
    });
    
    if (!targetPolyline) {
        return;
    }
    
    // 如果是选中状态的曲线，不应用悬停效果
    if (selectedPolyline === targetPolyline) {
        return;
    }
    
    if (isHover) {
        // 应用悬停效果
        targetPolyline.setOptions({
            strokeWeight: 5,
            strokeOpacity: 1,
            zIndex: 100
        });
    } else {
        // 恢复默认样式
        targetPolyline.setOptions({
            strokeWeight: 3,
            strokeOpacity: 0.8,
            zIndex: 10
        });
    }
};

// 高亮显示特定行程（点击选中 - 状态3）
function highlightTrip(date, origin, destination) {
    // 查找对应的曲线
    const targetPolyline = polylines.find(polyline => {
        const extData = polyline.getExtData();
        return extData && extData.date === date && 
               extData.origin === origin && 
               extData.destination === destination;
    });
    
    if (!targetPolyline) {
        console.warn('未找到对应的曲线:', date, origin, destination);
        return;
    }
    
    // 清除之前选中的曲线状态
    if (selectedPolyline && selectedPolyline !== targetPolyline) {
        const prevExtData = selectedPolyline.getExtData();
        const colors = ['#667eea', '#764ba2', '#f093fb', '#f5576c', '#4facfe', '#43e97b'];
        selectedPolyline.setOptions({
            strokeColor: colors[prevExtData.colorIndex],
            strokeWeight: 3,
            strokeOpacity: 0.8,
            zIndex: 10,
            strokeStyle: 'solid'
        });
        // 清除流动动画
        if (selectedPolylineInterval) {
            clearInterval(selectedPolylineInterval);
            selectedPolylineInterval = null;
        }
        // 清除流动标记点
        flowingMarkers.forEach(marker => map.remove(marker));
        flowingMarkers = [];
    }
    
    // 设置新选中的曲线
    selectedPolyline = targetPolyline;
    const extData = targetPolyline.getExtData();
    const colors = ['#667eea', '#764ba2', '#f093fb', '#f5576c', '#4facfe', '#43e97b'];
    const baseColor = colors[extData.colorIndex];
    
    // 加粗曲线
    targetPolyline.setOptions({
        strokeWeight: 6,
        strokeOpacity: 1,
        zIndex: 200,
        strokeStyle: 'solid'
    });
    
    // 创建流动动画效果 - 在路径上移动的小圆点
    const path = targetPolyline.getPath();
    if (path && path.length > 0) {
        const numDots = 5; // 5个流动的点
        const dotMarkers = [];
        
        // 创建流动点标记
        for (let i = 0; i < numDots; i++) {
            const dotMarker = new AMap.Marker({
                position: path[0],
                content: `<div style="
                    width: 8px;
                    height: 8px;
                    background: white;
                    border: 2px solid ${baseColor};
                    border-radius: 50%;
                    box-shadow: 0 0 4px ${baseColor};
                "></div>`,
                offset: new AMap.Pixel(-4, -4),
                zIndex: 250
            });
            
            map.add(dotMarker);
            dotMarkers.push(dotMarker);
            flowingMarkers.push(dotMarker);
        }
        
        // 动画逻辑：沿路径移动点
        let animationIndex = 0;
        
        selectedPolylineInterval = setInterval(() => {
            animationIndex = (animationIndex + 1) % path.length;
            
            // 更新每个点的位置（错开间隔）
            dotMarkers.forEach((marker, i) => {
                const offset = Math.floor((path.length / numDots) * i);
                const currentIndex = (animationIndex + offset) % path.length;
                marker.setPosition(path[currentIndex]);
            });
        }, 60); // 每60ms更新一次位置，速度减半
    }
}

// 显示行程信息
function highlightTripInfo(tripInfo) {
    const infoDiv = document.createElement('div');
    infoDiv.style.cssText = `
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: rgba(0,0,0,0.8);
        color: white;
        padding: 20px;
        border-radius: 10px;
        font-size: 16px;
        z-index: 1000;
        animation: fadeIn 0.3s ease;
        max-width: 400px;
        text-align: center;
    `;
    infoDiv.innerHTML = `
        <strong>路线信息</strong><br><br>
        ${tripInfo}<br><br>
        <small style="opacity: 0.8;">点击任意位置关闭</small>
    `;
    
    document.body.appendChild(infoDiv);
    
    // 添加淡入动画
    const style = document.createElement('style');
    style.textContent = `
        @keyframes fadeIn {
            from { opacity: 0; transform: translate(-50%, -50%) scale(0.8); }
            to { opacity: 1; transform: translate(-50%, -50%) scale(1); }
        }
    `;
    document.head.appendChild(style);
    
    // 点击关闭
    setTimeout(() => {
        document.addEventListener('click', function closeInfo(e) {
            if (!infoDiv.contains(e.target)) {
                if (infoDiv.parentNode) {
                    infoDiv.parentNode.removeChild(infoDiv);
                }
                if (style.parentNode) {
                    style.parentNode.removeChild(style);
                }
                document.removeEventListener('click', closeInfo);
            }
        });
    }, 100);
}

// 更新统计信息
function updateStats(trips) {
    const totalTripsElement = document.getElementById('total-trips');
    const uniqueCitiesElement = document.getElementById('unique-cities');
    
    if (totalTripsElement) {
        totalTripsElement.textContent = trips.length;
    }
    
    if (uniqueCitiesElement) {
        const uniqueCities = new Set();
        trips.forEach(trip => {
            uniqueCities.add(trip.origin);
            uniqueCities.add(trip.destination);
        });
        uniqueCitiesElement.textContent = uniqueCities.size;
    }
}

// 加载行程数据
function loadTrips(startDate = '', endDate = '') {
    const params = new URLSearchParams({
        start_date: startDate,
        end_date: endDate
    });
    
    fetch(`api/trips.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                showError(data.error);
                return;
            }
            
            displayTripsOnMap(data.trips, data.cities);
            renderTripList(data.trips);
            updateStats(data.trips);
        })
        .catch(error => {
            console.error('加载行程数据失败:', error);
            showError('加载行程数据失败，请稍后重试');
        });
}

// 显示错误信息
function showError(message) {
    const errorElement = document.createElement('div');
    errorElement.className = 'error';
    errorElement.style.cssText = `
        background: rgba(220, 53, 69, 0.1);
        border: 1px solid rgba(220, 53, 69, 0.3);
        color: #721c24;
        padding: 1rem;
        margin: 1rem 0;
        border-radius: 8px;
    `;
    errorElement.textContent = message;
    
    const container = document.querySelector('.page-content');
    if (container) {
        container.insertBefore(errorElement, container.firstChild);
        
        // 3秒后自动移除错误信息
        setTimeout(() => {
            if (errorElement.parentNode) {
                errorElement.parentNode.removeChild(errorElement);
            }
        }, 3000);
    }
}

// 页面加载完成后初始化
document.addEventListener('DOMContentLoaded', function() {
    // 等待高德地图API加载完成
    if (typeof AMap !== 'undefined') {
        console.log('高德地图API已加载');
        initMap();
        
        // 等待地图初始化完成后加载数据
        setTimeout(function() {
            console.log('加载行程数据...');
            loadTrips();
        }, 500);
    } else {
        console.error('高德地图API未加载，请检查网络连接或API配置');
        showError('高德地图API加载失败，请刷新页面重试');
    }
    
    // 绑定筛选表单提交事件
    const filterForm = document.getElementById('filter-form');
    if (filterForm) {
        filterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const startDate = document.getElementById('start-date').value;
            const endDate = document.getElementById('end-date').value;
            
            loadTrips(startDate, endDate);
        });
    }
    
    // 绑定序号显示开关
    const sequenceToggle = document.getElementById('show-sequence-toggle');
    if (sequenceToggle) {
        sequenceToggle.addEventListener('change', function(e) {
            toggleSequenceDisplay(e.target.checked);
        });
    }
});

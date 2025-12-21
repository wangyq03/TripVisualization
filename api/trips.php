<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// 设置时区
date_default_timezone_set('Asia/Shanghai');

/**
 * WGS84坐标转GCJ02坐标（火星坐标）
 * 高德地图使用GCJ02坐标系
 */
function wgs84ToGcj02($wgsLat, $wgsLng) {
    // 常量定义
    $PI = 3.1415926535897932384626;
    $a = 6378245.0; // 长半轴
    $ee = 0.00669342162296594323; // 偏心率平方
    
    // 判断是否在中国境外
    if (outOfChina($wgsLat, $wgsLng)) {
        return ['lat' => $wgsLat, 'lng' => $wgsLng];
    }
    
    $dLat = transformLat($wgsLng - 105.0, $wgsLat - 35.0);
    $dLng = transformLng($wgsLng - 105.0, $wgsLat - 35.0);
    $radLat = $wgsLat / 180.0 * $PI;
    $magic = sin($radLat);
    $magic = 1 - $ee * $magic * $magic;
    $sqrtMagic = sqrt($magic);
    $dLat = ($dLat * 180.0) / (($a * (1 - $ee)) / ($magic * $sqrtMagic) * $PI);
    $dLng = ($dLng * 180.0) / ($a / $sqrtMagic * cos($radLat) * $PI);
    $mgLat = $wgsLat + $dLat;
    $mgLng = $wgsLng + $dLng;
    
    return ['lat' => $mgLat, 'lng' => $mgLng];
}

function transformLat($lng, $lat) {
    $PI = 3.1415926535897932384626;
    $ret = -100.0 + 2.0 * $lng + 3.0 * $lat + 0.2 * $lat * $lat + 
           0.1 * $lng * $lat + 0.2 * sqrt(abs($lng));
    $ret += (20.0 * sin(6.0 * $lng * $PI) + 20.0 * sin(2.0 * $lng * $PI)) * 2.0 / 3.0;
    $ret += (20.0 * sin($lat * $PI) + 40.0 * sin($lat / 3.0 * $PI)) * 2.0 / 3.0;
    $ret += (160.0 * sin($lat / 12.0 * $PI) + 320 * sin($lat * $PI / 30.0)) * 2.0 / 3.0;
    return $ret;
}

function transformLng($lng, $lat) {
    $PI = 3.1415926535897932384626;
    $ret = 300.0 + $lng + 2.0 * $lat + 0.1 * $lng * $lng + 
           0.1 * $lng * $lat + 0.1 * sqrt(abs($lng));
    $ret += (20.0 * sin(6.0 * $lng * $PI) + 20.0 * sin(2.0 * $lng * $PI)) * 2.0 / 3.0;
    $ret += (20.0 * sin($lng * $PI) + 40.0 * sin($lng / 3.0 * $PI)) * 2.0 / 3.0;
    $ret += (150.0 * sin($lng / 12.0 * $PI) + 300.0 * sin($lng / 30.0 * $PI)) * 2.0 / 3.0;
    return $ret;
}

function outOfChina($lat, $lng) {
    return ($lng < 72.004 || $lng > 137.8347) || 
           (($lat < 0.8293 || $lat > 55.8271));
}


// 保存行程数据（全量替换）
function saveTrips($trips) {
    $tripsFile = __DIR__ . '/../data/trips.csv';
    
    // 创建备份
    if (file_exists($tripsFile)) {
        $backupFile = __DIR__ . '/../data/trips_backup_' . date('Y-m-d_H-i-s') . '.csv';
        copy($tripsFile, $backupFile);
    }
    
    // 写入新数据
    $content = '';
    $content .= "date,origin,destination\n"; // CSV头部
    
    foreach ($trips as $trip) {
        // 标准化日期格式为 YYYY-MM-DD
        $dateParts = preg_split('/[-\/]/', $trip['date']);
        if (count($dateParts) === 3) {
            $standardDate = sprintf('%04d-%02d-%02d', 
                intval($dateParts[0]), 
                intval($dateParts[1]), 
                intval($dateParts[2])
            );
        } else {
            $standardDate = $trip['date']; // 如果格式不对，保持原样
        }
        
        $content .= "{$standardDate},{$trip['origin']},{$trip['destination']}\n";
    }
    
    return file_put_contents($tripsFile, $content) !== false;
}

// 处理POST请求
function handlePostRequest() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['action'])) {
        echo json_encode(['success' => false, 'error' => '无效的请求参数']);
        exit;
    }
    
    switch ($input['action']) {
        case 'upload':
            if (!isset($input['trips']) || !is_array($input['trips'])) {
                echo json_encode(['success' => false, 'error' => '行程数据格式错误']);
                exit;
            }
            
            // 验证每个行程
            $validTrips = [];
            foreach ($input['trips'] as $index => $trip) {
                if (!isset($trip['origin']) || !isset($trip['destination']) || !isset($trip['date'])) {
                    echo json_encode(['success' => false, 'error' => '行程数据不完整']);
                    exit;
                }
                
                // 验证日期格式（支持 YYYY-MM-DD、YYYY/MM/DD、YYYY-M-D、YYYY/M/D 格式）
                if (!preg_match('/^\d{4}[-\/]\d{1,2}[-\/]\d{1,2}$/', $trip['date'])) {
                    echo json_encode(['success' => false, 'error' => '日期格式错误：' . $trip['date'] . ' 请使用 YYYY-MM-DD 或 YYYY/MM/DD 格式']);
                    exit;
                }
                
                // 验证城市是否存在
                $cities = loadCities();
                if (!isset($cities[$trip['origin']]) || !isset($cities[$trip['destination']])) {
                    echo json_encode(['success' => false, 'error' => '城市不存在：' . $trip['origin'] . ' 或 ' . $trip['destination']]);
                    exit;
                }
                
                $validTrips[] = $trip;
            }
            
            // 保存数据
            if (saveTrips($validTrips)) {
                echo json_encode([
                    'success' => true,
                    'message' => '行程数据上传成功',
                    'count' => count($validTrips)
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => '保存数据失败']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => '未知操作']);
            break;
    }
}

// 检查是否是POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handlePostRequest();
    exit;
}

// 读取城市数据
function loadCities() {
    $citiesFile = __DIR__ . '/../data/cities.json';
    if (!file_exists($citiesFile)) {
        return [];
    }
    
    $jsonContent = file_get_contents($citiesFile);
    $cities = json_decode($jsonContent, true);
    
    return $cities ?: [];
}

// 读取行程数据
function loadTrips($startDate = '', $endDate = '') {
    $tripsFile = __DIR__ . '/../data/trips.csv';
    if (!file_exists($tripsFile)) {
        return [];
    }
    
    $trips = [];
    $lines = file($tripsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        $parts = str_getcsv($line);
        if (count($parts) >= 3) {
            $tripDate = trim($parts[0]);
            $origin = trim($parts[1]);
            $destination = trim($parts[2]);
            
            // 验证日期格式（支持 YYYY-MM-DD、YYYY/MM/DD、YYYY-M-D、YYYY/M/D 格式）
            if (!preg_match('/^\d{4}[-\/]\d{1,2}[-\/]\d{1,2}$/', $tripDate)) {
                continue;
            }
            
            // 应用日期筛选
            if (!empty($startDate) && $tripDate < $startDate) {
                continue;
            }
            
            if (!empty($endDate) && $tripDate > $endDate) {
                continue;
            }
            
            $trips[] = [
                'date' => $tripDate,
                'origin' => $origin,
                'destination' => $destination,
                'timestamp' => strtotime($tripDate)
            ];
        }
    }
    
    // 按日期排序
    usort($trips, function($a, $b) {
        return $a['timestamp'] - $b['timestamp'];
    });
    
    return $trips;
}

// 验证日期格式
function validateDate($date) {
    if (empty($date)) {
        return true;
    }
    
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

// 处理GET请求
if (isset($_GET['action']) && $_GET['action'] === 'current') {
    // 返回当前所有行程数据
    try {
        $cities = loadCities();
        $trips = loadTrips('', ''); // 不筛选日期
        
        // 转换城市坐标为GCJ02（高德坐标系）
        foreach ($cities as $cityName => $cityData) {
            $converted = wgs84ToGcj02($cityData['latitude'], $cityData['longitude']);
            $cities[$cityName]['latitude'] = $converted['lat'];
            $cities[$cityName]['longitude'] = $converted['lng'];
        }
        
        echo json_encode([
            'success' => true,
            'trips' => $trips,
            'cities' => $cities,
            'total' => count($trips),
            'coordinateSystem' => 'GCJ02'
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => '获取数据失败：' . $e->getMessage()
        ]);
    }
    exit;
}

// 获取请求参数
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

// 验证参数
if (!validateDate($startDate) || !validateDate($endDate)) {
    echo json_encode([
        'error' => '日期格式无效，请使用 YYYY-MM-DD 格式'
    ]);
    exit;
}

// 如果提供了开始日期但结束日期为空，设置结束日期为当前日期
if (!empty($startDate) && empty($endDate)) {
    $endDate = date('Y-m-d');
}

// 验证日期范围
if (!empty($startDate) && !empty($endDate) && $startDate > $endDate) {
    echo json_encode([
        'error' => '开始日期不能晚于结束日期'
    ]);
    exit;
}

try {
    // 加载数据
    $cities = loadCities();
    $trips = loadTrips($startDate, $endDate);
    
    // 转换城市坐标为GCJ02（高德坐标系）
    foreach ($cities as $cityName => $cityData) {
        $converted = wgs84ToGcj02($cityData['latitude'], $cityData['longitude']);
        $cities[$cityName]['latitude'] = $converted['lat'];
        $cities[$cityName]['longitude'] = $converted['lng'];
    }
    
    // 返回响应
    echo json_encode([
        'success' => true,
        'trips' => $trips,
        'cities' => $cities,
        'total' => count($trips),
        'filters' => [
            'start_date' => $startDate,
            'end_date' => $endDate
        ],
        'coordinateSystem' => 'GCJ02' // 标识坐标系统
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => '服务器错误：' . $e->getMessage()
    ]);
}
?>
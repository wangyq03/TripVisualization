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

// 保存城市数据
function saveCities($cities) {
    $citiesFile = __DIR__ . '/../data/cities.json';
    
    // 创建备份
    if (file_exists($citiesFile)) {
        $backupFile = __DIR__ . '/../data/cities_backup_' . date('Y-m-d_H-i-s') . '.json';
        copy($citiesFile, $backupFile);
    }
    
    // 写入新数据
    $jsonContent = json_encode($cities, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($citiesFile, $jsonContent) !== false;
}

// 验证纬度
function isValidLatitude($lat) {
    return is_numeric($lat) && $lat >= -90 && $lat <= 90;
}

// 验证经度
function isValidLongitude($lng) {
    return is_numeric($lng) && $lng >= -180 && $lng <= 180;
}

// 处理GET请求
function handleGetRequest() {
    try {
        $cities = loadCities();
        
        // 检查是否需要转换坐标系（查看URL参数）
        $convertCoordinates = isset($_GET['convert']) && $_GET['convert'] === 'gcj02';
        
        // 转换为数组格式
        $citiesArray = [];
        foreach ($cities as $name => $data) {
            $latitude = $data['latitude'];
            $longitude = $data['longitude'];
            
            // 如果需要转换坐标系（WGS84 -> GCJ02）
            if ($convertCoordinates) {
                $converted = wgs84ToGcj02($latitude, $longitude);
                $latitude = $converted['lat'];
                $longitude = $converted['lng'];
            }
            
            $citiesArray[] = [
                'name' => $name,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'note' => $data['note'] ?? '',
                'coordinateSystem' => $convertCoordinates ? 'GCJ02' : 'WGS84'
            ];
        }
        
        // 按名称排序
        usort($citiesArray, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });
        
        echo json_encode([
            'success' => true,
            'cities' => $citiesArray,
            'total' => count($citiesArray),
            'coordinateSystem' => $convertCoordinates ? 'GCJ02 (高德/火星坐标)' : 'WGS84 (GPS坐标)'
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => '获取城市数据失败：' . $e->getMessage()
        ]);
    }
}

// 处理POST请求
function handlePostRequest() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['action'])) {
        echo json_encode(['success' => false, 'error' => '无效的请求参数']);
        exit;
    }
    
    try {
        switch ($input['action']) {
            case 'add':
                if (!isset($input['cities']) || !is_array($input['cities'])) {
                    echo json_encode(['success' => false, 'error' => '城市数据格式错误']);
                    exit;
                }
                
                $cities = loadCities();
                $addedCount = 0;
                $updatedCount = 0;
                
                foreach ($input['cities'] as $cityData) {
                    $name = trim($cityData['name'] ?? '');
                    $latitude = floatval($cityData['latitude'] ?? 0);
                    $longitude = floatval($cityData['longitude'] ?? 0);
                    $note = trim($cityData['note'] ?? '');
                    
                    // 验证数据
                    if (empty($name)) {
                        echo json_encode(['success' => false, 'error' => '城市名称不能为空']);
                        exit;
                    }
                    
                    if (!isValidLatitude($latitude)) {
                        echo json_encode(['success' => false, 'error' => "城市 {$name} 的纬度无效：{$latitude}"]);
                        exit;
                    }
                    
                    if (!isValidLongitude($longitude)) {
                        echo json_encode(['success' => false, 'error' => "城市 {$name} 的经度无效：{$longitude}"]);
                        exit;
                    }
                    
                    // 添加或更新城市
                    if (isset($cities[$name])) {
                        $updatedCount++;
                    } else {
                        $addedCount++;
                    }
                    
                    $cities[$name] = [
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'note' => $note,
                        'updateDate' => date('Y-m-d H:i:s')
                    ];
                }
                
                if (saveCities($cities)) {
                    echo json_encode([
                        'success' => true,
                        'message' => '城市数据保存成功',
                        'added' => $addedCount,
                        'updated' => $updatedCount,
                        'total' => $addedCount + $updatedCount
                    ]);
                } else {
                    echo json_encode(['success' => false, 'error' => '保存数据失败']);
                }
                break;
                
            case 'delete':
                if (!isset($input['cityName'])) {
                    echo json_encode(['success' => false, 'error' => '城市名称不能为空']);
                    exit;
                }
                
                $cityName = trim($input['cityName']);
                $cities = loadCities();
                
                if (!isset($cities[$cityName])) {
                    echo json_encode(['success' => false, 'error' => '城市不存在：' . $cityName]);
                    exit;
                }
                
                // 检查是否有行程使用这个城市
                $tripsFile = __DIR__ . '/../data/trips.csv';
                if (file_exists($tripsFile)) {
                    $lines = file($tripsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    foreach ($lines as $line) {
                        $parts = str_getcsv($line);
                        if (count($parts) >= 3) {
                            $origin = trim($parts[1]);
                            $destination = trim($parts[2]);
                            if ($origin === $cityName || $destination === $cityName) {
                                echo json_encode([
                                    'success' => false, 
                                    'error' => "无法删除城市 {$cityName}，存在相关行程数据。请先删除相关行程。"
                                ]);
                                exit;
                            }
                        }
                    }
                }
                
                unset($cities[$cityName]);
                
                if (saveCities($cities)) {
                    echo json_encode([
                        'success' => true,
                        'message' => "城市 {$cityName} 删除成功"
                    ]);
                } else {
                    echo json_encode(['success' => false, 'error' => '删除失败']);
                }
                break;
                
            default:
                echo json_encode(['success' => false, 'error' => '未知操作']);
                break;
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => '操作失败：' . $e->getMessage()
        ]);
    }
}

// 根据请求方法处理
switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        handleGetRequest();
        break;
    case 'POST':
        handlePostRequest();
        break;
    default:
        echo json_encode(['success' => false, 'error' => '不支持的请求方法']);
        break;
}
?>
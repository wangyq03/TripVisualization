<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// 设置时区
date_default_timezone_set('Asia/Shanghai');

// 引入数据库配置
require_once 'config.php';
require_once 'common.php';

// 数据库中存储的是高德坐标系（GCJ02），无需转换

// 从数据库读取城市数据
function loadCitiesFromDB() {
    $pdo = getDBConnection();
    if (!$pdo) {
        throw new Exception('数据库连接失败');
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT id, name, latitude, longitude, note, created_at, updated_at, created_by, is_active
            FROM cities 
            WHERE is_active = TRUE 
            ORDER BY name
        ");
        $stmt->execute();
        
        $cities = [];
        while ($row = $stmt->fetch()) {
            // 转换为原有的JSON格式，保持向后兼容
            $cities[$row['name']] = [
                'latitude' => floatval($row['latitude']),
                'longitude' => floatval($row['longitude']),
                'note' => $row['note'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
                'created_by' => $row['created_by'],
                'is_active' => (bool)$row['is_active'],
                'id' => $row['id']
            ];
        }
        
        return $cities;
    } catch (PDOException $e) {
        error_log("读取城市数据失败: " . $e->getMessage());
        
        // 如果数据库失败，回退到JSON文件
        return loadCitiesFromFile();
    }
}

// 从文件读取城市数据（备用方案）- 已移动到 common.php

// 保存城市数据到数据库
function saveCitiesToDB($cities) {
    $pdo = getDBConnection();
    if (!$pdo) {
        throw new Exception('数据库连接失败');
    }
    
    try {
        $pdo->beginTransaction();
        
        $insertStmt = $pdo->prepare("
            INSERT INTO cities (name, latitude, longitude, note, created_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
            latitude = VALUES(latitude),
            longitude = VALUES(longitude),
            note = VALUES(note),
            updated_at = NOW()
        ");
        
        $addedCount = 0;
        $updatedCount = 0;
        
        foreach ($cities as $name => $data) {
            $latitude = floatval($data['latitude']);
            $longitude = floatval($data['longitude']);
            $note = $data['note'] ?? '';
            $createdBy = $data['created_by'] ?? 'system';
            
            // 检查是否是新增还是更新
            $checkStmt = $pdo->prepare("SELECT id FROM cities WHERE name = ?");
            $checkStmt->execute([$name]);
            
            if ($checkStmt->fetch()) {
                $updatedCount++;
            } else {
                $addedCount++;
            }
            
            $insertStmt->execute([$name, $latitude, $longitude, $note, $createdBy]);
        }
        
        $pdo->commit();
        
        return [
            'success' => true,
            'added' => $addedCount,
            'updated' => $updatedCount,
            'total' => $addedCount + $updatedCount
        ];
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("保存城市数据失败: " . $e->getMessage());
        
        // 如果数据库失败，回退到文件保存
        if (saveCitiesToFile($cities)) {
            return [
                'success' => true,
                'added' => 0,
                'updated' => 0,
                'total' => 0,
                'fallback' => 'file'
            ];
        }
        
        throw new Exception('保存数据失败：' . $e->getMessage());
    }
}

// 保存城市数据到文件（备用方案）
function saveCitiesToFile($cities) {
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

// 从数据库删除城市
function deleteCityFromDB($cityName) {
    $pdo = getDBConnection();
    if (!$pdo) {
        throw new Exception('数据库连接失败');
    }
    
    try {
        $pdo->beginTransaction();
        
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
                        throw new Exception("无法删除城市 {$cityName}，存在相关行程数据。请先删除相关行程。");
                    }
                }
            }
        }
        
        // 软删除：设置为非激活状态
        $stmt = $pdo->prepare("UPDATE cities SET is_active = FALSE, updated_at = NOW() WHERE name = ?");
        $result = $stmt->execute([$cityName]);
        
        if (!$result) {
            throw new Exception("删除城市 {$cityName} 失败");
        }
        
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("删除城市失败: " . $e->getMessage());
        throw new Exception('删除城市失败：' . $e->getMessage());
    }
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
        $cities = loadCitiesFromDB();
        
        // 数据库中的坐标已经是高德坐标系，直接使用
        // 转换为数组格式
        $citiesArray = [];
        foreach ($cities as $name => $data) {
            $citiesArray[] = [
                'id' => $data['id'] ?? null,
                'name' => $name,
                'latitude' => $data['latitude'], // 直接使用高德坐标
                'longitude' => $data['longitude'], // 直接使用高德坐标
                'note' => $data['note'] ?? '',
                'created_at' => $data['created_at'] ?? null,
                'updated_at' => $data['updated_at'] ?? null,
                'created_by' => $data['created_by'] ?? null,
                'coordinateSystem' => 'GCJ02' // 固定为高德坐标系
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
            'coordinateSystem' => 'GCJ02 (高德/火星坐标)', // 固定标识
            'dataSource' => 'database'
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
                
                $cities = [];
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
                    
                    $cities[$name] = [
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'note' => $note,
                        'created_by' => 'admin' // 可以从session获取实际用户
                    ];
                }
                
                $result = saveCitiesToDB($cities);
                
                echo json_encode([
                    'success' => true,
                    'message' => '城市数据保存成功',
                    'added' => $result['added'],
                    'updated' => $result['updated'],
                    'total' => $result['total'],
                    'dataSource' => isset($result['fallback']) ? 'file' : 'database'
                ]);
                break;
                
            case 'delete':
                if (!isset($input['cityName'])) {
                    echo json_encode(['success' => false, 'error' => '城市名称不能为空']);
                    exit;
                }
                
                $cityName = trim($input['cityName']);
                
                try {
                    deleteCityFromDB($cityName);
                    echo json_encode([
                        'success' => true,
                        'message' => "城市 {$cityName} 删除成功"
                    ]);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
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
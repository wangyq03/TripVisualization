<?php
/**
 * 公共函数库
 * 包含在多个API文件中使用的通用函数
 */

// 设置时区
date_default_timezone_set('Asia/Shanghai');

// 从文件读取城市数据（备用方案）
function loadCitiesFromFile() {
    $citiesFile = __DIR__ . '/../data/cities.json';
    if (!file_exists($citiesFile)) {
        return [];
    }
    
    $jsonContent = file_get_contents($citiesFile);
    $cities = json_decode($jsonContent, true);
    
    return $cities ?: [];
}

// 统一错误响应格式
function sendError($message, $statusCode = 400) {
    http_response_code($statusCode);
    echo json_encode([
        'success' => false,
        'error' => $message
    ]);
    exit;
}

// 统一成功响应格式
function sendSuccess($data, $message = '操作成功') {
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// 日志记录函数
function logError($message, $context = []) {
    $logMessage = date('Y-m-d H:i:s') . ' - ' . $message;
    if (!empty($context)) {
        $logMessage .= ' - Context: ' . json_encode($context);
    }
    error_log($logMessage);
}
?>
<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// 设置时区
date_default_timezone_set('Asia/Shanghai');

// 引入数据库配置
require_once __DIR__ . '/config.php';

// 验证用户凭据
function verifyUser($username, $password) {
    // 调试日志
    error_log("尝试验证用户: $username");
    
    $pdo = getDBConnection();
    if (!$pdo) {
        error_log("数据库连接失败");
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id, username, password_hash, name, role FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        error_log("数据库查询结果: " . ($user ? "找到用户" : "未找到用户"));
        
        if ($user) {
            error_log("存储的密码哈希: " . $user['password_hash']);
            $passwordValid = password_verify($password, $user['password_hash']);
            error_log("密码验证结果: " . ($passwordValid ? "成功" : "失败"));
            
            if ($passwordValid) {
                error_log("用户 '$username' 验证成功");
                return $user;
            } else {
                error_log("用户 '$username' 密码错误");
            }
        }
        return false;
    } catch (PDOException $e) {
        error_log("用户验证失败: " . $e->getMessage());
        return false;
    }
}

// 获取用户信息
function getUserInfo($username) {
    $pdo = getDBConnection();
    if (!$pdo) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id, username, name, role FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("获取用户信息失败: " . $e->getMessage());
        return false;
    }
}

// 更新用户密码
function updateUserPassword($username, $newPassword) {
    $pdo = getDBConnection();
    if (!$pdo) {
        return false;
    }
    
    try {
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE username = ?");
        return $stmt->execute([$passwordHash, $username]);
    } catch (PDOException $e) {
        error_log("更新密码失败: " . $e->getMessage());
        return false;
    }
}

// 生成token
function generateToken($username, $role = 'user') {
    $payload = [
        'username' => $username,
        'role' => $role,
        'exp' => time() + (7 * 24 * 60 * 60), // 7天过期
        'iat' => time()
    ];
    
    // 简单的token编码（实际应用中应使用JWT）
    return base64_encode(json_encode($payload)) . '.' . md5(json_encode($payload) . 'secret_key');
}

// 验证token
function verifyToken($token, $username) {
    if (empty($token) || empty($username)) {
        return false;
    }
    
    // 简单的token解码
    $parts = explode('.', $token);
    if (count($parts) !== 2) {
        return false;
    }
    
    try {
        $payload = json_decode(base64_decode($parts[0]), true);
        if (!$payload || $payload['username'] !== $username) {
            return false;
        }
        
        // 检查过期时间
        if ($payload['exp'] < time()) {
            return false;
        }
        
        return $payload; // 返回payload以获取角色信息
    } catch (Exception $e) {
        // 记录错误但返回false
        error_log("Token验证失败: " . $e->getMessage());
        return false;
    }
}

// 处理登录请求
function handleLogin($username, $password) {
    $user = verifyUser($username, $password);
    
    if (!$user) {
        return ['success' => false, 'error' => '用户名或密码错误'];
    }
    
    $token = generateToken($username, $user['role']);
    
    // 设置session
    session_start();
    $_SESSION['logged_in'] = true;
    $_SESSION['username'] = $username;
    $_SESSION['user_role'] = $user['role'];
    
    return [
        'success' => true,
        'token' => $token,
        'user' => [
            'username' => $user['username'],
            'name' => $user['name'],
            'role' => $user['role']
        ],
        'expires_in' => 7 * 24 * 60 * 60 // 7天
    ];
}

// 处理token验证请求
function handleVerify($token, $username) {
    $payload = verifyToken($token, $username);
    if ($payload) {
        // 设置session
        session_start();
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $payload['username'];
        $_SESSION['user_role'] = $payload['role'] ?? 'user';
        
        return [
            'success' => true, 
            'valid' => true,
            'user' => [
                'username' => $payload['username'],
                'role' => $payload['role'] ?? 'user'
            ]
        ];
    } else {
        return ['success' => true, 'valid' => false];
    }
}

// 处理修改密码请求
function handleChangePassword($username, $currentPassword, $newPassword) {
    // 首先验证当前密码
    $user = verifyUser($username, $currentPassword);
    if (!$user) {
        return ['success' => false, 'error' => '当前密码错误'];
    }
    
    // 更新密码
    if (updateUserPassword($username, $newPassword)) {
        return ['success' => true, 'message' => '密码修改成功'];
    } else {
        return ['success' => false, 'error' => '密码修改失败，请稍后重试'];
    }
}

// 主逻辑
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['action'])) {
    echo json_encode(['success' => false, 'error' => '无效的请求参数']);
    exit;
}

switch ($input['action']) {
    case 'login':
        if (!isset($input['username']) || !isset($input['password'])) {
            echo json_encode(['success' => false, 'error' => '缺少用户名或密码']);
            exit;
        }
        
        $result = handleLogin($input['username'], $input['password']);
        echo json_encode($result);
        break;
        
    case 'verify':
        if (!isset($input['token']) || !isset($input['username'])) {
            echo json_encode(['success' => false, 'error' => '缺少token或username']);
            exit;
        }
        
        $result = handleVerify($input['token'], $input['username']);
        echo json_encode($result);
        break;
        
    case 'change_password':
        if (!isset($input['username']) || !isset($input['current_password']) || !isset($input['new_password'])) {
            echo json_encode(['success' => false, 'error' => '缺少必要参数']);
            exit;
        }
        
        $result = handleChangePassword($input['username'], $input['current_password'], $input['new_password']);
        echo json_encode($result);
        break;
        
    case 'logout':
        // 清除session
        session_start();
        session_destroy();
        echo json_encode(['success' => true, 'message' => '登出成功']);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => '未知操作']);
        break;
}
?>
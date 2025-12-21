<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// 设置时区
date_default_timezone_set('Asia/Shanghai');

// 简单的用户数据库（实际应用中应该使用真正的数据库）
$users = [
    'admin' => [
        'password' => password_hash('admin123', PASSWORD_DEFAULT),
        'name' => '管理员',
        'role' => 'admin'
    ],
    'user' => [
        'password' => password_hash('123456', PASSWORD_DEFAULT),
        'name' => '普通用户',
        'role' => 'user'
    ]
];

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
        return false;
    }
}

// 处理登录请求
function handleLogin($username, $password) {
    global $users;
    
    if (!isset($users[$username])) {
        return ['success' => false, 'error' => '用户名或密码错误'];
    }
    
    if (!password_verify($password, $users[$username]['password'])) {
        return ['success' => false, 'error' => '用户名或密码错误'];
    }
    
    $token = generateToken($username, $users[$username]['role']);
    
    // 设置session
    session_start();
    $_SESSION['logged_in'] = true;
    $_SESSION['username'] = $username;
    $_SESSION['user_role'] = $users[$username]['role'];
    
    return [
        'success' => true,
        'token' => $token,
        'user' => [
            'username' => $username,
            'name' => $users[$username]['name'],
            'role' => $users[$username]['role']
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
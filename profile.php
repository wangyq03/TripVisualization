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
    <title>个人中心 - 路线可视化展示系统</title>
    
    <!-- 公共样式 -->
    <link rel="stylesheet" href="css/common.css">
    
    <style>
        .profile-container {
            max-width: 800px;
        }
        
        .profile-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            gap: 2rem;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: bold;
        }
        
        .profile-info h2 {
            margin: 0 0 0.5rem 0;
            color: #333;
        }
        
        .profile-role {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        .role-admin {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .role-user {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        
        .form-section {
            margin-bottom: 2rem;
        }
        
        .form-section h3 {
            margin: 0 0 1rem 0;
            color: #333;
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .password-form {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .form-label {
            font-weight: 600;
            color: #555;
            font-size: 0.95rem;
        }
        
        .form-input {
            padding: 0.75rem 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-input.error {
            border-color: #f44336;
            box-shadow: 0 0 0 3px rgba(244, 67, 54, 0.1);
        }
        
        .success-message {
            background: #e8f5e8;
            color: #2e7d32;
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid #c8e6c9;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            animation: slideIn 0.3s ease;
        }
        
        .error-message {
            background: #ffebee;
            color: #c62828;
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid #ffcdd2;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .btn-primary:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .password-strength {
            margin-top: 0.5rem;
            font-size: 0.875rem;
        }
        
        .strength-weak {
            color: #f44336;
        }
        
        .strength-medium {
            color: #ff9800;
        }
        
        .strength-strong {
            color: #4caf50;
        }
        
        .loading {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #ffffff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .stat-item {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.875rem;
            margin-top: 0.25rem;
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
                        <a href="cities-manager.php" class="nav-link">
                            <span class="nav-icon">📍</span>
                            城市管理
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="profile.php" class="nav-link active">
                            <span class="nav-icon">👤</span>
                            个人中心
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <div class="page-container profile-container">
        <!-- 个人信息卡片 -->
        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                </div>
                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($_SESSION['username']); ?></h2>
                    <span class="profile-role <?php echo $_SESSION['user_role'] === 'admin' ? 'role-admin' : 'role-user'; ?>">
                        <?php echo $_SESSION['user_role'] === 'admin' ? '管理员' : '普通用户'; ?>
                    </span>
                </div>
            </div>
            
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-value" id="login-days">0</div>
                    <div class="stat-label">上次登录</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo $_SESSION['user_role'] === 'admin' ? '全部权限' : '基础权限'; ?></div>
                    <div class="stat-label">权限级别</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">活跃</div>
                    <div class="stat-label">账户状态</div>
                </div>
            </div>
        </div>

        <!-- 修改密码卡片 -->
        <div class="profile-card">
            <div class="form-section">
                <h3>🔒 修改密码</h3>
                
                <div id="message-container"></div>
                
                <form id="password-form" class="password-form">
                    <div class="form-group">
                        <label class="form-label" for="current-password">当前密码</label>
                        <input 
                            type="password" 
                            id="current-password" 
                            name="current_password" 
                            class="form-input" 
                            required
                            placeholder="请输入当前密码"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="new-password">新密码</label>
                        <input 
                            type="password" 
                            id="new-password" 
                            name="new_password" 
                            class="form-input" 
                            required
                            minlength="6"
                            placeholder="请输入新密码（至少6位）"
                        >
                        <div class="password-strength" id="password-strength"></div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="confirm-password">确认新密码</label>
                        <input 
                            type="password" 
                            id="confirm-password" 
                            name="confirm_password" 
                            class="form-input" 
                            required
                            placeholder="请再次输入新密码"
                        >
                    </div>
                    
                    <button type="submit" class="btn btn-primary" id="submit-btn">
                        <span id="btn-text">修改密码</span>
                        <span class="loading" id="btn-loading" style="display: none;"></span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // 密码强度检测
        function checkPasswordStrength(password) {
            let strength = 0;
            
            if (password.length >= 8) strength++;
            if (password.length >= 12) strength++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            
            return strength;
        }
        
        // 更新密码强度提示
        function updatePasswordStrength(password) {
            const strengthEl = document.getElementById('password-strength');
            const strength = checkPasswordStrength(password);
            
            if (password.length === 0) {
                strengthEl.textContent = '';
                strengthEl.className = 'password-strength';
                return;
            }
            
            if (strength <= 2) {
                strengthEl.textContent = '密码强度：弱';
                strengthEl.className = 'password-strength strength-weak';
            } else if (strength <= 4) {
                strengthEl.textContent = '密码强度：中等';
                strengthEl.className = 'password-strength strength-medium';
            } else {
                strengthEl.textContent = '密码强度：强';
                strengthEl.className = 'password-strength strength-strong';
            }
        }
        
        // 显示消息
        function showMessage(message, type) {
            const container = document.getElementById('message-container');
            const messageEl = document.createElement('div');
            messageEl.className = `${type}-message`;
            messageEl.innerHTML = `
                <span>${type === 'success' ? '✅' : '❌'}</span>
                <span>${message}</span>
            `;
            
            container.innerHTML = '';
            container.appendChild(messageEl);
            
            // 5秒后自动隐藏消息
            setTimeout(() => {
                if (messageEl.parentNode) {
                    messageEl.remove();
                }
            }, 5000);
        }
        
        // 设置按钮加载状态
        function setButtonLoading(loading) {
            const btn = document.getElementById('submit-btn');
            const btnText = document.getElementById('btn-text');
            const btnLoading = document.getElementById('btn-loading');
            
            btn.disabled = loading;
            btnText.style.display = loading ? 'none' : 'inline';
            btnLoading.style.display = loading ? 'inline-block' : 'none';
        }
        
        // 监听新密码输入
        document.getElementById('new-password').addEventListener('input', function(e) {
            updatePasswordStrength(e.target.value);
        });
        
        // 监听确认密码输入
        document.getElementById('confirm-password').addEventListener('input', function(e) {
            const newPassword = document.getElementById('new-password').value;
            const confirmPassword = e.target.value;
            const confirmInput = e.target;
            
            if (confirmPassword && newPassword !== confirmPassword) {
                confirmInput.classList.add('error');
            } else {
                confirmInput.classList.remove('error');
            }
        });
        
        // 处理表单提交
        document.getElementById('password-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const currentPassword = document.getElementById('current-password').value;
            const newPassword = document.getElementById('new-password').value;
            const confirmPassword = document.getElementById('confirm-password').value;
            
            // 前端验证
            if (newPassword !== confirmPassword) {
                showMessage('两次输入的密码不一致', 'error');
                return;
            }
            
            if (newPassword.length < 6) {
                showMessage('新密码长度至少为6位', 'error');
                return;
            }
            
            if (currentPassword === newPassword) {
                showMessage('新密码不能与当前密码相同', 'error');
                return;
            }
            
            setButtonLoading(true);
            
            try {
                const response = await fetch('api/auth.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'change_password',
                        username: '<?php echo $_SESSION['username']; ?>',
                        current_password: currentPassword,
                        new_password: newPassword
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showMessage('密码修改成功！', 'success');
                    // 清空表单
                    document.getElementById('password-form').reset();
                    document.getElementById('password-strength').textContent = '';
                } else {
                    showMessage(result.error || '密码修改失败', 'error');
                }
            } catch (error) {
                console.error('修改密码错误:', error);
                showMessage('网络错误，请稍后重试', 'error');
            } finally {
                setButtonLoading(false);
            }
        });
        
        // 更新上次登录时间
        document.addEventListener('DOMContentLoaded', function() {
            const lastLogin = localStorage.getItem('lastLogin') || '今天';
            document.getElementById('login-days').textContent = lastLogin;
            
            // 记录当前登录时间
            const now = new Date().toLocaleDateString('zh-CN');
            localStorage.setItem('lastLogin', now);
        });
    </script>
    
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
</body>
</html>
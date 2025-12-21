// 页面加载完成后初始化
document.addEventListener('DOMContentLoaded', function() {
    setupEventListeners();
    checkLoginStatus();
});

// 设置事件监听器
function setupEventListeners() {
    const loginForm = document.getElementById('loginForm');
    
    loginForm.addEventListener('submit', function(e) {
        e.preventDefault();
        handleLogin();
    });

    // 回车键登录
    document.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            handleLogin();
        }
    });
}

// 检查登录状态
function checkLoginStatus() {
    const token = localStorage.getItem('authToken');
    const username = localStorage.getItem('username');
    
    if (token && username) {
        // 验证token是否有效
        verifyToken(token, username);
    }
}

// 验证token有效性
function verifyToken(token, username) {
    fetch('api/auth.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'verify',
            token: token,
            username: username
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.valid) {
            // Token有效，直接跳转
            showToast('success', '欢迎回来，' + username + '！');
            setTimeout(() => {
                window.location.href = 'index.php';
            }, 1000);
        } else {
            // Token无效，清除本地存储
            localStorage.removeItem('authToken');
            localStorage.removeItem('username');
            localStorage.removeItem('loginTime');
        }
    })
    .catch(error => {
        console.error('验证token失败:', error);
        localStorage.removeItem('authToken');
        localStorage.removeItem('username');
        localStorage.removeItem('loginTime');
    });
}

// 处理登录
function handleLogin() {
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value.trim();
    const loginBtn = document.getElementById('loginBtn');
    const loginBtnText = document.getElementById('loginBtnText');
    const errorMessage = document.getElementById('errorMessage');

    if (!username || !password) {
        showError('请输入用户名和密码');
        return;
    }

    // 显示加载状态
    loginBtn.disabled = true;
    loginBtnText.textContent = '登录中...';

    // 发送登录请求
    fetch('api/auth.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'login',
            username: username,
            password: password
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // 登录成功
            localStorage.setItem('authToken', data.token);
            localStorage.setItem('username', username);
            localStorage.setItem('userRole', data.user.role);
            localStorage.setItem('loginTime', Date.now());
            
            showToast('success', '登录成功！正在跳转...');
            
            setTimeout(() => {
                window.location.href = 'index.php';
            }, 1000);
        } else {
            // 登录失败
            showError(data.error || '登录失败，请检查用户名和密码');
        }
    })
    .catch(error => {
        console.error('登录失败:', error);
        showError('登录失败，请稍后重试');
    })
    .finally(() => {
        // 恢复按钮状态
        loginBtn.disabled = false;
        loginBtnText.textContent = '登录';
    });
}

// 显示错误信息
function showError(message) {
    const errorMessage = document.getElementById('errorMessage');
    errorMessage.textContent = message;
    errorMessage.style.display = 'block';
    
    // 5秒后自动隐藏
    setTimeout(() => {
        errorMessage.style.display = 'none';
    }, 5000);
}

// 显示Toast提示
function showToast(type, message) {
    // 移除现有的Toast
    const existingToast = document.querySelector('.toast');
    if (existingToast) {
        existingToast.remove();
    }

    // 创建Toast元素
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = message;
    
    // 添加样式
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 16px 24px;
        border-radius: 8px;
        color: white;
        font-weight: 500;
        z-index: 9999;
        opacity: 0;
        transform: translateX(100%);
        transition: all 0.3s ease;
        max-width: 400px;
        word-wrap: break-word;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    `;
    
    // 根据类型设置背景色
    if (type === 'success') {
        toast.style.background = 'linear-gradient(135deg, #28a745, #20c997)';
    } else if (type === 'error') {
        toast.style.background = 'linear-gradient(135deg, #dc3545, #fd7e14)';
    } else if (type === 'warning') {
        toast.style.background = 'linear-gradient(135deg, #ffc107, #fd7e14)';
        toast.style.color = '#333';
    }
    
    // 添加到页面
    document.body.appendChild(toast);
    
    // 触发动画
    setTimeout(() => {
        toast.style.opacity = '1';
        toast.style.transform = 'translateX(0)';
    }, 100);
    
    // 3秒后移除
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    }, 3000);
}
// 用户菜单组件
class UserMenu {
    constructor() {
        this.isLoggedIn = false;
        this.username = null;
        this.userRole = null;
        this.init();
    }

    init() {
        this.checkLoginStatus();
        this.createMenu();
    }

    // 检查登录状态
    checkLoginStatus() {
        const token = localStorage.getItem('authToken');
        this.username = localStorage.getItem('username');
        this.userRole = localStorage.getItem('userRole');
        
        if (token && this.username) {
            this.isLoggedIn = true;
            // 不再进行服务器端验证，因为PHP已经验证过了
            this.updateMenu(true, this.username);
            this.setupNavigation();
        } else {
            this.isLoggedIn = false;
            this.updateMenu(false);
        }
    }

    // 验证token有效性
    verifyToken(token, username) {
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
                // Token有效，保存角色信息并更新菜单
                this.userRole = data.user.role;
                localStorage.setItem('userRole', data.user.role);
                this.updateMenu(true, username);
                this.setupNavigation(); // 设置导航权限
            } else {
                // Token无效，清除本地存储
                this.logout();
            }
        })
        .catch(error => {
            console.error('验证token失败:', error);
            this.logout();
        });
    }

    // 创建菜单
    createMenu() {
        // 添加CSS样式
        this.addStyles();
        
        // 等待导航栏加载完成
        setTimeout(() => {
            // 查找导航栏容器
            const navContainer = document.querySelector('.nav-container');
            if (navContainer) {
                // 创建菜单HTML
                const menuHtml = `
                    <div id="userMenuContainer">
                        ${this.isLoggedIn ? this.getLoggedInMenu() : this.getLoggedOutMenu()}
                    </div>
                `;
                
                // 添加到导航栏中
                navContainer.insertAdjacentHTML('beforeend', menuHtml);
                
                // 绑定事件
                this.bindEvents();
            }
        }, 100);
    }

    // 登录后的菜单
    getLoggedInMenu() {
        return `
            <div class="user-menu-wrapper">
                <div class="user-info" id="userInfo">
                    <span class="user-avatar">👤</span>
                    <span class="user-name">${this.username || '用户'}</span>
                    <span class="dropdown-arrow">▼</span>
                </div>
                <div class="user-dropdown" id="userDropdown">
                    <div class="dropdown-header">
                        <span class="user-avatar-large">👤</span>
                        <div class="user-details">
                            <div class="user-name-large">${this.username || '用户'}</div>
                            <div class="user-role">已登录用户</div>
                        </div>
                    </div>
                    <div class="dropdown-divider"></div>
                    <a href="#" class="dropdown-item logout-item" onclick="userMenu.handleLogout()">
                        <span class="item-icon">🚪</span>
                        <span class="item-text">退出登录</span>
                    </a>
                </div>
            </div>
        `;
    }

    // 登录前的菜单
    getLoggedOutMenu() {
        return `
            <div class="login-btn-wrapper">
                <a href="login.html" class="login-btn">
                    <span class="login-icon">🔑</span>
                    <span class="login-text">登录</span>
                </a>
            </div>
        `;
    }

    // 更新菜单
    updateMenu(loggedIn, username = null) {
        this.isLoggedIn = loggedIn;
        this.username = username;
        
        const container = document.getElementById('userMenuContainer');
        if (container) {
            container.innerHTML = loggedIn ? this.getLoggedInMenu() : this.getLoggedOutMenu();
            this.bindEvents();
        }
    }

    // 绑定事件
    bindEvents() {
        const userInfo = document.getElementById('userInfo');
        const dropdown = document.getElementById('userDropdown');
        
        if (userInfo && dropdown) {
            // 点击用户信息显示/隐藏下拉菜单
            userInfo.addEventListener('click', (e) => {
                e.stopPropagation();
                dropdown.classList.toggle('show');
            });

            // 点击页面其他地方关闭下拉菜单
            document.addEventListener('click', () => {
                dropdown.classList.remove('show');
            });

            // 阻止下拉菜单内部点击事件冒泡
            dropdown.addEventListener('click', (e) => {
                e.stopPropagation();
            });
        }
    }

    // 处理登出
    handleLogout() {
        if (confirm('确定要退出登录吗？')) {
            this.logout();
        }
    }

    // 设置导航权限
    setupNavigation() {
        if (!this.isLoggedIn) return;
        
        // 隐藏城市管理链接（非admin用户）
        const citiesNavLink = document.querySelector('a[href="cities-manager.php"]');
        if (citiesNavLink && this.userRole !== 'admin') {
            citiesNavLink.style.display = 'none';
        }
        
        // 检查当前页面权限
        this.checkPagePermissions();
    }

    // 检查页面权限
    checkPagePermissions() {
        const currentPage = window.location.pathname.split('/').pop();
        
        // 城市管理页面需要admin权限
        if (currentPage === 'cities-manager.php' && this.userRole !== 'admin') {
            this.showPermissionError();
            return;
        }
    }

    // 显示权限错误
    showPermissionError() {
        this.showToast('error', '❌ 您没有访问此页面的权限');
        
        // 显示权限提示对话框
        const modal = document.createElement('div');
        modal.className = 'permission-modal';
        modal.innerHTML = `
            <div class="permission-content">
                <div class="permission-icon">🚫</div>
                <h3>访问被拒绝</h3>
                <p>您没有权限访问城市管理页面。<br>此功能仅对管理员开放。</p>
                <button class="permission-btn" onclick="this.closest('.permission-modal').remove(); window.location.href='index.php'">
                    返回首页
                </button>
            </div>
            <div class="permission-backdrop"></div>
        `;
        
        // 添加样式
        const style = document.createElement('style');
        style.textContent = `
            .permission-modal {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 10000;
                display: flex;
                align-items: center;
                justify-content: center;
                animation: modalFadeIn 0.3s ease;
            }
            
            .permission-content {
                background: white;
                border-radius: 16px;
                padding: 2rem;
                text-align: center;
                box-shadow: 0 20px 40px rgba(0,0,0,0.2);
                z-index: 10001;
                max-width: 400px;
                animation: modalSlideIn 0.3s ease;
            }
            
            .permission-icon {
                font-size: 4rem;
                margin-bottom: 1rem;
            }
            
            .permission-content h3 {
                color: #dc3545;
                margin-bottom: 1rem;
                font-size: 1.5rem;
            }
            
            .permission-content p {
                color: #666;
                margin-bottom: 2rem;
                line-height: 1.6;
            }
            
            .permission-btn {
                background: linear-gradient(135deg, #667eea, #764ba2);
                color: white;
                border: none;
                padding: 12px 32px;
                border-radius: 8px;
                font-size: 1rem;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
            }
            
            .permission-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
            }
            
            .permission-backdrop {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
                backdrop-filter: blur(4px);
            }
            
            @keyframes modalFadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            
            @keyframes modalSlideIn {
                from { 
                    opacity: 0;
                    transform: translateY(-20px) scale(0.9);
                }
                to { 
                    opacity: 1;
                    transform: translateY(0) scale(1);
                }
            }
        `;
        document.head.appendChild(style);
        document.body.appendChild(modal);
        
        // 3秒后自动跳转
        setTimeout(() => {
            window.location.href = 'index.php';
        }, 3000);
    }

    // 登出
    logout() {
        // 调用登出API
        fetch('api/auth.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'logout'
            })
        })
        .then(response => response.json())
        .then(data => {
            // 清除本地存储
            localStorage.removeItem('authToken');
            localStorage.removeItem('username');
            localStorage.removeItem('userRole');
            localStorage.removeItem('loginTime');
            
            // 显示提示
            this.showToast('success', '已安全退出登录');
            
            // 跳转到登录页
            setTimeout(() => {
                window.location.href = 'login.html';
            }, 1000);
        })
        .catch(error => {
            console.error('登出失败:', error);
            // 即使API调用失败，也清除本地存储并跳转
            localStorage.removeItem('authToken');
            localStorage.removeItem('username');
            localStorage.removeItem('userRole');
            localStorage.removeItem('loginTime');
            
            this.showToast('success', '已安全退出登录');
            setTimeout(() => {
                window.location.href = 'login.html';
            }, 1000);
        });
    }

    // 显示Toast提示
    showToast(type, message) {
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

    // 添加CSS样式
    addStyles() {
        const style = document.createElement('style');
        style.textContent = `
            #userMenuContainer {
                margin-left: auto;
                position: relative;
            }

            .user-menu-wrapper {
                position: relative;
            }

            .user-info {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 8px 16px;
                color: rgba(255, 255, 255, 0.9);
                cursor: pointer;
                transition: all 0.3s ease;
                border-radius: 8px;
                border: 2px solid transparent;
            }

            .user-info:hover {
                color: white;
                background: rgba(255, 255, 255, 0.1);
                border-color: rgba(255, 255, 255, 0.3);
            }

            .user-avatar {
                font-size: 18px;
                width: 32px;
                height: 32px;
                display: flex;
                align-items: center;
                justify-content: center;
                background: rgba(255, 255, 255, 0.2);
                border-radius: 50%;
                color: white;
            }

            .user-name {
                font-weight: 600;
                color: rgba(255, 255, 255, 0.9);
                font-size: 14px;
            }

            .dropdown-arrow {
                font-size: 10px;
                color: rgba(255, 255, 255, 0.7);
                transition: transform 0.3s ease;
            }

            .user-info:hover .dropdown-arrow {
                transform: rotate(180deg);
                color: white;
            }

            .user-dropdown {
                position: absolute;
                top: calc(100% + 8px);
                right: 0;
                min-width: 280px;
                background: white;
                border-radius: 12px;
                box-shadow: 0 8px 24px rgba(0,0,0,0.15);
                display: none;
                overflow: hidden;
                animation: dropdownSlide 0.3s ease;
                z-index: 1000;
            }

            .user-dropdown.show {
                display: block;
            }

            .dropdown-header {
                padding: 20px;
                background: linear-gradient(135deg, #667eea, #764ba2);
                color: white;
                display: flex;
                align-items: center;
                gap: 12px;
            }

            .user-avatar-large {
                font-size: 32px;
                width: 48px;
                height: 48px;
                display: flex;
                align-items: center;
                justify-content: center;
                background: rgba(255,255,255,0.2);
                border-radius: 50%;
            }

            .user-details {
                flex: 1;
            }

            .user-name-large {
                font-size: 16px;
                font-weight: 600;
                margin-bottom: 4px;
            }

            .user-role {
                font-size: 12px;
                opacity: 0.8;
            }

            .dropdown-divider {
                height: 1px;
                background: #e1e5e9;
                margin: 0;
            }

            .dropdown-item {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 16px 20px;
                color: #333;
                text-decoration: none;
                transition: all 0.2s ease;
                border: none;
                background: none;
                width: 100%;
                text-align: left;
                font-size: 14px;
                cursor: pointer;
            }

            .dropdown-item:hover {
                background: rgba(102, 126, 234, 0.1);
                color: #667eea;
            }

            .logout-item:hover {
                background: rgba(220, 53, 69, 0.1);
                color: #dc3545;
            }

            .item-icon {
                font-size: 16px;
            }

            .item-text {
                font-weight: 500;
            }

            .login-btn-wrapper {
                margin-left: auto;
            }

            .login-btn {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 8px 20px;
                background: rgba(255, 255, 255, 0.2);
                color: white;
                text-decoration: none;
                border-radius: 8px;
                transition: all 0.3s ease;
                font-weight: 600;
                font-size: 14px;
                border: 2px solid rgba(255, 255, 255, 0.3);
            }

            .login-btn:hover {
                background: rgba(255, 255, 255, 0.3);
                border-color: rgba(255, 255, 255, 0.5);
                transform: translateY(-1px);
            }

            .login-icon {
                font-size: 16px;
            }

            @keyframes dropdownSlide {
                from {
                    opacity: 0;
                    transform: translateY(-10px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            /* 移动端适配 */
            @media (max-width: 768px) {
                .user-info {
                    padding: 6px 12px;
                }

                .user-name {
                    display: none;
                }

                .user-avatar {
                    width: 28px;
                    height: 28px;
                    font-size: 16px;
                }

                .login-btn {
                    padding: 6px 16px;
                    font-size: 12px;
                }

                .user-dropdown {
                    min-width: 240px;
                    right: -10px;
                }

                .dropdown-header {
                    padding: 16px;
                }

                .user-avatar-large {
                    font-size: 28px;
                    width: 40px;
                    height: 40px;
                }

                .user-name-large {
                    font-size: 14px;
                }
            }

            /* 确保导航栏样式兼容 */
            .nav-container {
                display: flex;
                align-items: center;
                justify-content: space-between;
            }
        `;
        document.head.appendChild(style);
    }
}

// 全局实例化
let userMenu;

// 页面加载完成后初始化
document.addEventListener('DOMContentLoaded', function() {
    userMenu = new UserMenu();
});
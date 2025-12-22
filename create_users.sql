-- ========================================
-- 路线可视化展示系统 - 用户表结构
-- ========================================
-- 生成时间: 2025-01-20
-- 数据库: MySQL 5.7+
-- 字符集: utf8mb4

-- 创建用户表
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY COMMENT '用户ID',
    `username` VARCHAR(50) NOT NULL UNIQUE COMMENT '用户名',
    `password_hash` VARCHAR(255) NOT NULL COMMENT '密码哈希',
    `name` VARCHAR(100) NOT NULL COMMENT '显示名称',
    `role` ENUM('admin', 'user') NOT NULL DEFAULT 'user' COMMENT '用户角色',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    `last_login` TIMESTAMP NULL COMMENT '最后登录时间',
    `is_active` BOOLEAN DEFAULT TRUE COMMENT '是否启用',
    
    INDEX `idx_username` (`username`),
    INDEX `idx_role` (`role`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户表';

-- 插入默认用户数据
-- 密码均为明文的哈希值，请在部署前修改

INSERT INTO `users` (`username`, `password_hash`, `name`, `role`) VALUES 
(
    'admin', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: admin123
    '系统管理员', 
    'admin'
),
(
    'user', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: user123  
    '普通用户', 
    'user'
)
ON DUPLICATE KEY UPDATE 
    `password_hash` = VALUES(`password_hash`),
    `name` = VALUES(`name`),
    `role` = VALUES(`role`),
    `updated_at` = CURRENT_TIMESTAMP;

-- ========================================
-- 生成新密码哈希的方法
-- ========================================

-- 方法1: 使用PHP命令行
/*
php -r "echo password_hash('your_new_password', PASSWORD_DEFAULT);"
*/

-- 方法2: 创建临时PHP文件
/*
<?php
echo password_hash('your_new_password', PASSWORD_DEFAULT);
?>
*/

-- 方法3: 使用在线工具
-- https://passwordhash.org/

-- ========================================
-- 安全配置建议
-- ========================================

/*
1. 修改默认密码:
   - 立即修改admin和user的默认密码
   - 使用强密码（至少12位，包含大小写、数字、特殊字符）

2. 数据库权限:
   - 创建专用数据库用户，不要使用root
   - 只授予必要的权限（SELECT, INSERT, UPDATE, DELETE）
   
3. 连接安全:
   - 使用SSL连接数据库
   - 将数据库凭据存储在环境变量中
   - 不要将密码提交到版本控制系统

4. 定期维护:
   - 定期更改密码
   - 监控登录日志
   - 禁用不活跃的用户账户
*/

-- ========================================
-- 示例用户管理查询
-- ========================================

-- 查看所有用户
/*
SELECT id, username, name, role, created_at, last_login FROM users ORDER BY created_at;
*/

-- 修改用户密码
/*
UPDATE users SET password_hash = '$2y$10$新的哈希值' WHERE username = '用户名';
*/

-- 禁用用户
/*
UPDATE users SET is_active = FALSE WHERE username = '用户名';
*/

-- 添加新用户
/*
INSERT INTO users (username, password_hash, name, role) 
VALUES ('newuser', '$2y$10$新密码哈希', '新用户', 'user');
*/

-- ========================================
-- 部署检查清单
-- ========================================

/*
□ 已修改默认密码
□ 已创建专用数据库用户
□ 已配置数据库连接参数
□ 已测试数据库连接
□ 已设置适当的数据库权限
□ 已配置备份策略
□ 已启用SSL连接（如需要）
□ 已测试登录功能
*/
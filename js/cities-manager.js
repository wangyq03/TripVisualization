let citiesData = [];

// 页面加载完成后初始化
document.addEventListener('DOMContentLoaded', function() {
    loadCitiesData();
    setupEventListeners();
});

// 设置事件监听器
function setupEventListeners() {
    const validateBtn = document.getElementById('validate-btn');
    const addCitiesBtn = document.getElementById('add-cities-btn');
    const clearBtn = document.getElementById('clear-btn');
    const citiesTextarea = document.getElementById('cities-data');

    validateBtn.addEventListener('click', validateCitiesData);
    addCitiesBtn.addEventListener('click', addCities);
    clearBtn.addEventListener('click', clearInput);
    
    // 实时验证
    citiesTextarea.addEventListener('input', debounce(validateCitiesData, 1000));
}

// 加载城市数据
function loadCitiesData() {
    fetch('api/cities.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                citiesData = data.cities;
                displayCities(citiesData);
                updateStats(citiesData);
            } else {
                showError('加载城市数据失败：' + data.error);
            }
        })
        .catch(error => {
            console.error('加载城市数据失败:', error);
            showError('加载城市数据失败，请稍后重试');
        });
}

// 显示城市列表
function displayCities(cities) {
    const citiesList = document.getElementById('cities-list');
    
    if (cities.length === 0) {
        citiesList.innerHTML = `
            <div style="text-align: center; padding: 3rem; color: #999;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">📍</div>
                <p>暂无城市数据</p>
                <p style="font-size: 0.9rem;">请在上方添加城市信息</p>
            </div>
        `;
        return;
    }

    const citiesGrid = document.createElement('div');
    citiesGrid.className = 'cities-grid';

    cities.forEach((city, index) => {
        const cityCard = document.createElement('div');
        cityCard.className = 'city-card';
        
        cityCard.innerHTML = `
            <button class="delete-city" onclick="deleteCity('${city.name}')" title="删除城市">×</button>
            <div class="city-name">${city.name}</div>
            <div class="city-info">
                <span class="city-label">北纬：</span>
                <span class="city-value">${city.latitude}</span>
            </div>
            <div class="city-info">
                <span class="city-label">东经：</span>
                <span class="city-value">${city.longitude}</span>
            </div>
            ${city.note ? `<div class="city-note">📝 ${city.note}</div>` : ''}
        `;
        
        citiesGrid.appendChild(cityCard);
    });

    citiesList.innerHTML = '';
    citiesList.appendChild(citiesGrid);
}

// 更新统计信息
function updateStats(cities) {
    const totalCities = document.getElementById('total-cities');
    const validCities = document.getElementById('valid-cities');
    const withNotes = document.getElementById('with-notes');
    const recentUpdates = document.getElementById('recent-updates');

    totalCities.textContent = cities.length;
    
    const validCount = cities.filter(city => 
        isValidLatitude(city.latitude) && isValidLongitude(city.longitude)
    ).length;
    validCities.textContent = validCount;
    
    const withNotesCount = cities.filter(city => city.note && city.note.trim()).length;
    withNotes.textContent = withNotesCount;
    
    // 计算今日更新（这里假设都是今天更新的，实际应该从后端获取）
    const today = new Date().toDateString();
    const todayUpdates = cities.filter(city => {
        // 假设如果有updateDate字段就检查，否则算作今天
        return !city.updateDate || new Date(city.updateDate).toDateString() === today;
    }).length;
    recentUpdates.textContent = todayUpdates;
}

// 验证城市数据
function validateCitiesData() {
    const textarea = document.getElementById('cities-data');
    const errorDiv = document.getElementById('validation-error');
    const successDiv = document.getElementById('validation-success');
    const addBtn = document.getElementById('add-cities-btn');

    const data = textarea.value.trim();
    if (!data) {
        showValidationError('');
        successDiv.style.display = 'none';
        addBtn.disabled = true;
        return;
    }

    const lines = data.split('\n').filter(line => line.trim());
    const cities = [];
    const errors = [];
    const warnings = [];

    for (let i = 0; i < lines.length; i++) {
        const line = lines[i].trim();
        const parts = line.split(',').map(part => part.trim());
        
        if (parts.length < 3) {
            errors.push(`第${i + 1}行：数据不完整，至少需要城市、纬度、经度`);
            continue;
        }

        const city = {
            name: parts[0],
            latitude: parseFloat(parts[1]),
            longitude: parseFloat(parts[2]),
            note: parts[3] || ''
        };

        // 验证城市名称
        if (!city.name) {
            errors.push(`第${i + 1}行：城市名称不能为空`);
            continue;
        }

        // 检查重复
        if (citiesData.some(c => c.name === city.name) || 
            cities.some(c => c.name === city.name)) {
            warnings.push(`第${i + 1}行：城市"${city.name}"已存在`);
        }

        // 验证纬度
        if (!isValidLatitude(city.latitude)) {
            errors.push(`第${i + 1}行：纬度${city.latitude}无效，范围应为-90到90`);
            continue;
        }

        // 验证经度
        if (!isValidLongitude(city.longitude)) {
            errors.push(`第${i + 1}行：经度${city.longitude}无效，范围应为-180到180`);
            continue;
        }

        cities.push(city);
    }

    // 显示验证结果
    if (errors.length > 0) {
        showValidationError(errors.join('<br>'));
        successDiv.style.display = 'none';
        addBtn.disabled = true;
    } else {
        let message = `✅ 验证通过！可添加 ${cities.length} 个城市`;
        if (warnings.length > 0) {
            message += `<br><br>⚠️ 注意事项：<br>${warnings.join('<br>')}`;
        }
        
        errorDiv.style.display = 'none';
        successDiv.innerHTML = message;
        successDiv.style.display = 'block';
        addBtn.disabled = false;
        
        // 存储验证通过的数据
        window.validatedCities = cities;
    }
}

// 添加城市
function addCities() {
    if (!window.validatedCities || window.validatedCities.length === 0) {
        showError('没有可添加的城市数据');
        return;
    }

    const addBtn = document.getElementById('add-cities-btn');
    addBtn.disabled = true;
    addBtn.innerHTML = '<span>⏳</span> 添加中...';

    fetch('api/cities.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'add',
            cities: window.validatedCities
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccess(`成功添加 ${data.count} 个城市！`);
            clearInput();
            loadCitiesData(); // 重新加载城市数据
        } else {
            showError('添加失败：' + data.error);
        }
    })
    .catch(error => {
        console.error('添加城市失败:', error);
        showError('添加城市失败，请稍后重试');
    })
    .finally(() => {
        addBtn.disabled = false;
        addBtn.innerHTML = '<span>➕</span> 添加城市';
    });
}

// 删除城市
function deleteCity(cityName) {
    if (!confirm(`确定要删除城市"${cityName}"吗？\n\n注意：删除后可能影响相关行程的显示。`)) {
        return;
    }

    fetch('api/cities.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'delete',
            cityName: cityName
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccess(`成功删除城市"${cityName}"`);
            loadCitiesData(); // 重新加载城市数据
        } else {
            showError('删除失败：' + data.error);
        }
    })
    .catch(error => {
        console.error('删除城市失败:', error);
        showError('删除城市失败，请稍后重试');
    });
}

// 清空输入
function clearInput() {
    document.getElementById('cities-data').value = '';
    document.getElementById('validation-error').style.display = 'none';
    document.getElementById('validation-success').style.display = 'none';
    document.getElementById('add-cities-btn').disabled = true;
    window.validatedCities = null;
}

// 验证纬度
function isValidLatitude(lat) {
    return typeof lat === 'number' && lat >= -90 && lat <= 90 && !isNaN(lat);
}

// 验证经度
function isValidLongitude(lng) {
    return typeof lng === 'number' && lng >= -180 && lng <= 180 && !isNaN(lng);
}

// 显示验证错误
function showValidationError(message) {
    const errorDiv = document.getElementById('validation-error');
    if (message) {
        errorDiv.innerHTML = '❌ ' + message;
        errorDiv.style.display = 'block';
    } else {
        errorDiv.style.display = 'none';
    }
}

// 显示成功信息
function showSuccess(message) {
    showAlert(message, 'success');
}

// 显示错误信息
function showError(message) {
    showAlert(message, 'danger');
}

// 显示提示信息
function showAlert(message, type) {
    // 移除现有的提示（除了验证相关的）
    const existingAlerts = document.querySelectorAll('.alert:not(.alert-success):not(.alert-error)');
    existingAlerts.forEach(alert => alert.remove());

    // 创建新提示
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} fade-in`;
    alertDiv.textContent = message;

    // 插入到页面内容顶部
    const pageContent = document.querySelector('.page-content');
    pageContent.insertBefore(alertDiv, pageContent.firstChild);

    // 3秒后自动移除
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.parentNode.removeChild(alertDiv);
        }
    }, 3000);
}

// 防抖函数
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}
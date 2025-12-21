let currentData = null;
let fileData = null;

// 页面加载完成后初始化
document.addEventListener('DOMContentLoaded', function() {
    initializePage();
});

// 初始化页面
function initializePage() {
    setupEventListeners();
    loadCurrentData();
}



// 设置事件监听器
function setupEventListeners() {
    const uploadArea = document.getElementById('upload-area');
    const fileInput = document.getElementById('csv-file');
    const previewBtn = document.getElementById('preview-btn');
    const uploadBtn = document.getElementById('upload-btn');
    const confirmUploadBtn = document.getElementById('confirm-upload');
    const cancelUploadBtn = document.getElementById('cancel-upload');
    const downloadTemplateBtn = document.getElementById('download-template-btn');

    // 上传区域点击事件
    uploadArea.addEventListener('click', () => fileInput.click());

    // 文件选择事件
    fileInput.addEventListener('change', handleFileSelect);

    // 拖拽事件
    uploadArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadArea.classList.add('dragover');
    });

    uploadArea.addEventListener('dragleave', () => {
        uploadArea.classList.remove('dragover');
    });

    uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            handleFile(files[0]);
        }
    });

    // 按钮事件
    previewBtn.addEventListener('click', previewData);
    uploadBtn.addEventListener('click', uploadData);
    confirmUploadBtn.addEventListener('click', confirmUpload);
    cancelUploadBtn.addEventListener('click', cancelUpload);
    downloadTemplateBtn.addEventListener('click', downloadTemplate);
}



// 处理文件选择
function handleFileSelect(e) {
    const file = e.target.files[0];
    if (file) {
        handleFile(file);
    }
}

// 处理文件
function handleFile(file) {
    const fileName = file.name.toLowerCase();
    const fileExt = fileName.substring(fileName.lastIndexOf('.'));
    
    // 检查文件格式
    if (!['.csv', '.xlsx', '.xls'].includes(fileExt)) {
        showError('请选择 CSV 或 Excel 格式的文件（.csv, .xlsx, .xls）');
        return;
    }

    const fileInfo = document.getElementById('file-info');
    const fileNameEl = document.getElementById('file-name');
    const fileSizeEl = document.getElementById('file-size');

    fileNameEl.textContent = file.name;
    fileSizeEl.textContent = formatFileSize(file.size);
    fileInfo.style.display = 'block';

    // 根据文件类型选择解析方式
    if (fileExt === '.csv') {
        parseCSVFile(file);
    } else {
        parseExcelFile(file);
    }
}

// 解析CSV文件
function parseCSVFile(file) {
    const reader = new FileReader();
    reader.onload = function(e) {
        let content = e.target.result;
        
        // 检测是否有乱码，如果有则尝试用GBK编码重新读取
        if (content.includes('�')) {
            console.log('检测到乱码，尝试使用GBK编码重新读取');
            const gbkReader = new FileReader();
            gbkReader.onload = function(gbkE) {
                try {
                    const gbkContent = gbkE.target.result;
                    fileData = parseCSV(gbkContent);
                    document.getElementById('data-count').textContent = fileData.length;
                    
                    // 启用按钮
                    document.getElementById('preview-btn').disabled = false;
                    document.getElementById('upload-btn').disabled = false;
                    
                    showSuccess(`成功解析CSV文件，共 ${fileData.length} 条数据`);
                } catch (error) {
                    showError('解析CSV文件失败：' + error.message);
                }
            };
            gbkReader.readAsText(file, 'GBK');
            return;
        }
        
        try {
            fileData = parseCSV(content);
            document.getElementById('data-count').textContent = fileData.length;
            
            // 启用按钮
            document.getElementById('preview-btn').disabled = false;
            document.getElementById('upload-btn').disabled = false;
            
            showSuccess(`成功解析CSV文件，共 ${fileData.length} 条数据`);
        } catch (error) {
            showError('解析CSV文件失败：' + error.message);
        }
    };
    reader.readAsText(file, 'UTF-8');
}

// 解析Excel文件
function parseExcelFile(file) {
    const reader = new FileReader();
    reader.onload = function(e) {
        try {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, { type: 'array' });
            
            // 获取第一个工作表
            const firstSheetName = workbook.SheetNames[0];
            const worksheet = workbook.Sheets[firstSheetName];
            
            // 转换为JSON格式
            const jsonData = XLSX.utils.sheet_to_json(worksheet, { header: 1 });
            
            // 解析为行程数据
            fileData = parseExcelData(jsonData);
            document.getElementById('data-count').textContent = fileData.length;
            
            // 启用按钮
            document.getElementById('preview-btn').disabled = false;
            document.getElementById('upload-btn').disabled = false;
            
            showSuccess(`成功解析Excel文件，共 ${fileData.length} 条数据`);
        } catch (error) {
            console.error('Excel解析错误:', error);
            showError('解析Excel文件失败：' + error.message);
        }
    };
    reader.readAsArrayBuffer(file);
}

// 解析Excel数据
function parseExcelData(rows) {
    if (rows.length === 0) {
        throw new Error('Excel文件为空');
    }
    
    const data = [];
    const headerRow = rows[0];
    
    console.log('Excel头部行:', headerRow);
    
    // 查找列索引（支持中英文）
    let originIndex = -1, destinationIndex = -1, dateIndex = -1;
    
    headerRow.forEach((header, index) => {
        if (!header) return;
        const cleanHeader = header.toString().trim().toLowerCase();
        console.log(`列 ${index}: "${header}" -> "${cleanHeader}"`);
        
        if (cleanHeader === 'origin' || cleanHeader.includes('出发') || 
            cleanHeader.includes('起点') || cleanHeader === 'from') {
            originIndex = index;
        } else if (cleanHeader === 'destination' || cleanHeader.includes('目的') || 
                   cleanHeader.includes('终点') || cleanHeader === 'to') {
            destinationIndex = index;
        } else if (cleanHeader === 'date' || cleanHeader.includes('日期') || 
                   cleanHeader === 'time') {
            dateIndex = index;
        }
    });
    
    console.log('找到的列索引:', { originIndex, destinationIndex, dateIndex });
    
    if (originIndex === -1 || destinationIndex === -1 || dateIndex === -1) {
        throw new Error('Excel格式不正确，缺少必要的列：起点/出发地、终点/目的地、日期\n\n' +
            '请确保Excel第一行包含以下列名之一：\n' +
            '• 起点/出发地/origin/from\n' +
            '• 终点/目的地/destination/to\n' +
            '• 日期/date/time');
    }
    
    // 解析数据行
    for (let i = 1; i < rows.length; i++) {
        const row = rows[i];
        if (!row || row.length === 0) continue;
        
        const origin = row[originIndex] ? row[originIndex].toString().trim() : '';
        const destination = row[destinationIndex] ? row[destinationIndex].toString().trim() : '';
        let rawDate = row[dateIndex] ? row[dateIndex].toString().trim() : '';
        
        if (!origin || !destination || !rawDate) {
            console.warn(`跳过第${i + 1}行：数据不完整`);
            continue;
        }
        
        // Excel日期可能是数字（序列日期）
        if (!isNaN(rawDate) && rawDate > 25569) {
            // Excel日期序列号转换为JavaScript日期
            const excelDate = new Date((rawDate - 25569) * 86400 * 1000);
            rawDate = formatDate(excelDate);
        }
        
        // 验证并标准化日期格式
        if (!isValidDate(rawDate)) {
            console.warn(`跳过第${i + 1}行：日期格式不正确 "${rawDate}"`);
            continue;
        }
        
        const standardDate = standardizeDate(rawDate);
        
        data.push({
            origin: origin,
            destination: destination,
            date: standardDate
        });
    }
    
    if (data.length === 0) {
        throw new Error('Excel文件中没有有效的行程数据');
    }
    
    return data;
}

// 格式化日期对象为 YYYY-MM-DD
function formatDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

// 标准化日期格式（将斜杠转换为横线，补齐月份和日期为两位数）
function standardizeDate(dateString) {
    // 将斜杠替换为横线
    const normalized = dateString.replace(/\//g, '-');
    
    // 分割日期部分
    const parts = normalized.split('-');
    if (parts.length !== 3) return normalized;
    
    // 补齐月份和日期为两位数
    const year = parts[0];
    const month = parts[1].padStart(2, '0');
    const day = parts[2].padStart(2, '0');
    
    return `${year}-${month}-${day}`;
}


// 解析CSV内容
function parseCSV(content) {
    const lines = content.split('\n').filter(line => line.trim());
    const data = [];
    
    if (lines.length === 0) {
        throw new Error('CSV文件为空');
    }
    
    // 检查头部 - 支持多种格式
    const headerLine = lines[0].toLowerCase();
    let originIndex = -1, destinationIndex = -1, dateIndex = -1;
    
    // 分割头部（处理可能的空格）
    const headers = parseCSVLine(headerLine);
    
    // 查找列索引
    headers.forEach((header, index) => {
        const cleanHeader = header.trim().toLowerCase();
        if (cleanHeader === 'origin' || cleanHeader.includes('出发') || cleanHeader.includes('起点')) {
            originIndex = index;
        } else if (cleanHeader === 'destination' || cleanHeader.includes('目的') || cleanHeader.includes('终点')) {
            destinationIndex = index;
        } else if (cleanHeader === 'date' || cleanHeader.includes('日期')) {
            dateIndex = index;
        }
    });
    
    if (originIndex === -1 || destinationIndex === -1 || dateIndex === -1) {
        throw new Error('CSV格式不正确，缺少必要的列：出发地、目的地、日期\n\n请确保包含以下列之一：\n• 发起地/origin/起点\n• 目的地/destination/终点\n• 日期/date');
    }

    // 解析数据行
    for (let i = 1; i < lines.length; i++) {
        const line = lines[i].trim();
        if (line) {
            try {
                const parts = parseCSVLine(line);
                
                if (parts.length > Math.max(originIndex, destinationIndex, dateIndex)) {
                    let rawDate = parts[dateIndex] ? parts[dateIndex].trim() : '';
                    
                    // 验证日期格式
                    if (!isValidDate(rawDate)) {
                        throw new Error(`第${i + 1}行日期格式不正确：${rawDate}\n请使用 YYYY-M-D、YYYY-MM-DD 或 YYYY/M/D、YYYY/MM/DD 格式，例如：2025-1-5、2025-01-15 或 2025/1/5、2025/01/15`);
                    }
                    
                    // 统一日期格式为横线分隔
                    const normalizedDate = rawDate.replace(/\//g, '-');
                    
                    const trip = {
                        origin: parts[originIndex] ? parts[originIndex].trim() : '',
                        destination: parts[destinationIndex] ? parts[destinationIndex].trim() : '',
                        date: normalizedDate
                    };

                    // 验证必填字段
                    if (!trip.origin || !trip.destination || !trip.date) {
                        console.warn(`第${i + 1}行数据不完整:`, trip);
                        continue;
                    }

                    data.push(trip);
                }
            } catch (lineError) {
                throw new Error(`第${i + 1}行解析失败：${lineError.message}`);
            }
        }
    }

    if (data.length === 0) {
        throw new Error('CSV文件中没有有效的行程数据');
    }

    return data;
}

// 解析CSV行（处理引号和逗号）
function parseCSVLine(line) {
    const result = [];
    let current = '';
    let inQuotes = false;
    
    for (let i = 0; i < line.length; i++) {
        const char = line[i];
        
        if (char === '"') {
            inQuotes = !inQuotes;
        } else if (char === ',' && !inQuotes) {
            result.push(current.trim());
            current = '';
        } else {
            current += char;
        }
    }
    
    result.push(current.trim());
    return result;
}

// 验证日期格式（支持 YYYY-M-D、YYYY-MM-DD、YYYY/M/D、YYYY/MM/DD 等格式）
function isValidDate(dateString) {
    // 支持横线和斜杠分隔，月份和日期可以是1位或2位
    const regex = /^\d{4}[-\/]\d{1,2}[-\/]\d{1,2}$/;
    if (!regex.test(dateString)) return false;
    
    // 统一转换为横线格式进行验证
    const normalizedDate = dateString.replace(/\//g, '-');
    const date = new Date(normalizedDate);
    return date instanceof Date && !isNaN(date);
}

// 预览数据
function previewData() {
    if (!fileData) {
        showError('没有可预览的数据');
        return;
    }

    const previewContent = document.getElementById('preview-content');
    const previewSection = document.getElementById('preview-section');

    let html = `
        <table class="preview-table">
            <thead>
                <tr>
                    <th>序号</th>
                    <th>出发地</th>
                    <th>目的地</th>
                    <th>日期</th>
                </tr>
            </thead>
            <tbody>
    `;

    fileData.forEach((trip, index) => {
        html += `
            <tr>
                <td>${index + 1}</td>
                <td>${trip.origin}</td>
                <td>${trip.destination}</td>
                <td>${trip.date}</td>
            </tr>
        `;
    });

    html += '</tbody></table>';
    previewContent.innerHTML = html;
    
    // 将预览部分移动到格式要求卡片的后面
    const container = document.querySelector('.page-container');
    if (container) {
        const formatRequirementCard = container.querySelector('.card:last-child');
        
        if (formatRequirementCard) {
            // 在格式要求卡片后面插入预览部分
            formatRequirementCard.parentNode.insertBefore(previewSection, formatRequirementCard.nextSibling);
        }
    }
    
    previewSection.style.display = 'block';
    
    // 滚动到预览部分
    setTimeout(() => {
        previewSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 100);
}

// 上传数据
function uploadData() {
    if (!fileData) {
        showError('没有可上传的数据');
        return;
    }

    // 显示确认对话框
    if (confirm(`即将用 ${fileData.length} 条数据替换现有数据，此操作不可恢复！\n\n确定要继续吗？`)) {
        confirmUpload();
    }
}

// 确认上传
function confirmUpload() {
    if (!fileData) {
        showError('没有可上传的数据');
        return;
    }

    const uploadBtn = document.getElementById('upload-btn');
    uploadBtn.disabled = true;
    uploadBtn.innerHTML = '<span>⏳</span> 上传中...';

    fetch('api/trips.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'upload',
            trips: fileData
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('success', `✅ 数据上传成功！共上传 ${data.count || 0} 条行程数据`);
            resetForm();
        } else {
            showToast('error', '❌ 上传失败：' + data.error);
        }
    })
    .catch(error => {
        console.error('上传失败:', error);
        showToast('error', '❌ 上传失败，请稍后重试');
    })
    .finally(() => {
        uploadBtn.disabled = false;
        uploadBtn.innerHTML = '<span>⬆️</span> 上传替换';
    });
}

// 取消上传
function cancelUpload() {
    document.getElementById('preview-section').style.display = 'none';
}

// 重置表单
function resetForm() {
    document.getElementById('csv-file').value = '';
    document.getElementById('file-info').style.display = 'none';
    document.getElementById('preview-section').style.display = 'none';
    document.getElementById('preview-btn').disabled = true;
    document.getElementById('upload-btn').disabled = true;
    fileData = null;
}

// 下载模板
function downloadTemplate() {
    const template = `origin,destination,date
北京,上海,2025-10-23
天津,北京,2025-10-24
唐山,天津,2025-10-25
北京,广州,2025-10-26
上海,深圳,2025-10-27`;

    const blob = new Blob([template], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    link.setAttribute('href', url);
    link.setAttribute('download', `行程数据模板_${new Date().toISOString().split('T')[0]}.csv`);
    link.style.visibility = 'hidden';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// 格式化文件大小
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// 显示成功信息
function showSuccess(message) {
    showAlert(message, 'success');
}

// 显示错误信息
function showError(message) {
    showAlert(message, 'danger');
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

// 加载当前数据
function loadCurrentData() {
    fetch('api/trips.php?action=current')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentData = data;
                console.log('当前数据加载成功:', data.total, '条行程');
                
                // 在页面上显示当前数据统计
                showDataStats(data);
            } else {
                console.error('加载当前数据失败:', data.error);
                showError('加载当前数据失败：' + (data.error || '未知错误'));
            }
        })
        .catch(error => {
            console.error('加载当前数据失败:', error);
            showError('加载当前数据失败，请稍后重试');
        });
}

// 显示数据统计信息
function showDataStats(data) {
    console.log('showDataStats 被调用，数据:', data);
    
    // 从行程数据中提取实际涉及的城市
    const uniqueCities = new Set();
    let minDate = null;
    let maxDate = null;
    
    data.trips.forEach(trip => {
        if (trip.origin) {
            uniqueCities.add(trip.origin);
        }
        if (trip.destination) {
            uniqueCities.add(trip.destination);
        }
        
        // 计算日期范围
        if (trip.date) {
            const tripDate = new Date(trip.date);
            if (!minDate || tripDate < minDate) {
                minDate = tripDate;
            }
            if (!maxDate || tripDate > maxDate) {
                maxDate = tripDate;
            }
        }
    });
    
    // 格式化日期范围
    let dateRangeText = '-';
    if (minDate && maxDate) {
        const formatDate = (date) => {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };
        dateRangeText = `${formatDate(minDate)} ~ ${formatDate(maxDate)}`;
    }
    
    console.log('统计数据:', {
        trips: data.total,
        cities: uniqueCities.size,
        dateRange: dateRangeText
    });
    
    // 更新统计卡片
    const tripsElement = document.getElementById('current-trips');
    const citiesElement = document.getElementById('current-cities');
    const dateRangeElement = document.getElementById('date-range');
    
    console.log('查找元素:', {
        trips: tripsElement,
        cities: citiesElement,
        dateRange: dateRangeElement
    });
    
    if (tripsElement) {
        tripsElement.textContent = data.total || 0;
    }
    
    if (citiesElement) {
        citiesElement.textContent = uniqueCities.size || 0;
    }
    
    if (dateRangeElement) {
        dateRangeElement.textContent = dateRangeText;
    }
    
    console.log('数据统计更新完成');
}

// 显示提示信息
function showAlert(message, type) {
    // 移除现有的提示
    const existingAlert = document.querySelector('.alert:not(.alert-info)');
    if (existingAlert) {
        existingAlert.remove();
    }

    // 创建新提示
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.textContent = message;
    alertDiv.style.marginBottom = '1rem';

    // 插入到容器顶部
    const container = document.querySelector('.container');
    if (container && container.firstChild) {
        container.insertBefore(alertDiv, container.firstChild);
    }

    // 3秒后自动移除
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.parentNode.removeChild(alertDiv);
        }
    }, 3000);
}
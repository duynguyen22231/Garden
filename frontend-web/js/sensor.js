/* Set active sidebar link dynamically */
function setActiveNavLink() {
    const navLinks = document.querySelectorAll('.sidebar .nav-link');
    const currentPage = window.location.pathname.split('/').pop() || 'sensor.html';

    navLinks.forEach(link => {
        link.classList.remove('active');
        if (link.getAttribute('href') === currentPage) {
            link.classList.add('active');
        }
    });
}

// Get garden_id from URL
function getGardenIdFromUrl() {
    const urlParams = new URLSearchParams(window.location.search);
    const gardenId = urlParams.get('garden_id') || '';
    console.log('gardenId from URL:', gardenId); // Debug log
    return gardenId;
}

// Load garden name and update title
function loadGardenName(gardenId) {
    if (!gardenId) {
        showErrorMessage(new Error('Vui lòng chọn một vườn'));
        window.location.href = 'garden.html';
        return;
    }
    fetch('http://localhost/SmartGarden/backend-api/routes/garden.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=get_garden_by_id&id=${gardenId}`
    })
        .then(async res => {
            if (!res.ok) {
                const text = await res.text();
                throw new Error(`Lỗi HTTP: ${res.status} - ${text}`);
            }
            const text = await res.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error(`Phản hồi không phải JSON: ${text}`);
            }
        })
        .then(data => {
            if (data.success && data.data) {
                document.getElementById('garden-title').textContent = `🌱 Giám Sát & Điều Khiển - ${data.data.garden_names}`;
            } else {
                console.warn('Không tìm thấy dữ liệu vườn:', JSON.stringify(data));
                showErrorMessage(new Error('Không thể tải tên vườn: ' + (data.message || 'Lỗi không xác định')));
                window.location.href = 'garden.html';
            }
        })
        .catch(error => {
            console.error('Lỗi tải tên vườn:', error);
            showErrorMessage(error);
            window.location.href = 'garden.html';
        });
}

// Lưu trữ dữ liệu hiện tại để so sánh
let lastSensorData = null;
let lastDeviceStatus = null;
let lastSchedules = null;
let lastAlerts = null;
let lastMicrocontrollers = null;

// So sánh dữ liệu để kiểm tra thay đổi
function hasDataChanged(newData, oldData) {
    return JSON.stringify(newData) !== JSON.stringify(oldData);
}

// Load all data for a specific garden
function loadGardenData(gardenId) {
    if (!gardenId) {
        showErrorMessage(new Error('Vui lòng chọn một vườn'));
        window.location.href = 'garden.html';
        return;
    }
    loadSensorData(gardenId);
    loadDeviceStatus(gardenId);
    loadSchedules(gardenId);
    loadAlerts(gardenId);
    loadMicrocontrollers(gardenId);
}

// Load sensor data from API
function loadSensorData(gardenId) {
    fetch(`http://localhost/SmartGarden/backend-api/routes/sensor.php?action=latest&garden_id=${gardenId}`)
        .then(async res => {
            if (!res.ok) {
                const text = await res.text();
                throw new Error(`Lỗi HTTP: ${res.status} - ${text}`);
            }
            const text = await res.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error(`Phản hồi không phải JSON: ${text}`);
            }
        })
        .then(data => {
            console.log('Dữ liệu cảm biến:', data);
            if (data && data.success && !data.message) {
                if (hasDataChanged(data.data, lastSensorData)) {
                    updateSensorUI(data.data);
                    lastSensorData = data.data;
                }
            } else {
                showNoDataMessage();
            }
        })
        .catch(error => {
            console.error('Lỗi tải dữ liệu cảm biến:', error);
            showErrorMessage(error);
        });
}

// Update sensor UI with data
function updateSensorUI(sensorData) {
    const now = new Date(sensorData.created_at || new Date());
    const timeString = now.getHours().toString().padStart(2, '0') + ':' + 
                      now.getMinutes().toString().padStart(2, '0');

    const sensorFields = [
        { id: 'soil-moisture', value: sensorData.soil_moisture, unit: '%', timeId: 'soil-time' },
        { id: 'temperature', value: sensorData.temperature, unit: '°C', timeId: 'temp-time' },
        { id: 'humidity', value: sensorData.humidity, unit: '%', timeId: 'humi-time' },
        { id: 'light', value: sensorData.light, unit: 'lux', timeId: 'light-time' },
        { id: 'water-level', value: sensorData.water_level_cm, unit: 'cm', timeId: 'water-time' },
        { id: 'rain-status', value: sensorData.is_raining == 1 ? 'Đang mưa' : 'Không mưa', unit: '', timeId: 'rain-time', class: sensorData.is_raining == 1 ? 'text-info' : 'text-warning' }
    ];

    sensorFields.forEach(field => {
        const element = document.getElementById(field.id);
        const timeElement = document.getElementById(field.timeId);
        if (element && timeElement) {
            element.textContent = (field.value !== null && field.value !== undefined ? field.value : '--') + field.unit;
            if (field.class) element.className = 'card-text sensor-value ' + field.class;
            timeElement.textContent = timeString;
        }
    });
}

// Load device status from API
function loadDeviceStatus(gardenId) {
    fetch(`http://localhost/SmartGarden/backend-api/routes/sensor.php?action=get_status&garden_id=${gardenId}`)
        .then(async res => {
            if (!res.ok) {
                const text = await res.text();
                throw new Error(`Lỗi HTTP: ${res.status} - ${text}`);
            }
            const text = await res.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error(`Phản hồi không phải JSON: ${text}`);
            }
        })
        .then(data => {
            console.log('Trạng thái thiết bị:', data);
            if (data.success && data.data) {
                if (hasDataChanged(data.data, lastDeviceStatus)) {
                    updateDeviceStatusUI(data.data);
                    lastDeviceStatus = data.data;
                }
            } else {
                showNoDeviceStatusMessage();
            }
        })
        .catch(error => {
            console.error('Lỗi tải trạng thái thiết bị:', error);
            showErrorMessage(error);
        });
}

// Update device status UI
function updateDeviceStatusUI(devices) {
    const statusContainer = document.getElementById('device-status-container');
    if (!statusContainer) return;

    statusContainer.innerHTML = `
        <h5 class="card-title text-success">🔌 Trạng thái thiết bị</h5>
        <div class="row g-3">
            ${devices.length === 0 ? '<div class="col-12"><p class="text-muted text-center">Không có thiết bị.</p></div>' : 
            devices.map(device => `
                <div class="col-4">
                    <div class="card h-100 text-center sensor-card">
                        <div class="card-body">
                            <i class="bi bi-gear-fill ${device.status ? 'text-success' : 'text-danger'}"></i>
                            <h6 class="card-title">${device.device_name}</h6>
                            <p class="card-text sensor-value">${device.status ? 'Bật' : 'Tắt'}</p>
                            <p class="text-muted small">Cập nhật: ${new Date(device.last_updated).toLocaleTimeString()}</p>
                        </div>
                    </div>
                </div>
            `).join('')}
        </div>
    `;
}

// Load schedules from API
function loadSchedules(gardenId) {
    fetch(`http://localhost/SmartGarden/backend-api/routes/sensor.php?action=get_schedules&garden_id=${gardenId}`)
        .then(async res => {
            if (!res.ok) {
                const text = await res.text();
                throw new Error(`Lỗi HTTP: ${res.status} - ${text}`);
            }
            const text = await res.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error(`Phản hồi không phải JSON: ${text}`);
            }
        })
        .then(data => {
            console.log('Danh sách lịch trình:', data);
            if (data.success && data.data) {
                if (hasDataChanged(data.data, lastSchedules)) {
                    updateSchedulesUI(data.data);
                    lastSchedules = data.data;
                }
            } else {
                showNoSchedulesMessage();
            }
        })
        .catch(error => {
            console.error('Lỗi tải lịch trình:', error);
            showErrorMessage(error);
        });
}

// Update schedules UI
function updateSchedulesUI(schedules) {
    const scheduleContainer = document.getElementById('schedule-container');
    if (!scheduleContainer) return;

    scheduleContainer.innerHTML = `
        <h5 class="card-title text-success">📅 Lịch trình tự động</h5>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Thiết bị</th>
                    <th>Hành động</th>
                    <th>Thời gian</th>
                    <th>Ngày</th>
                </tr>
            </thead>
            <tbody>
                ${schedules.length === 0 ? '<tr><td colspan="4" class="text-center text-muted">Không có lịch trình.</td></tr>' : 
                schedules.map(schedule => `
                    <tr>
                        <td>${schedule.device_name}</td>
                        <td>${schedule.action == 1 ? 'Bật' : 'Tắt'}</td>
                        <td>${schedule.time}</td>
                        <td>${schedule.days}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
}

// Load alerts from API
function loadAlerts(gardenId) {
    fetch(`http://localhost/SmartGarden/backend-api/routes/sensor.php?action=get_alerts&garden_id=${gardenId}`)
        .then(async res => {
            if (!res.ok) {
                const text = await res.text();
                throw new Error(`Lỗi HTTP: ${res.status} - ${text}`);
            }
            const text = await res.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error(`Phản hồi không phải JSON: ${text}`);
            }
        })
        .then(data => {
            console.log('Cảnh báo:', data);
            if (data.success && data.data) {
                if (hasDataChanged(data.data, lastAlerts)) {
                    updateAlertsUI(data.data);
                    lastAlerts = data.data;
                }
            } else {
                showNoAlertsMessage();
            }
        })
        .catch(error => {
            console.error('Lỗi tải cảnh báo:', error);
            showErrorMessage(error);
        });
}

// Update alerts UI
function updateAlertsUI(alerts) {
    const alertContainer = document.getElementById('alert-container');
    if (!alertContainer) return;

    alertContainer.innerHTML = `
        <h5 class="card-title text-success">🚨 Cảnh báo</h5>
        <ul class="list-group">
            ${alerts.length === 0 ? '<li class="list-group-item text-center text-muted">Không có cảnh báo.</li>' : 
            alerts.map(alert => `
                <li class="list-group-item">${alert.message} - ${new Date(alert.timestamp).toLocaleString()}</li>
            `).join('')}
        </ul>
    `;
}

// Load microcontrollers from API
function loadMicrocontrollers(gardenId) {
    fetch(`http://localhost/SmartGarden/backend-api/routes/sensor.php?action=get_microcontrollers&garden_id=${gardenId}`)
        .then(async res => {
            if (!res.ok) {
                const text = await res.text();
                throw new Error(`Lỗi HTTP: ${res.status} - ${text}`);
            }
            const text = await res.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error(`Phản hồi không phải JSON: ${text}`);
            }
        })
        .then(data => {
            console.log('Trạng thái vi điều khiển:', data);
            if (data.success && data.data) {
                if (hasDataChanged(data.data, lastMicrocontrollers)) {
                    updateMicrocontrollersUI(data.data);
                    lastMicrocontrollers = data.data;
                }
            } else {
                showNoMicrocontrollersMessage();
            }
        })
        .catch(error => {
            console.error('Lỗi tải trạng thái vi điều khiển:', error);
            showErrorMessage(error);
        });
}

// Update microcontrollers UI
function updateMicrocontrollersUI(microcontrollers) {
    const microContainer = document.getElementById('microcontroller-container');
    if (!microContainer) return;

    microContainer.innerHTML = `
        <h5 class="card-title text-success">🖥️ Trạng thái vi điều khiển</h5>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Tên</th>
                    <th>Trạng thái</th>
                    <th>IP</th>
                    <th>Lần cuối hoạt động</th>
                </tr>
            </thead>
            <tbody>
                ${microcontrollers.length === 0 ? '<tr><td colspan="4" class="text-center text-muted">Không có vi điều khiển.</td></tr>' : 
                microcontrollers.map(mcu => `
                    <tr>
                        <td>${mcu.name}</td>
                        <td><span class="badge ${mcu.status === 'online' ? 'bg-success' : 'bg-danger'}">${mcu.status}</span></td>
                        <td>${mcu.ip_address || '--'}</td>
                        <td>${new Date(mcu.last_seen).toLocaleString()}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
}

// Show no data message for sensors
function showNoDataMessage() {
    document.querySelectorAll('.sensor-card .card-body').forEach(card => {
        card.innerHTML = '<p class="text-muted text-center">Không có dữ liệu.</p>';
    });
}

// Show no device status message
function showNoDeviceStatusMessage() {
    const statusContainer = document.getElementById('device-status-container');
    if (statusContainer) {
        statusContainer.innerHTML = '<p class="text-muted text-center">Không có dữ liệu trạng thái thiết bị.</p>';
    }
}

// Show no schedules message
function showNoSchedulesMessage() {
    const scheduleContainer = document.getElementById('schedule-container');
    if (scheduleContainer) {
        scheduleContainer.innerHTML = '<p class="text-muted text-center">Không có lịch trình.</p>';
    }
}

// Show no alerts message
function showNoAlertsMessage() {
    const alertContainer = document.getElementById('alert-container');
    if (alertContainer) {
        alertContainer.innerHTML = '<p class="text-muted text-center">Không có cảnh báo.</p>';
    }
}

// Show no microcontrollers message
function showNoMicrocontrollersMessage() {
    const microContainer = document.getElementById('microcontroller-container');
    if (microContainer) {
        microContainer.innerHTML = '<p class="text-muted text-center">Không có dữ liệu vi điều khiển.</p>';
    }
}

// Show error message
function showErrorMessage(error) {
    document.querySelectorAll('.sensor-card .card-body').forEach(card => {
        card.innerHTML = `<p class="text-danger text-center">Lỗi tải dữ liệu: ${error.message}</p>`;
    });
    showNoDeviceStatusMessage();
    showNoSchedulesMessage();
    showNoAlertsMessage();
    showNoMicrocontrollersMessage();
}

// Control relay devices
function controlRelay(device, status) {
    const gardenId = getGardenIdFromUrl();
    if (!gardenId) {
        alert('Vui lòng chọn một vườn!');
        return;
    }
    fetch('http://localhost/SmartGarden/backend-api/routes/sensor.php?action=update_relay', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `garden_id=${gardenId}&name=${device}&status=${status}`
    })
        .then(async res => {
            if (!res.ok) {
                const text = await res.text();
                throw new Error(`Lỗi HTTP: ${res.status} - ${text}`);
            }
            return res.json();
        })
        .then(data => {
            if (data.success) {
                alert(`Đã cập nhật ${device} → ${status ? 'Bật' : 'Tắt'}`);
                loadDeviceStatus(gardenId); // Tải lại ngay khi có thay đổi thủ công
            } else {
                alert('Cập nhật thất bại: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Lỗi cập nhật thiết bị:', error);
            alert('Không thể kết nối đến máy chủ!');
        });
}

// Save schedule settings
function saveSchedule() {
    const gardenId = getGardenIdFromUrl();
    if (!gardenId) {
        alert('Vui lòng chọn một vườn!');
        return;
    }
    const device = document.getElementById('deviceSelect').value;
    const action = document.getElementById('actionSelect').value;
    const time = document.getElementById('timeInput').value;
    
    const days = [];
    if (document.getElementById('monCheck').checked) days.push('Thứ 2');
    if (document.getElementById('tueCheck').checked) days.push('Thứ 3');
    if (document.getElementById('wedCheck').checked) days.push('Thứ 4');
    if (document.getElementById('thuCheck').checked) days.push('Thứ 5');
    if (document.getElementById('friCheck').checked) days.push('Thứ 6');
    if (document.getElementById('satCheck').checked) days.push('Thứ 7');
    if (document.getElementById('sunCheck').checked) days.push('Chủ nhật');
    
    if (!time || days.length === 0) {
        alert('Vui lòng chọn thời gian và ít nhất một ngày!');
        return;
    }

    fetch('http://localhost/SmartGarden/backend-api/routes/sensor.php?action=save_schedule', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `garden_id=${gardenId}&device=${device}&action=${action}&time=${time}&days=${days.join(',')}`
    })
        .then(async res => {
            if (!res.ok) {
                const text = await res.text();
                throw new Error(`Lỗi HTTP: ${res.status} - ${text}`);
            }
            return res.json();
        })
        .then(data => {
            if (data.success) {
                alert(`Đã lưu lịch: ${action === '1' ? 'Bật' : 'Tắt'} ${device} lúc ${time} vào các ngày: ${days.join(', ')}`);
                loadSchedules(gardenId); // Tải lại ngay khi có thay đổi thủ công
                const modal = bootstrap.Modal.getInstance(document.getElementById('scheduleModal'));
                modal.hide();
            } else {
                alert('Lưu lịch thất bại: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Lỗi lưu lịch:', error);
            alert('Không thể kết nối đến máy chủ!');
        });
}

// Check API availability
function checkAPI(gardenId) {
    if (!gardenId) return;
    fetch(`http://localhost/SmartGarden/backend-api/routes/sensor.php?action=latest&garden_id=${gardenId}`)
        .then(async res => {
            if (!res.ok) {
                const text = await res.text();
                console.error('API không khả dụng:', text);
                showErrorMessage(new Error('Không thể kết nối đến máy chủ'));
            }
        })
        .catch(error => {
            console.error('Lỗi kiểm tra API:', error);
            showErrorMessage(error);
        });
}

// Initialize when page loads
window.onload = function() {
    setActiveNavLink();
    const gardenId = getGardenIdFromUrl();
    if (!gardenId) {
        showErrorMessage(new Error('Vui lòng chọn một vườn'));
        window.location.href = 'garden.html';
        return;
    }
    loadGardenName(gardenId);
    checkAPI(gardenId);
    loadGardenData(gardenId);
    setInterval(() => loadGardenData(gardenId), 30000); // Tăng lên 30 giây
};
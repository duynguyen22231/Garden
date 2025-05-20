/* Set active sidebar link dynamically */
function setActiveNavLink() {
    const navLinks = document.querySelectorAll('.sidebar .nav-link');
    const currentPage = window.location.pathname.split('/').pop() || 'home.html';

    navLinks.forEach(link => {
        link.classList.remove('active');
        if (link.getAttribute('href') === currentPage) {
            link.classList.add('active');
        }
    });
}

// Load sensor data from API
function loadSensorData() {
    fetch('http://localhost/SmartGarden/backend-api/routes/sensor.php?action=latest')
        .then(res => {
            if (!res.ok) throw new Error(`Lỗi HTTP: ${res.status} - ${res.statusText}`);
            return res.json();
        })
        .then(data => {
            console.log('Dữ liệu cảm biến:', data);
            if (data && !data.message) {
                updateSensorUI(data);
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
function loadDeviceStatus() {
    fetch('http://localhost/SmartGarden/backend-api/routes/sensor.php?action=get_status')
        .then(res => {
            if (!res.ok) throw new Error(`Lỗi HTTP: ${res.status} - ${res.statusText}`);
            return res.json();
        })
        .then(data => {
            console.log('Trạng thái thiết bị:', data);
            if (data.success && data.data) {
                updateDeviceStatusUI(data.data);
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
            ${devices.map(device => `
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
function loadSchedules() {
    fetch('http://localhost/SmartGarden/backend-api/routes/sensor.php?action=get_schedules')
        .then(res => {
            if (!res.ok) throw new Error(`Lỗi HTTP: ${res.status} - ${res.statusText}`);
            return res.json();
        })
        .then(data => {
            console.log('Danh sách lịch trình:', data);
            if (data.success && data.data) {
                updateSchedulesUI(data.data);
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
                ${schedules.map(schedule => `
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
function loadAlerts() {
    fetch('http://localhost/SmartGarden/backend-api/routes/sensor.php?action=get_alerts')
        .then(res => {
            if (!res.ok) throw new Error(`Lỗi HTTP: ${res.status} - ${res.statusText}`);
            return res.json();
        })
        .then(data => {
            console.log('Cảnh báo:', data);
            if (data.success && data.data) {
                updateAlertsUI(data.data);
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
            ${alerts.map(alert => `
                <li class="list-group-item">${alert.message} - ${new Date(alert.timestamp).toLocaleString()}</li>
            `).join('')}
        </ul>
    `;
}

// Load microcontrollers from API
function loadMicrocontrollers() {
    fetch('http://localhost/SmartGarden/backend-api/routes/sensor.php?action=get_microcontrollers')
        .then(res => {
            if (!res.ok) throw new Error(`Lỗi HTTP: ${res.status} - ${res.statusText}`);
            return res.json();
        })
        .then(data => {
            console.log('Trạng thái vi điều khiển:', data);
            if (data.success && data.data) {
                updateMicrocontrollersUI(data.data);
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
                ${microcontrollers.map(mcu => `
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
}

// Control relay devices
function controlRelay(device, status) {
    const garden_id = 1; // Thay bằng garden_id thực tế
    fetch('http://localhost/SmartGarden/backend-api/routes/sensor.php?action=update_relay', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `garden_id=${garden_id}&name=${device}&status=${status}`
    })
        .then(res => {
            if (!res.ok) throw new Error('Lỗi cập nhật');
            return res.json();
        })
        .then(data => {
            if (data.success) {
                alert(`Đã cập nhật ${device} → ${status ? 'Bật' : 'Tắt'}`);
                loadDeviceStatus();
            } else {
                alert('Cập nhật thất bại: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Lỗi:', error);
            alert('Không thể kết nối đến máy chủ!');
        });
}

// Save schedule settings
function saveSchedule() {
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
        body: `device=${device}&action=${action}&time=${time}&days=${days.join(',')}`
    })
        .then(res => {
            if (!res.ok) throw new Error('Lỗi lưu lịch');
            return res.json();
        })
        .then(data => {
            if (data.success) {
                alert(`Đã lưu lịch: ${action === '1' ? 'Bật' : 'Tắt'} ${device} lúc ${time} vào các ngày: ${days.join(', ')}`);
                loadSchedules();
                const modal = bootstrap.Modal.getInstance(document.getElementById('scheduleModal'));
                modal.hide();
            } else {
                alert('Lưu lịch thất bại: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Lỗi:', error);
            alert('Không thể kết nối đến máy chủ!');
        });
}

// Check API availability
function checkAPI() {
    fetch('http://localhost/SmartGarden/backend-api/routes/sensor.php?action=latest')
        .then(res => {
            if (!res.ok) {
                console.error('API không khả dụng');
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
    checkAPI();
    loadSensorData();
    loadDeviceStatus();
    loadSchedules();
    loadAlerts();
    loadMicrocontrollers();
    setInterval(() => {
        loadSensorData();
        loadDeviceStatus();
        loadSchedules();
        loadAlerts();
        loadMicrocontrollers();
    }, 15000);
};
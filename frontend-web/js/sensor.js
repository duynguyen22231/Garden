/* Set active sidebar link dynamically */
function setActiveNavLink() {
    try {
        const navLinks = document.querySelectorAll('.sidebar .nav-link');
        const currentPage = window.location.pathname.split('/').pop() || 'sensor.html';
        console.log('Set active nav link for page:', currentPage);

        navLinks.forEach(link => {
            link.classList.remove('active');
            if (link.getAttribute('href') === currentPage) {
                link.classList.add('active');
            }
        });
    } catch (error) {
        console.error('Lỗi setActiveNavLink:', error);
    }
}

// Get token from localStorage
function getToken() {
    const token = localStorage.getItem('accessToken');
    console.log('Token:', token ? 'Có token' : 'Không có token');
    if (!token) {
        alert("Vui lòng đăng nhập lại!");
        window.location.href = "/SmartGarden/frontend-web/pages/login.html";
        return null;
    }
    return token;
}

// Get garden_id from URL
function getGardenIdFromUrl() {
    const urlParams = new URLSearchParams(window.location.search);
    const gardenId = urlParams.get('garden_id') || '';
    console.log('gardenId from URL:', gardenId);
    return gardenId;
}

// Load garden name and update title
function loadGardenName(gardenId) {
    if (!gardenId) {
        console.error('Vui lòng chọn một vườn');
        showErrorMessage(new Error('Vui lòng chọn một vườn'));
        window.location.href = 'garden.html';
        return;
    }
    const token = getToken();
    if (!token) return;

    fetch('http://localhost/SmartGarden/backend-api/routes/garden.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'Authorization': `Bearer ${token}`
        },
        body: `action=get_garden_by_id&id=${gardenId}`
    })
        .then(async res => {
            if (!res.ok) {
                if (res.status === 401) {
                    alert('Token không hợp lệ. Vui lòng đăng nhập lại!');
                    window.location.href = '/SmartGarden/frontend-web/pages/login.html';
                    return;
                }
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
            console.log('Dữ liệu vườn:', data);
            const titleElement = document.getElementById('garden-title');
            if (titleElement && data.success && data.data) {
                titleElement.textContent = `🌱 Giám Sát & Điều Khiển - ${data.data.garden_names || 'Vườn không tên'}`;
            } else {
                console.warn('Không tìm thấy garden-title hoặc dữ liệu vườn:', data);
                showErrorMessage(new Error('Không thể tải tên vườn: ' + (data.message || 'Lỗi không xác định')));
                window.location.href = 'garden.html';
            }
        })
        .catch(error => {
            console.error('Lỗi tải tên vườn:', error);
            showErrorMessage(error);
            window.location.href = '/SmartGarden/frontend-web/pages/garden.html';
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
        console.error('Vui lòng chọn một vườn');
        showErrorMessage(new Error('Vui lòng chọn một vườn'));
        window.location.href = 'garden.html';
        return;
    }
    
    console.log('Bắt đầu tải dữ liệu cho gardenId:', gardenId);
    try {
        loadSensorData(gardenId);
        loadDeviceStatus(gardenId);
        loadSchedules(gardenId);
        loadAlerts(gardenId);
        loadMicrocontrollers(gardenId);
    } catch (error) {
        console.error('Lỗi loadGardenData:', error);
    }
}

// Load sensor data from API
function loadSensorData(gardenId) {
    const token = getToken();
    if (!token) return;

    fetch(`http://localhost/SmartGarden/backend-api/routes/sensor.php?action=latest&garden_id=${gardenId}`, {
        headers: { 'Authorization': `Bearer ${token}` }
    })
        .then(async res => {
            if (!res.ok) {
                if (res.status === 401) {
                    alert('Token không hợp lệ. Vui lòng đăng nhập lại!');
                    window.location.href = '/SmartGarden/frontend-web/pages/login.html';
                    return;
                }
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
            if (data && data.success && data.data) {
                if (hasDataChanged(data.data, lastSensorData)) {
                    updateSensorUI(data.data);
                    lastSensorData = data.data;
                } else {
                    console.log('Dữ liệu cảm biến không thay đổi, không cập nhật UI');
                }
            } else {
                console.warn('Không có dữ liệu cảm biến hợp lệ');
                showNoDataMessage();
            }
        })
        .catch(error => {
            console.error('Lỗi tải dữ liệu cảm biến:', error);
            showErrorMessage(error);
        });
}


function updateSensorUI(sensorData) {
    try {
        console.log('Cập nhật UI cảm biến:', sensorData);
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
            console.log(`Kiểm tra phần tử ${field.id}:`, element, `Thời gian ${field.timeId}:`, timeElement);
            if (element && timeElement) {
                element.textContent = (field.value !== null && field.value !== undefined ? field.value : '--') + field.unit;
                if (field.class) {
                    element.className = 'card-text sensor-value ' + field.class;
                }
                timeElement.textContent = timeString;
            } else {
                console.error(`Không tìm thấy phần tử: ${field.id} hoặc ${field.timeId}`);
            }
        });
    } catch (error) {
        console.error('Lỗi updateSensorUI:', error);
    }
}

// Load device status from API
function loadDeviceStatus(gardenId) {
    const token = getToken();
    if (!token) return;

    fetch(`http://localhost/SmartGarden/backend-api/routes/sensor.php?action=get_status&garden_id=${gardenId}`, {
        headers: { 'Authorization': `Bearer ${token}` }
    })
        .then(async res => {
            if (!res.ok) {
                if (res.status === 401) {
                    alert('Token không hợp lệ. Vui lòng đăng nhập lại!');
                    window.location.href = '/SmartGarden/frontend-web/pages/login.html';
                    return;
                }
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
                } else {
                    console.log('Trạng thái thiết bị không thay đổi, không cập nhật UI');
                }
            } else {
                console.warn('Không có dữ liệu trạng thái thiết bị');
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
    try {
        console.log('Cập nhật UI trạng thái thiết bị:', devices);
        const statusContainer = document.getElementById('device-status-container');
        if (!statusContainer) {
            console.error('Không tìm thấy phần tử #device-status-container');
            return;
        }

        const fixedDevices = ['den', 'quat', 'bom'];
        const deviceMap = {};

        devices.forEach(device => {
            if (fixedDevices.includes(device.device_name)) {
                deviceMap[device.device_name] = device;
                console.log(`Thiết bị được lọc: ${device.device_name}, Trạng thái: ${device.status}`);
            }
        });

        let deviceRow = statusContainer.querySelector('.row.g-3');
        if (!deviceRow) {
            console.log('Tạo mới row cho trạng thái thiết bị');
            statusContainer.innerHTML = `
                <h5 class="card-title text-success">🔌 Trạng thái thiết bị</h5>
                <div class="row g-3"></div>
            `;
            deviceRow = statusContainer.querySelector('.row.g-3');
        }

        fixedDevices.forEach(deviceName => {
            const device = deviceMap[deviceName] || { device_name: deviceName, status: 0, last_updated: new Date().toISOString() };
            let deviceCard = deviceRow.querySelector(`[data-device="${deviceName}"]`);

            if (!deviceCard) {
                console.log(`Tạo mới card cho ${deviceName}`);
                deviceCard = document.createElement('div');
                deviceCard.className = 'col-4';
                deviceCard.setAttribute('data-device', deviceName);
                deviceCard.innerHTML = `
                    <div class="card h-100 text-center sensor-card">
                        <div class="card-body">
                            <i class="bi bi-gear-fill"></i>
                            <h6 class="card-title">${deviceName.charAt(0).toUpperCase() + deviceName.slice(1)}</h6>
                            <p class="card-text sensor-value"></p>
                            <p class="text-muted small">Cập nhật: <span class="time"></span></p>
                        </div>
                    </div>
                `;
                deviceRow.appendChild(deviceCard);
            }

            const icon = deviceCard.querySelector('i');
            const statusText = deviceCard.querySelector('.sensor-value');
            const timeSpan = deviceCard.querySelector('.time');
            console.log(`Cập nhật ${deviceName}: Icon=${!!icon}, StatusText=${!!statusText}, Time=${!!timeSpan}`);

            if (icon && statusText && timeSpan) {
                icon.className = `bi bi-gear-fill ${device.status ? 'text-success' : 'text-danger'}`;
                statusText.textContent = device.status ? 'Bật' : 'Tắt';
                timeSpan.textContent = new Date(device.last_updated).toLocaleTimeString();
            } else {
                console.error(`Không tìm thấy phần tử con trong card ${deviceName}`);
            }
        });

        deviceRow.querySelectorAll('.col-4').forEach(col => {
            const deviceName = col.getAttribute('data-device');
            if (!fixedDevices.includes(deviceName)) {
                console.log(`Xóa thiết bị không cần thiết: ${deviceName}`);
                col.remove();
            }
        });
    } catch (error) {
        console.error('Lỗi updateDeviceStatusUI:', error);
    }
}

function loadSchedules(gardenId) {
    const token = getToken();
    if (!token) return;

    fetch(`http://localhost/SmartGarden/backend-api/routes/sensor.php?action=get_schedules&garden_id=${gardenId}`, {
        headers: { 'Authorization': `Bearer ${token}` }
    })
        .then(async res => {
            if (!res.ok) {
                if (res.status === 401) {
                    alert('Token không hợp lệ. Vui lòng đăng nhập lại!');
                    window.location.href = '/SmartGarden/frontend-web/pages/login.html';
                    return;
                }
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
                } else {
                    console.log('Lịch trình không thay đổi, không cập nhật UI');
                }
            } else {
                console.warn('Không có dữ liệu lịch trình');
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
    try {
        console.log('Cập nhật UI lịch trình:', schedules);
        const scheduleContainer = document.getElementById('schedule-container');
        if (!scheduleContainer) {
            console.error('Không tìm thấy phần tử #schedule-container');
            return;
        }

        const dayMap = {
            '0': 'Chủ nhật',
            '1': 'Thứ 2',
            '2': 'Thứ 3',
            '3': 'Thứ 4',
            '4': 'Thứ 5',
            '5': 'Thứ 6',
            '6': 'Thứ 7'
        };

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
                    schedules.map(schedule => {
                        const days = schedule.days ? schedule.days.split(',').map(day => dayMap[day] || day).join(', ') : '--';
                        console.log(`Render lịch: ${schedule.device_name}, Ngày: ${days}`);
                        return `
                        <tr>
                            <td>${schedule.device_name ? schedule.device_name.charAt(0).toUpperCase() + schedule.device_name.slice(1) : '--'}</td>
                            <td>${schedule.action == 1 ? 'Bật' : 'Tắt'}</td>
                            <td>${schedule.time || '--'}</td>
                            <td>${days}</td>
                        </tr>
                    `}).join('')}
                </tbody>
            </table>
        `;
    } catch (error) {
        console.error('Lỗi updateSchedulesUI:', error);
    }
}

// Load alerts from API
function loadAlerts(gardenId) {
    const token = getToken();
    if (!token) return;

    fetch(`http://localhost/SmartGarden/backend-api/routes/sensor.php?action=get_alerts&garden_id=${gardenId}`, {
        headers: { 'Authorization': `Bearer ${token}` }
    })
        .then(async res => {
            if (!res.ok) {
                if (res.status === 401) {
                    alert('Token không hợp lệ. Vui lòng đăng nhập lại!');
                    window.location.href = '/SmartGarden/frontend-web/pages/login.html';
                    return;
                }
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
                } else {
                    console.log('Cảnh báo không thay đổi, không cập nhật UI');
                }
            } else {
                console.warn('Không có dữ liệu cảnh báo');
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
    try {
        console.log('Cập nhật UI cảnh báo:', alerts);
        const alertContainer = document.getElementById('alert-container');
        if (!alertContainer) {
            console.error('Không tìm thấy phần tử #alert-container');
            return;
        }

        alertContainer.innerHTML = `
            <h5 class="card-title text-success">🚨 Cảnh báo</h5>
            <ul class="list-group">
                ${alerts.length === 0 ? '<li class="list-group-item text-center text-muted">Không có cảnh báo.</li>' : 
                alerts.map(alert => {
                    console.log(`Render cảnh báo: ${alert.sensor_id}`);
                    return `
                        <li class="list-group-item">${alert.message || '--'} - Cảm biến: ${alert.sensor_id || '--'} - ${alert.timestamp ? new Date(alert.timestamp).toLocaleString() : '--'}</li>
                    `;
                }).join('')}
            </ul>
        `;
    } catch (error) {
        console.error('Lỗi updateAlertsUI:', error);
    }
}

// Load microcontrollers from API
function loadMicrocontrollers(gardenId) {
    const token = getToken();
    if (!token) return;

    fetch(`http://localhost/SmartGarden/backend-api/routes/sensor.php?action=get_microcontrollers&garden_id=${gardenId}`, {
        headers: { 'Authorization': `Bearer ${token}` }
    })
        .then(async res => {
            if (!res.ok) {
                if (res.status === 401) {
                    alert('Token không hợp lệ. Vui lòng đăng nhập lại!');
                    window.location.href = '/SmartGarden/frontend-web/pages/login.html';
                    return;
                }
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
                } else {
                    console.log('Vi điều khiển không thay đổi, không cập nhật UI');
                }
            } else {
                console.warn('Không có dữ liệu vi điều khiển');
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
    try {
        console.log('Cập nhật UI microcontrollers:', microcontrollers);
        const microContainer = document.getElementById('microcontroller-container');
        if (!microContainer) {
            console.error('Không tìm thấy phần tử #microcontroller-container');
            return;
        }

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
                    microcontrollers.map(mcu => {
                        console.log(`Render vi điều khiển: ${mcu.name}`);
                        return `
                        <tr>
                            <td>${mcu.name || '--'}</td>
                            <td><span class="badge ${mcu.status === 'online' ? 'bg-success' : 'bg-danger'}">${mcu.status || '--'}</span></td>
                            <td>${mcu.ip_address || '--'}</td>
                            <td>${mcu.last_seen ? new Date(mcu.last_seen).toLocaleString() : '--'}</td>
                        </tr>
                    `}).join('')}
                </tbody>
            </table>
        `;
    } catch (error) {
        console.error('Lỗi updateMicrocontrollersUI:', error);
    }
}

// Show no data message for sensors
function showNoDataMessage() {
    try {
        console.log('Hiển thị thông báo không có dữ liệu cảm biến');
        document.querySelectorAll('.sensor-card .card-body').forEach(card => {
            card.innerHTML = '<p class="text-muted text-center">Không có dữ liệu.</p>';
        });
    } catch (error) {
        console.error('Lỗi showNoDataMessage:', error);
    }
}

// Show no device status message
function showNoDeviceStatusMessage() {
    try {
        console.log('Hiển thị thông báo không có trạng thái thiết bị');
        const statusContainer = document.getElementById('device-status-container');
        if (statusContainer) {
            statusContainer.innerHTML = `
                <h5 class="card-title text-success">🔌 Trạng thái thiết bị</h5>
                <p class="text-muted text-center">Không có dữ liệu trạng thái thiết bị.</p>
            `;
        } else {
            console.error('Không tìm thấy #device-status-container');
        }
    } catch (error) {
        console.error('Lỗi showNoDeviceStatusMessage:', error);
    }
}

// Show no schedules message
function showNoSchedulesMessage() {
    try {
        console.log('Hiển thị thông báo không có lịch trình');
        const scheduleContainer = document.getElementById('schedule-container');
        if (scheduleContainer) {
            scheduleContainer.innerHTML = `
                <h5 class="card-title text-success">📅 Lịch trình tự động</h5>
                <p class="text-muted text-center">Không có lịch trình.</p>
            `;
        } else {
            console.error('Không tìm thấy #schedule-container');
        }
    } catch (error) {
        console.error('Lỗi showNoSchedulesMessage:', error);
    }
}

// Show no alerts message
function showNoAlertsMessage() {
    try {
        console.log('Hiển thị thông báo không có cảnh báo');
        const alertContainer = document.getElementById('alert-container');
        if (alertContainer) {
            alertContainer.innerHTML = `
                <h5 class="card-title text-success">🚨 Cảnh báo</h5>
                <p class="text-muted text-center">Không có cảnh báo.</p>
            `;
        } else {
            console.error('Không tìm thấy #alert-container');
        }
    } catch (error) {
        console.error('Lỗi showNoAlertsMessage:', error);
    }
}

// Show no microcontrollers message
function showNoMicrocontrollersMessage() {
    try {
        console.log('Hiển thị thông báo không có vi điều khiển');
        const microContainer = document.getElementById('microcontroller-container');
        if (microContainer) {
            microContainer.innerHTML = `
                <h5 class="card-title text-success">🖥️ Trạng thái vi điều khiển</h5>
                <p class="text-muted text-center">Không có dữ liệu vi điều khiển.</p>
            `;
        } else {
            console.error('Không tìm thấy #microcontroller-container');
        }
    } catch (error) {
        console.error('Lỗi showNoMicrocontrollersMessage:', error);
    }
}

// Show error message
function showErrorMessage(error) {
    try {
        console.error('Hiển thị lỗi:', error);
        document.querySelectorAll('.sensor-card .card-body').forEach(card => {
            card.innerHTML = `<p class="text-danger text-center">Lỗi tải dữ liệu: ${error.message}</p>`;
        });
        showNoDeviceStatusMessage();
        showNoSchedulesMessage();
        showNoAlertsMessage();
        showNoMicrocontrollersMessage();
    } catch (err) {
        console.error('Lỗi showErrorMessage:', err);
    }
}

// Toggle device status
function toggleDevice(device, isChecked) {
    const gardenId = getGardenIdFromUrl();
    if (!gardenId) {
        alert('Vui lòng chọn một vườn!');
        return;
    }
    const token = getToken();
    if (!token) return;

    const status = isChecked ? 1 : 0;
    fetch('http://localhost/SmartGarden/backend-api/routes/sensor.php?action=update_relay', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'Authorization': `Bearer ${token}`
        },
        body: `garden_id=${gardenId}&name=${device}&status=${status}`
    })
    .then(async res => {
        if (!res.ok) {
            if (res.status === 401) {
                alert('Token không hợp lệ. Vui lòng đăng nhập lại!');
                window.location.href = '/SmartGarden/frontend-web/pages/login.html';
                return;
            }
            const text = await res.text();
            throw new Error(`Lỗi HTTP: ${res.status} - ${text}`);
        }
        return res.json();
    })
    .then(data => {
        console.log('Kết quả điều khiển:', data);
        if (data.success) {
           
            updateSingleDeviceStatus(device, status);
            // Cập nhật toggle state trên UI (nếu cần)
            document.getElementById(`${device}Toggle`).checked = isChecked;
        } else {
            alert('Cập nhật thất bại: ' + (data.message || 'Lỗi không xác định'));
            document.getElementById(`${device}Toggle`).checked = !isChecked; 
        }
    })
    .catch(error => {
        console.error('Lỗi điều khiển thiết bị:', error);
        alert('Không thể kết nối đến máy chủ!');
        document.getElementById(`${device}Toggle`).checked = !isChecked; 
    });
}

// Update status of a single device
function updateSingleDeviceStatus(deviceName, status) {
    try {
        console.log(`Cập nhật trạng thái đơn lẻ cho ${deviceName}: ${status}`);
        const statusContainer = document.getElementById('device-status-container');
        if (!statusContainer) {
            console.error('Không tìm thấy #device-status-container');
            return;
        }

        let deviceCard = statusContainer.querySelector(`[data-device="${deviceName}"]`);
        if (deviceCard) {
            const icon = deviceCard.querySelector('i');
            const statusText = deviceCard.querySelector('.sensor-value');
            const timeSpan = deviceCard.querySelector('.time');

            if (icon && statusText && timeSpan) {
                icon.className = `bi bi-gear-fill ${status ? 'text-success' : 'text-danger'}`;
                statusText.textContent = status ? 'Bật' : 'Tắt';
                timeSpan.textContent = new Date().toLocaleTimeString();
                console.log(`Đã cập nhật UI cho ${deviceName}`);

                lastDeviceStatus = lastDeviceStatus ? lastDeviceStatus.map(device => 
                    device.device_name === deviceName 
                        ? { ...device, status, last_updated: new Date().toISOString() }
                        : device
                ) : [{ device_name: deviceName, status, last_updated: new Date().toISOString() }];
            } else {
                console.error(`Không tìm thấy phần tử con trong card ${deviceName}`);
            }
        } else {
            console.warn(`Không tìm thấy card cho ${deviceName}, load lại trạng thái`);
            loadDeviceStatus(getGardenIdFromUrl());
        }
    } catch (error) {
        console.error('Lỗi updateSingleDeviceStatus:', error);
    }
}

// Save schedule settings
function saveSchedule() {
    const gardenId = getGardenIdFromUrl();
    if (!gardenId) {
        alert('Vui lòng chọn một vườn!');
        return;
    }
    const token = getToken();
    if (!token) return;

    const device = document.getElementById('deviceSelect').value;
    const action = document.getElementById('actionSelect').value;
    const time = document.getElementById('timeInput').value;
    
    const days = [];
    ['monCheck', 'tueCheck', 'wedCheck', 'thuCheck', 'friCheck', 'satCheck', 'sunCheck'].forEach((id, index) => {
        if (document.getElementById(id).checked) {
            days.push(index === 6 ? 0 : index + 1); // Map to 0-6 (Sun-Sat)
        }
    });
    
    if (!time || days.length === 0) {
        alert('Vui lòng chọn thời gian và ít nhất một ngày!');
        return;
    }

    console.log('Lưu lịch:', { device, action, time, days });
    fetch('http://localhost/SmartGarden/backend-api/routes/sensor.php?action=save_schedule', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'Authorization': `Bearer ${token}`
        },
        body: `garden_id=${gardenId}&device=${device}&action=${action}&time=${time}&days=${days.join(',')}`
    })
    .then(async res => {
        if (!res.ok) {
            if (res.status === 401) {
                alert('Token không hợp lệ. Vui lòng đăng nhập lại!');
                window.location.href = '/SmartGarden/frontend-web/pages/login.html';
                return;
            }
            const text = await res.text();
            throw new Error(`Lỗi HTTP: ${res.status} - ${text}`);
        }
        return res.json();
    })
    .then(data => {
        console.log('Kết quả lưu lịch:', data);
        if (data.success) {
            alert(`Đã lưu lịch: ${action === '1' ? 'Bật' : 'Tắt'} ${device} lúc ${time} vào các ngày: ${days.join(', ')}`);
            loadSchedules(gardenId);
            const modal = bootstrap.Modal.getInstance(document.getElementById('scheduleModal'));
            modal.hide();
        } else {
            alert('Lưu lịch thất bại: ' + (data.message || 'Lỗi không xác định'));
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
    const token = getToken();
    if (!token) return;

    fetch(`http://localhost/SmartGarden/backend-api/routes/sensor.php?action=latest&garden_id=${gardenId}`, {
        headers: { 'Authorization': `Bearer ${token}` }
    })
    .then(async res => {
        if (!res.ok) {
            if (res.status === 401) {
                alert('Token không hợp lệ. Vui lòng đăng nhập lại!');
                window.location.href = '/SmartGarden/frontend-web/pages/login.html';
                return;
            }
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
    try {
        console.log('Trang sensor.html đã tải');
        setActiveNavLink();
        const gardenId = getGardenIdFromUrl();
        if (!gardenId) {
            console.error('Không có garden_id, chuyển hướng đến garden.html');
            showErrorMessage(new Error('Vui lòng chọn một vườn'));
            window.location.href = '/SmartGarden/frontend-web/pages/garden.html';
            return;
        }
        loadGardenName(gardenId);
        checkAPI(gardenId);
        loadGardenData(gardenId);
        setInterval(() => {
            console.log('Tự động tải lại dữ liệu');
            loadGardenData(gardenId);
        }, 30000); 
    } catch (error) {
        console.error('Lỗi window.onload:', error);
    }
};

// Hàm đăng xuất
function logout() {
    try {
        console.log('Đăng xuất người dùng');
        localStorage.removeItem("accessToken");
        localStorage.removeItem("isAdmin");
        localStorage.removeItem("currentUserId");
        alert("Đăng xuất thành công!");
        window.location.href = "/SmartGarden/frontend-web/pages/login.html";
    } catch (error) {
        console.error('Lỗi logout:', error);
    }
}
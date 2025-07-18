/* Quản lý trạng thái vườn và MCU */
let cachedMcuId = null; // Lưu trữ mcu_id để giảm gọi API
let lastSensorData = null; // Lưu trữ dữ liệu cảm biến gần nhất
let lastSchedules = null; // Lưu trữ lịch trình gần nhất
let lastAlerts = null; // Lưu trữ cảnh báo gần nhất
let lastMicrocontrollers = null; // Lưu trữ vi điều khiển gần nhất
let lastDeviceStatus = null; // Lưu trữ trạng thái thiết bị gần nhất

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
    const gardenId = urlParams.get('garden_id');
    console.log('URL garden_id param:', gardenId);
    if (!gardenId || isNaN(gardenId) || parseInt(gardenId) <= 0) {
        console.error('Invalid or missing garden ID in URL');
        alert('Vui lòng chọn một vườn hợp lệ từ trang Vườn!');
        window.location.href = '/SmartGarden/frontend-web/pages/garden.html';
        return null;
    }
    return parseInt(gardenId);
}

// Gán garden_id vào vườn 1 hoặc vườn 2 bằng cách gọi API
async function assignGarden(gardenId) {
    try {
        if (!gardenId || isNaN(gardenId)) {
            throw new Error('ID vườn không hợp lệ');
        }

        // Gọi API để lấy số vườn
        const data = await apiRequest('sensor.php', 'get_garden_assignment', 'POST', { garden_id: gardenId });
        const gardenNumber = data.garden_number;
        if (!gardenNumber || ![1, 2].includes(gardenNumber)) {
            throw new Error(`Số vườn không hợp lệ: ${gardenNumber}`);
        }

        console.log(`garden_id=${gardenId} được gán làm Vườn ${gardenNumber}`);
        return gardenNumber;
    } catch (error) {
        console.error('Lỗi assignGarden:', error);
        // Thử gán số vườn mới nếu chưa có
        try {
            const data = await apiRequest('sensor.php', 'get_garden_number', 'POST', { garden_id: gardenId });
            const gardenNumber = data.garden_number;
            console.log(`Gán mới garden_id=${gardenId} làm Vườn ${gardenNumber}`);
            return gardenNumber;
        } catch (err) {
            console.error('Lỗi gán vườn mới:', err);
            showErrorMessage(err);
            return null;
        }
    }
}



// Get devices for a specific garden
async function getGardenDevices(gardenId) {
    try {
        const gardenNumber = await assignGarden(gardenId);
        if (!gardenNumber) {
            throw new Error('Không thể lấy số vườn');
        }
        const devices = {
            1: ['den1', 'quat1', 'van_tren'],
            2: ['den2', 'quat2', 'van_duoi']
        };
        const deviceList = devices[gardenNumber] || [];
        if (!deviceList.length) {
            console.warn(`Không tìm thấy thiết bị cho garden_id=${gardenId} (Vườn ${gardenNumber})`);
        }
        console.log(`Thiết bị cho garden_id=${gardenId} (Vườn ${gardenNumber}):`, deviceList);
        return deviceList;
    } catch (error) {
        console.error('Lỗi getGardenDevices:', error);
        showErrorMessage(error);
        return [];
    }
}

// Debounce function to limit API calls
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

// Handle API response and parse JSON safely
async function handleApiResponse(res, context) {
    const text = await res.text();
    try {
        const data = JSON.parse(text);
        if (!data.success) throw new Error(data.message || `Lỗi không xác định từ ${context}`);
        return data;
    } catch (e) {
        if (e instanceof SyntaxError) {
            throw new Error(`Phản hồi không phải JSON từ ${context}: ${text}`);
        }
        throw e;
    }
}

// Generic API request function with retry
async function apiRequest(endpoint, action, method = 'POST', data = {}, retries = 2) {
    const token = getToken();
    if (!token) return null;

    for (let attempt = 0; attempt <= retries; attempt++) {
        try {
            const options = {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`
                }
            };
            if (method === 'POST') {
                options.body = JSON.stringify({ action, ...data });
            }
            const res = await fetch(`http://192.168.1.123/SmartGarden/backend-api/routes/${endpoint}`, options);
            return await handleApiResponse(res, action);
        } catch (error) {
            console.error(`Lỗi API ${action} (Thử lần ${attempt + 1}/${retries + 1}):`, error);
            if (attempt === retries) throw error;
            await new Promise(resolve => setTimeout(resolve, 1000 * (attempt + 1)));
        }
    }
}

// Fetch mcu_id for a garden
async function getMcuId(gardenId) {
    if (!gardenId || isNaN(gardenId)) {
        console.error('Lỗi getMcuId: Thiếu hoặc garden ID không hợp lệ');
        return null;
    }
    if (cachedMcuId) {
        console.log('Sử dụng mcu_id từ cache:', cachedMcuId);
        return cachedMcuId;
    }
    try {
        const data = await apiRequest('sensor.php', 'get_microcontrollers', 'POST', { garden_id: gardenId });
        if (data.data && data.data.length > 0) {
            cachedMcuId = data.data[0].mcu_id;
            console.log('mcu_id:', cachedMcuId);
            return cachedMcuId;
        }
        throw new Error('Không tìm thấy vi điều khiển cho vườn này');
    } catch (error) {
        console.error('Lỗi lấy mcu_id:', error);
        showErrorMessage(error);
        return null;
    }
}

// Initialize MCU and devices
async function initializeMcu(gardenId) {
    try {
        const mcuId = await getMcuId(gardenId);
        if (!mcuId) {
            throw new Error('Không thể lấy mcu_id cho garden ID: ' + gardenId);
        }
        const data = await apiRequest('sensor.php', 'initialize_mcu_and_devices', 'POST', { mcu_id: mcuId });
        console.log('Khởi tạo MCU thành công:', data);
        return data;
    } catch (error) {
        console.error('Lỗi khởi tạo MCU:', error);
        showErrorMessage(error);
        return null;
    }
}

// Show error message
function showErrorMessage(error) {
    try {
        console.error('Hiển thị lỗi:', error);
        const alertContainer = document.getElementById('alert-container');
        if (alertContainer) {
            alertContainer.innerHTML = `
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    Lỗi: ${error.message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
        } else {
            console.error('Không tìm thấy #alert-container');
            alert(`Lỗi: ${error.message}`);
        }
    } catch (err) {
        console.error('Lỗi showErrorMessage:', err);
    }
}

// Load garden name and update title
async function loadGardenName(gardenId) {
    if (!gardenId || isNaN(gardenId)) {
        console.error('Vui lòng cung cấp garden ID hợp lệ');
        showErrorMessage(new Error('Vui lòng cung cấp ID vườn hợp lệ'));
        return;
    }
    try {
        const data = await apiRequest('sensor.php', 'get_garden_by_id', 'POST', { id: gardenId });
        const titleElement = document.getElementById('garden-title');
        if (titleElement && data.data) {
            const gardenNumber = await assignGarden(gardenId);
            titleElement.textContent = `🌱 Giám Sát & Điều Khiển - Vườn ${gardenId} (Vườn ${gardenNumber || 'Chưa gán'})`;
        } else {
            throw new Error('Không thể tải tên vườn');
        }
    } catch (error) {
        console.error('Lỗi tải tên vườn:', error);
        showErrorMessage(error);
        const titleElement = document.getElementById('garden-title');
        if (titleElement) {
            const gardenNumber = await assignGarden(gardenId);
            titleElement.textContent = `🌱 Giám Sát & Điều Khiển - Vườn ${gardenId} (Vườn ${gardenNumber || 'Chưa gán'})`;
        }
    }
}

// Load all data for a specific garden
async function loadGardenData(gardenId) {
    if (!gardenId || isNaN(gardenId)) {
        console.error('Vui lòng cung cấp ID vườn hợp lệ');
        showErrorMessage(new Error('Vui lòng cung cấp ID vườn hợp lệ'));
        showNoDataMessage();
        showNoSchedulesMessage();
        showNoAlertsMessage();
        showNoMicrocontrollersMessage();
        return;
    }
    
    console.log('Bắt đầu tải dữ liệu cho gardenId:', gardenId);
    try {
        await Promise.all([
            loadGardenName(gardenId),
            loadSensorData(gardenId),
            loadSchedules(gardenId),
            loadAlerts(gardenId),
            loadMicrocontrollers(gardenId),
            loadDeviceStatus(gardenId)
        ]);
    } catch (error) {
        console.error('Lỗi loadGardenData:', error);
        showErrorMessage(error);
    }
}

// Toggle auto mode
const toggleAutoMode = debounce(async (isChecked) => {
    const gardenId = getGardenIdFromUrl();
    if (!gardenId) {
        console.error('Lỗi toggleAutoMode: Thiếu garden ID');
        alert('Vui lòng cung cấp ID vườn!');
        return;
    }
    const mcuId = await getMcuId(gardenId);
    if (!mcuId) {
        console.error('Lỗi toggleAutoMode: Không lấy được mcu_id');
        alert('Không thể lấy thông tin vi điều khiển!');
        return;
    }
    try {
        await apiRequest('sensor.php', 'update_auto_mode', 'POST', {
            garden_id: gardenId,
            mcu_id: mcuId,
            auto_mode: isChecked ? 1 : 0
        });
        console.log(`Chế độ tự động: ${isChecked ? 'Bật' : 'Tắt'}`);
        const autoModeToggle = document.getElementById('autoModeToggle');
        if (autoModeToggle) autoModeToggle.checked = isChecked;
    } catch (error) {
        console.error('Lỗi toggleAutoMode:', error);
        alert(`Không thể cập nhật chế độ tự động: ${error.message}`);
        const autoModeToggle = document.getElementById('autoModeToggle');
        if (autoModeToggle) autoModeToggle.checked = !isChecked;
    }
}, 500);

// Toggle device with relay control
const toggleDevice = debounce(async (device, isChecked) => {
    const gardenId = getGardenIdFromUrl();
    if (!gardenId) {
        console.error('Lỗi toggleDevice: Thiếu garden ID');
        alert('Vui lòng chọn một vườn từ trang Vườn!');
        return;
    }
    const mcuId = await getMcuId(gardenId);
    if (!mcuId) {
        console.error('Lỗi toggleDevice: Không lấy được mcu_id');
        alert('Không thể lấy thông tin vi điều khiển!');
        return;
    }
    const status = isChecked ? 1 : 0;
    const deviceName = device;
    console.log('Gửi request update_relay:', { garden_id: gardenId, device_name: deviceName, status, mcu_id: mcuId });
    try {
        const response = await apiRequest('sensor.php', 'update_relay', 'POST', {
            garden_id: gardenId,
            device_name: deviceName,
            status: status,
            mcu_id: mcuId
        });
        console.log(`Điều khiển thiết bị ${deviceName} thành công: ${status ? 'Bật' : 'Tắt'}`);
        // Tải lại trạng thái thiết bị để đồng bộ
        await loadDeviceStatus(gardenId);
    } catch (error) {
        console.error(`Lỗi điều khiển thiết bị ${deviceName}:`, error);
        alert(`Không thể điều khiển thiết bị ${deviceName}: ${error.message}`);
        const toggle = document.getElementById(`${deviceName}Toggle`);
        if (toggle) toggle.checked = !isChecked; // Hoàn nguyên trạng thái nếu lỗi
    }
}, 500);

// Compare data to check for changes
function hasDataChanged(newData, oldData) {
    return JSON.stringify(newData) !== JSON.stringify(oldData);
}

// Load sensor data from API with retry
async function loadSensorData(gardenId, retryCount = 0, maxRetries = 3) {
    try {
        const data = await apiRequest('sensor.php', 'get_readings', 'POST', { garden_id: gardenId });
        if (data.data) {
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
    } catch (error) {
        console.error('Lỗi tải dữ liệu cảm biến (Thử lần ' + (retryCount + 1) + '/' + maxRetries + '):', error);
        if (retryCount < maxRetries) {
            await new Promise(resolve => setTimeout(resolve, 2000 * (retryCount + 1)));
            return loadSensorData(gardenId, retryCount + 1, maxRetries);
        }
        showErrorMessage(error);
    }
}

// Update sensor UI
function updateSensorUI(sensorData) {
    try {
        console.log('Cập nhật UI cảm biến:', sensorData);
        const container = document.getElementById('sensor-data');
        if (!container) {
            console.error('Không tìm thấy #sensor-data');
            return;
        }

        const now = new Date(sensorData.created_at || new Date());
        const timeString = now.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });

        const sensorFields = [
            { id: 'soil-moisture', value: sensorData.soil_moisture, unit: '%', timeId: 'soil-time' },
            { id: 'temperature', value: sensorData.temperature, unit: '°C', timeId: 'temp-time' },
            { id: 'humidity', value: sensorData.humidity, unit: '%', timeId: 'humi-time' },
            { id: 'light', value: sensorData.light, unit: 'lux', timeId: 'light-time' },
            { id: 'water-level', value: sensorData.water_level_cm, unit: 'cm', timeId: 'water-time' },
            { id: 'rain-status', value: (sensorData.is_raining === 1 || sensorData.is_raining === '1') ? 'Đang mưa' : 'Không mưa', unit: '', timeId: 'rain-time', class: (sensorData.is_raining === 1 || sensorData.is_raining === '1') ? 'text-info' : 'text-warning' },
            { id: 'salinity', value: sensorData.salinity, unit: 'ppm', timeId: 'salinity-time' }
        ];

        sensorFields.forEach(field => {
            const element = document.getElementById(field.id);
            const timeElement = document.getElementById(field.timeId);
            if (element && timeElement) {
                element.textContent = (field.value !== null && field.value !== undefined ? (typeof field.value === 'number' ? field.value.toFixed(1) : field.value) : '--') + field.unit;
                if (field.class) element.className = 'card-text sensor-value ' + field.class;
                timeElement.textContent = timeString;
            } else {
                console.warn(`Không tìm thấy phần tử: ${field.id} hoặc ${field.timeId}`);
            }
        });
    } catch (error) {
        console.error('Lỗi updateSensorUI:', error);
        showErrorMessage(error);
    }
}

// Load schedules from API
async function loadSchedules(gardenId) {
    try {
        const data = await apiRequest('sensor.php', 'get_schedules', 'POST', { garden_id: gardenId });
        if (data.data) {
            if (hasDataChanged(data.data, lastSchedules)) {
                updateSchedulesUI(data.data, gardenId);
                lastSchedules = data.data;
            } else {
                console.log('Lịch trình không thay đổi, không cập nhật UI');
            }
        } else {
            console.warn('Không có dữ liệu lịch trình');
            showNoSchedulesMessage();
        }
    } catch (error) {
        console.error('Lỗi tải lịch trình:', error);
        showErrorMessage(error);
    }
}

// Update schedules UI
async function updateSchedulesUI(schedules, gardenId) {
    try {
        console.log('Cập nhật UI lịch trình:', schedules);
        const scheduleContainer = document.getElementById('schedule-container');
        if (!scheduleContainer) {
            console.error('Không tìm thấy phần tử #schedule-container');
            return;
        }

        const gardenNumber = await assignGarden(gardenId);
        const deviceDisplayMap = {
            1: { 'den1': 'Đèn 1', 'quat1': 'Quạt 1', 'van_tren': 'Van (Vườn 1)' },
            2: { 'den2': 'Đèn 2', 'quat2': 'Quạt 2', 'van_duoi': 'Van (Vườn 2)' }
        };

        const dayMap = {
            '0': 'Chủ nhật',
            '1': 'Thứ 2',
            '2': 'Thứ 3',
            '3': 'Thứ 4',
            '4': 'Thứ 5',
            '5': 'Thứ 6',
            '6': 'Thứ 7'
        };

        const validDevices = await getGardenDevices(gardenId);
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
                        const deviceName = validDevices.includes(schedule.device_name) 
                            ? (deviceDisplayMap[gardenNumber][schedule.device_name] || schedule.device_name)
                            : `Thiết bị không xác định (${schedule.device_name})`;
                        const days = schedule.days ? schedule.days.split(',').map(day => dayMap[day.trim()] || day.trim()).join(', ') : '--';
                        return `
                        <tr>
                            <td>${deviceName}</td>
                            <td>${schedule.action === 'on' ? 'Bật' : 'Tắt'}</td>
                            <td>${schedule.time ? schedule.time.substring(0, 5) : '--'}</td>
                            <td>${days}</td>
                        </tr>
                    `;
                    }).join('')}
                </tbody>
            </table>
        `;
    } catch (error) {
        console.error('Lỗi updateSchedulesUI:', error);
        showErrorMessage(error);
    }
}

// Load alerts from API
async function loadAlerts(gardenId) {
    try {
        const data = await apiRequest('sensor.php', 'get_alerts', 'POST', { garden_id: gardenId });
        if (data.data) {
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
    } catch (error) {
        console.error('Lỗi tải cảnh báo:', error);
        showErrorMessage(error);
    }
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
                alerts.map(alert => `
                    <li class="list-group-item">${alert.message || '--'} - Độ nghiêm trọng: ${alert.severity || '--'} - ${alert.created_at ? new Date(alert.created_at).toLocaleString('vi-VN') : '--'}</li>
                `).join('')}
            </ul>
        `;
    } catch (error) {
        console.error('Lỗi updateAlertsUI:', error);
        showErrorMessage(error);
    }
}

// Load microcontrollers from API
async function loadMicrocontrollers(gardenId) {
    try {
        const data = await apiRequest('sensor.php', 'get_microcontrollers', 'POST', { garden_id: gardenId });
        if (data.data) {
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
    } catch (error) {
        console.error('Lỗi tải vi điều khiển:', error);
        showErrorMessage(error);
    }
}

// Update microcontrollers UI
function updateMicrocontrollersUI(microcontrollers) {
    try {
        console.log('Cập nhật UI vi điều khiển:', microcontrollers);
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
                    microcontrollers.map(mcu => `
                        <tr>
                            <td>${mcu.mcu_id || '--'}</td>
                            <td><span class="badge ${mcu.status === 'online' ? 'bg-success' : 'bg-danger'}">${mcu.status || '--'}</span></td>
                            <td>${mcu.ip_address || '--'}</td>
                            <td>${mcu.updated_at ? new Date(mcu.updated_at).toLocaleString('vi-VN') : '--'}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    } catch (error) {
        console.error('Lỗi updateMicrocontrollersUI:', error);
        showErrorMessage(error);
    }
}

// Load device status from API
async function loadDeviceStatus(gardenId) {
    try {
        const mcuId = await getMcuId(gardenId);
        if (!mcuId) {
            console.warn('Không thể tải trạng thái thiết bị: Thiếu mcu_id');
            await updateDeviceStatusUI([], gardenId);
            return;
        }
        const data = await apiRequest('sensor.php', 'get_status', 'POST', { garden_id: gardenId, mcu_id: mcuId });
        if (data.data) {
            if (hasDataChanged(data.data, lastDeviceStatus)) {
                await updateDeviceStatusUI(data.data, gardenId);
                lastDeviceStatus = data.data;
            } else {
                console.log('Trạng thái thiết bị không thay đổi, không cập nhật UI');
            }
        } else {
            console.warn('Không có dữ liệu trạng thái thiết bị');
            await updateDeviceStatusUI([], gardenId);
        }
    } catch (error) {
        console.error('Lỗi loadDeviceStatus:', error);
        showErrorMessage(error);
        await updateDeviceStatusUI([], gardenId);
    }
}

// Update device status UI
async function updateDeviceStatusUI(statusData, gardenId) {
    try {
        console.log('Cập nhật UI trạng thái thiết bị:', statusData);
        const deviceControls = document.getElementById('device-control-list');
        if (!deviceControls) {
            console.error('Không tìm thấy #device-control-list');
            showErrorMessage(new Error('Không tìm thấy container danh sách thiết bị'));
            return;
        }

        const devices = await getGardenDevices(gardenId);
        const deviceDisplayMap = {
            'den1': 'Đèn 1', 'quat1': 'Quạt 1', 'van_tren': 'Van (Vườn 1)',
            'den2': 'Đèn 2', 'quat2': 'Quạt 2', 'van_duoi': 'Van (Vườn 2)'
        };

        deviceControls.innerHTML = devices.length > 0
            ? devices.map(device => {
                const deviceStatus = statusData.find(item => item.device_name === device) || { status: 0 };
                const isChecked = deviceStatus.status === 1;
                const displayName = deviceDisplayMap[device] || device;
                const iconClass = device.includes('den') ? 'bi-lightbulb' : device.includes('quat') ? 'bi-fan' : 'bi-droplet';
                return `
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="bi ${iconClass} me-2"></i>${displayName}</span>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="${device}Toggle" ${isChecked ? 'checked' : ''}>
                            <label class="form-check-label" for="${device}Toggle">${isChecked ? 'Bật' : 'Tắt'}</label>
                        </div>
                    </li>
                `;
            }).join('')
            : '<li class="list-group-item text-center text-muted">Không có thiết bị.</li>';

        // Thêm sự kiện cho các toggle switch
        devices.forEach(device => {
            const toggle = document.getElementById(`${device}Toggle`);
            if (toggle) {
                toggle.addEventListener('change', (e) => {
                    toggleDevice(device, e.target.checked);
                });
            }
        });

        // Cập nhật options trong modal lịch trình
        const deviceSelect = document.getElementById('deviceSelect');
        if (deviceSelect) {
            deviceSelect.innerHTML = devices.length > 0
                ? `<option value="">Chọn thiết bị</option>` + devices.map(device => `
                    <option value="${device}">${deviceDisplayMap[device] || device}</option>
                `).join('')
                : '<option value="">Không có thiết bị</option>';
        } else {
            console.warn('Không tìm thấy #deviceSelect');
        }
    } catch (error) {
        console.error('Lỗi updateDeviceStatusUI:', error);
        showErrorMessage(error);
    }
}

// Show no data message
function showNoDataMessage() {
    const container = document.getElementById('sensor-data');
    if (container) {
        container.innerHTML = '<p class="text-muted text-center">Không có dữ liệu cảm biến.</p>';
    }
}

// Show no schedules message
function showNoSchedulesMessage() {
    const container = document.getElementById('schedule-container');
    if (container) {
        container.innerHTML = '<p class="text-muted text-center">Không có lịch trình.</p>';
    }
}

// Show no alerts message
function showNoAlertsMessage() {
    const container = document.getElementById('alert-container');
    if (container) {
        container.innerHTML = '<p class="text-muted text-center">Không có cảnh báo.</p>';
    }
}

// Show no microcontrollers message
function showNoMicrocontrollersMessage() {
    const container = document.getElementById('microcontroller-container');
    if (container) {
        container.innerHTML = '<p class="text-muted text-center">Không có vi điều khiển.</p>';
    }
}

// Save schedule settings
async function saveSchedule() {
    const gardenId = getGardenIdFromUrl();
    if (!gardenId) {
        alert('Vui lòng chọn một vườn từ trang Vườn!');
        return;
    }
    const mcuId = await getMcuId(gardenId);
    if (!mcuId) {
        console.error('Lỗi saveSchedule: Không lấy được mcu_id');
        alert('Không thể lấy thông tin vi điều khiển!');
        return;
    }
    const deviceSelect = document.getElementById('deviceSelect');
    const actionSelect = document.getElementById('actionSelect');
    const timeInput = document.getElementById('timeInput');
    if (!deviceSelect || !actionSelect || !timeInput) {
        console.error('Lỗi saveSchedule: Form không đầy đủ');
        alert('Form lịch trình không đầy đủ!');
        return;
    }

    const validDevices = await getGardenDevices(gardenId);
    const device = deviceSelect.value;
    const action = actionSelect.value;
    const time = timeInput.value;

    if (!device || !validDevices.includes(device)) {
        alert('Vui lòng chọn một thiết bị hợp lệ!');
        console.error('Thiết bị không hợp lệ:', device);
        return;
    }
    if (!time) {
        alert('Vui lòng chọn thời gian!');
        return;
    }

    const days = [];
    ['monCheck', 'tueCheck', 'wedCheck', 'thuCheck', 'friCheck', 'satCheck', 'sunCheck'].forEach((id, index) => {
        const checkbox = document.getElementById(id);
        if (checkbox && checkbox.checked) {
            days.push(index === 6 ? 0 : index + 1);
        }
    });

    if (days.length === 0) {
        alert('Vui lòng chọn ít nhất một ngày!');
        return;
    }

    console.log('Lưu lịch:', { device_name: device, action, time, days, mcu_id: mcuId });
    try {
        await apiRequest('sensor.php', 'save_schedule', 'POST', {
            garden_id: gardenId,
            device_name: device,
            action: action,
            time: time,
            days: days.join(','),
            mcu_id: mcuId
        });
        const gardenNumber = await assignGarden(gardenId);
        const deviceDisplayMap = {
            'den1': 'Đèn 1', 'quat1': 'Quạt 1', 'van_tren': 'Van (Vườn 1)',
            'den2': 'Đèn 2', 'quat2': 'Quạt 2', 'van_duoi': 'Van (Vườn 2)'
        };
        alert(`Đã lưu lịch: ${action === 'on' ? 'Bật' : 'Tắt'} ${deviceDisplayMap[device] || device} lúc ${time} vào các ngày: ${days.map(day => ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'][day]).join(', ')} (Vườn ${gardenNumber})`);
        loadSchedules(gardenId);
        const modal = bootstrap.Modal.getInstance(document.getElementById('scheduleModal'));
        if (modal) modal.hide();
        else console.error('Không tìm thấy modal #scheduleModal');
    } catch (error) {
        console.error('Lỗi lưu lịch:', error);
        alert(`Không thể lưu lịch: ${error.message}`);
    }
}

// Check API availability
async function checkAPI(gardenId) {
    if (!gardenId) return;
    try {
        await apiRequest('sensor.php', 'get_readings', 'POST', { garden_id: gardenId });
        console.log('API check thành công');
    } catch (error) {
        console.error('Lỗi kiểm tra API:', error);
        showErrorMessage(error);
    }
}

// Initialize page
document.addEventListener('DOMContentLoaded', async () => {
    try {
        setActiveNavLink();
        const gardenId = getGardenIdFromUrl();
        if (gardenId) {
            await initializeMcu(gardenId);
            await loadGardenData(gardenId);

            // Thiết lập cập nhật định kỳ
            setInterval(() => loadGardenData(gardenId), 30000); // Cập nhật mỗi 30 giây
        }

        // Thêm sự kiện cho toggle chế độ tự động
        const autoModeToggle = document.getElementById('autoModeToggle');
        if (autoModeToggle) {
            autoModeToggle.addEventListener('change', (e) => {
                toggleAutoMode(e.target.checked);
            });
        }

        // Thêm sự kiện submit cho schedule form
        const scheduleForm = document.getElementById('scheduleForm');
        if (scheduleForm) {
            scheduleForm.addEventListener('submit', (e) => {
                e.preventDefault();
                saveSchedule();
            });
        }
    } catch (error) {
        console.error('Lỗi khởi tạo trang:', error);
        showErrorMessage(error);
    }
});

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
        showErrorMessage(error);
    }
}

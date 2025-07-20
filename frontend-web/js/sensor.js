/* Quản lý trạng thái vườn */
let lastSensorData = null;
let lastDeviceStatus = null;

function setActiveNavLink() {
    try {
        const navLinks = document.querySelectorAll('.sidebar .nav-link');
        const currentPage = window.location.pathname.split('/').pop() || 'sensor.html';

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

function getGardenIdFromUrl() {
    const urlParams = new URLSearchParams(window.location.search);
    const gardenId = urlParams.get('garden_id');
    if (!gardenId || isNaN(gardenId) || parseInt(gardenId) <= 0) {
        alert('Vui lòng chọn một vườn hợp lệ từ trang Vườn!');
        window.location.href = '/SmartGarden/frontend-web/pages/garden.html';
        return null;
    }
    return parseInt(gardenId);
}

async function assignGarden(gardenId) {
    try {
        const data = await apiRequest('sensor.php', 'get_garden_number', 'POST', { garden_id: gardenId });
        return data.garden_number || 1;
    } catch (error) {
        console.error('Lỗi assignGarden:', error);
        return 1; // Mặc định là vườn 1 nếu có lỗi
    }
}

async function getGardenDevices(gardenId) {
    try {
        const gardenNumber = await assignGarden(gardenId);
        return gardenNumber === 1 ? ['den1', 'quat1'] : ['den2', 'quat2'];
    } catch (error) {
        console.error('Lỗi getGardenDevices:', error);
        return [];
    }
}

async function apiRequest(endpoint, action, method = 'POST', data = {}, retries = 3) {
    for (let attempt = 0; attempt <= retries; attempt++) {
        try {
            const options = {
                method,
                headers: {
                    'Content-Type': 'application/json'
                }
            };
            if (method === 'POST') {
                options.body = JSON.stringify({ action, ...data });
            }
            const res = await fetch(`http://192.168.1.123/SmartGarden/backend-api/routes/${endpoint}`, options);
            const text = await res.text();
            const response = JSON.parse(text);
            if (!response.success) throw new Error(response.message || 'Request failed');
            return response;
        } catch (error) {
            if (attempt === retries) throw error;
            await new Promise(resolve => setTimeout(resolve, 2000 * (attempt + 1)));
        }
    }
}

async function loadGardenData(gardenId) {
    if (!gardenId || isNaN(gardenId)) {
        console.error('Vui lòng cung cấp ID vườn hợp lệ');
        return;
    }
    
    try {
        const gardenNumber = await assignGarden(gardenId);
        await Promise.all([
            loadSensorData(gardenNumber),
            loadDeviceStatus(gardenNumber)
        ]);
    } catch (error) {
        console.error('Lỗi loadGardenData:', error);
    }
}

async function toggleDevice(device, isChecked, gardenNumber) {
    try {
        await apiRequest('sensor.php', 'update_relay', 'POST', {
            garden_number: gardenNumber,
            device_name: device,
            status: isChecked ? 1 : 0
        });
        await loadDeviceStatus(gardenNumber);
    } catch (error) {
        console.error(`Lỗi điều khiển thiết bị ${device}:`, error);
    }
}

async function loadSensorData(gardenNumber) {
    try {
        const data = await apiRequest('sensor.php', 'get_readings', 'POST', { garden_number: gardenNumber });
        if (data.data) {
            updateSensorUI(data.data);
            lastSensorData = data.data;
        }
    } catch (error) {
        console.error('Lỗi tải dữ liệu cảm biến:', error);
    }
}

function updateSensorUI(sensorData) {
    try {
        const now = new Date(sensorData.created_at || new Date());
        const timeString = now.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });

        const sensorFields = [
            { id: 'soil-moisture', value: sensorData.soil_moisture, unit: '%', timeId: 'soil-time' },
            { id: 'temperature', value: sensorData.temperature, unit: '°C', timeId: 'temp-time' },
            { id: 'humidity', value: sensorData.humidity, unit: '%', timeId: 'humi-time' },
            { id: 'water-level', value: sensorData.water_level_cm, unit: 'cm', timeId: 'water-time' },
            { id: 'rain-status', value: sensorData.is_raining ? 'Đang mưa' : 'Không mưa', unit: '', timeId: 'rain-time', class: sensorData.is_raining ? 'text-info' : 'text-warning' }
        ];

        sensorFields.forEach(field => {
            const element = document.getElementById(field.id);
            const timeElement = document.getElementById(field.timeId);
            if (element && timeElement) {
                element.textContent = (field.value !== null ? (typeof field.value === 'number' ? field.value.toFixed(1) : field.value) : '--') + field.unit;
                if (field.class) element.className = 'card-text sensor-value ' + field.class;
                timeElement.textContent = timeString;
            }
        });
    } catch (error) {
        console.error('Lỗi updateSensorUI:', error);
    }
}

async function loadDeviceStatus(gardenNumber) {
    try {
        const data = await apiRequest('sensor.php', 'get_control_command', 'POST', { garden_number: gardenNumber });
        await updateDeviceStatusUI(data.data, gardenNumber);
        lastDeviceStatus = data.data;
    } catch (error) {
        console.error('Lỗi loadDeviceStatus:', error);
    }
}

async function updateDeviceStatusUI(statusData, gardenNumber) {
    try {
        const deviceControls = document.getElementById('device-control-list');
        if (!deviceControls) return;

        const devices = gardenNumber === 1 ? ['den1', 'quat1'] : ['den2', 'quat2'];
        const deviceDisplayMap = {
            'den1': 'Đèn 1', 'quat1': 'Quạt 1',
            'den2': 'Đèn 2', 'quat2': 'Quạt 2'
        };

        deviceControls.innerHTML = devices.map(device => {
            const deviceStatus = statusData.find(item => item.device_name === device) || { status: 0 };
            const isChecked = deviceStatus.status === 1;
            const displayName = deviceDisplayMap[device] || device;
            const iconClass = device.includes('den') ? 'bi-lightbulb' : 'bi-fan';
            return `
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span><i class="bi ${iconClass} me-2"></i>${displayName}</span>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="${device}Toggle" ${isChecked ? 'checked' : ''}>
                    </div>
                </li>
            `;
        }).join('');

        devices.forEach(device => {
            const toggle = document.getElementById(`${device}Toggle`);
            if (toggle) {
                toggle.addEventListener('change', (e) => {
                    toggleDevice(device, e.target.checked, gardenNumber);
                });
            }
        });
    } catch (error) {
        console.error('Lỗi updateDeviceStatusUI:', error);
    }
}

document.addEventListener('DOMContentLoaded', async () => {
    setActiveNavLink();
    const gardenId = getGardenIdFromUrl();
    if (gardenId) {
        await loadGardenData(gardenId);
    }
});
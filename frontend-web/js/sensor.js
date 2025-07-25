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
        const gardenNumber = data.garden_number || 1;

        await apiRequest('sensor.php', 'save_garden_assignment', 'POST', {
            garden_id: gardenId,
            garden_number: gardenNumber
        });

        const macAddress = '94:E6:86:0D:EF:B4';
        await apiRequest('sensor.php', 'get_mcu_id', 'POST', { mac_address: macAddress });

        return gardenNumber;
    } catch (error) {
        console.error('Lỗi assignGarden:', error);
        return 1;
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
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ action, ...data })
            };

            console.log(`[${action}] Attempt ${attempt + 1} - Sending request to: ${endpoint}`, options.body);

            const res = await fetch(`http://192.168.1.123/SmartGarden/backend-api/routes/${endpoint}`, options);
            const responseText = await res.text();
            console.log(`[${action}] Raw response:`, responseText);

            if (!res.ok) {
                throw new Error(`HTTP error! status: ${res.status}`);
            }

            let response;
            try {
                response = JSON.parse(responseText);
            } catch (jsonError) {
                throw new Error(`Invalid JSON response: ${responseText}`);
            }

            if (!response.success) {
                throw new Error(response.message || 'Request failed');
            }

            return response;
        } catch (error) {
            console.error(`[${action}] Attempt ${attempt + 1} failed:`, error);
            if (attempt === retries) throw error;
            await new Promise(resolve => setTimeout(resolve, 1000 * (attempt + 1)));
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

// Lưu ngưỡng cảm biến
async function saveThreshold() {
    try {
        const gardenId = getGardenIdFromUrl();
        const sensorType = document.getElementById('sensorTypeSelect').value;
        const minValue = document.getElementById('minValueInput').value;
        const maxValue = document.getElementById('maxValueInput').value;
        const emailEnabled = document.getElementById('emailEnabled').checked ? 1 : 0;

        if (!sensorType) {
            alert('Vui lòng chọn loại cảm biến!');
            return;
        }

        if (!minValue && !maxValue) {
            alert('Vui lòng cung cấp ít nhất một ngưỡng (tối thiểu hoặc tối đa)!');
            return;
        }

        // Kiểm tra min_value <= max_value
        if (minValue && maxValue && parseFloat(minValue) > parseFloat(maxValue)) {
            alert('Ngưỡng tối thiểu phải nhỏ hơn hoặc bằng ngưỡng tối đa!');
            return;
        }

        console.log('Saving threshold:', { garden_id: gardenId, sensor_type: sensorType, min_value: minValue, max_value: maxValue, email_enabled: emailEnabled });

        const response = await apiRequest('sensor.php', 'set_threshold', 'POST', {
            garden_id: gardenId,
            sensor_type: sensorType,
            min_value: minValue,
            max_value: maxValue,
            email_enabled: emailEnabled
        });

        if (response.success) {
            alert('Đã lưu ngưỡng thành công!');
            $('#thresholdModal').modal('hide');
            await displayThresholds(gardenId);
            // Kích hoạt kiểm tra và gửi email ngay sau khi lưu ngưỡng
            await checkAndSendAlert(gardenId);
        } else {
            alert('Lỗi: ' + (response.message || 'Không thể lưu ngưỡng'));
        }
    } catch (error) {
        console.error('Lỗi khi lưu ngưỡng:', error);
        alert('Lỗi khi lưu ngưỡng: ' + error.message);
    }
}

// Kiểm tra và gửi email cảnh báo sau khi lưu ngưỡng
async function checkAndSendAlert(gardenId) {
    try {
        const response = await apiRequest('sensor.php', 'check_and_send_alert', 'POST', { garden_id: gardenId });
        if (response.success && response.alert_sent) {
            alert('Email cảnh báo đã được gửi thành công!');
        } else if (response.success && !response.alert_sent) {
            console.log('Không có cảnh báo nào được kích hoạt.');
        } else {
            alert('Lỗi khi kiểm tra và gửi cảnh báo: ' + (response.message || 'Lỗi không xác định'));
        }
    } catch (error) {
        console.error('Lỗi khi kiểm tra và gửi cảnh báo:', error);
        alert('Lỗi khi kiểm tra và gửi cảnh báo: ' + error.message);
    }
}

// Hiển thị ngưỡng
async function displayThresholds(gardenId) {
    try {
        const response = await apiRequest('sensor.php', 'get_thresholds', 'POST', { garden_id: gardenId });
        const thresholdContainer = document.getElementById('threshold-display');
        
        if (!thresholdContainer) {
            console.error('Cannot find threshold-display container');
            return;
        }

        const sensorTypeMap = {
            'soil_moisture': 'Độ ẩm đất',
            'temperature': 'Nhiệt độ',
            'humidity': 'Độ ẩm không khí',
            'water_level_cm': 'Mực nước'
        };

        if (response.data && response.data.length > 0) {
            const thresholdsHTML = response.data.map(threshold => `
                <div class="alert alert-info mb-2">
                    <p><strong>Cảm biến:</strong> ${sensorTypeMap[threshold.sensor_type] || threshold.sensor_type}</p>
                    <p><strong>Ngưỡng tối thiểu:</strong> ${threshold.min_value !== null ? threshold.min_value : '--'}</p>
                    <p><strong>Ngưỡng tối đa:</strong> ${threshold.max_value !== null ? threshold.max_value : '--'}</p>
                    <p><strong>Gửi email:</strong> ${threshold.email_enabled ? 'Bật' : 'Tắt'}</p>
                </div>
            `).join('');
            thresholdContainer.innerHTML = `<h6>Các ngưỡng đã cài đặt:</h6>${thresholdsHTML}`;
        } else {
            thresholdContainer.innerHTML = '<h6>Các ngưỡng đã cài đặt:</h6><p class="text-muted">Chưa có ngưỡng nào được cài đặt.</p>';
        }
    } catch (error) {
        console.error('Lỗi tải ngưỡng:', error);
    }
}

// Hiển thị lịch sử cảnh báo
async function displayAlerts(gardenId) {
    try {
        console.log('Calling displayAlerts for gardenId:', gardenId);
        const response = await apiRequest('sensor.php', 'get_alerts', 'POST', { garden_id: gardenId });
        console.log('get_alerts response:', response);
        
        const alertContainer = document.getElementById('alerts-list');
        if (!alertContainer) {
            console.error('Cannot find alerts-list container');
            return;
        }

        const sensorTypeMap = {
            'soil_moisture': 'Độ ẩm đất',
            'temperature': 'Nhiệt độ',
            'humidity': 'Độ ẩm không khí',
            'water_level_cm': 'Mực nước'
        };

        if (response.data && response.data.length > 0) {
            const alertsHTML = response.data.map(alert => `
                <div class="alert alert-warning mb-2">
                    <p><strong>Cảm biến:</strong> ${sensorTypeMap[alert.sensor_type] || alert.sensor_type}</p>
                    <p><strong>Cảnh báo:</strong> ${alert.alert_type}</p>
                    <p><strong>Giá trị:</strong> ${alert.sensor_value}</p>
                    <p><strong>Thời gian:</strong> ${new Date(alert.created_at).toLocaleString('vi-VN')}</p>
                    <p><strong>Gửi đến:</strong> ${alert.sent_to_email || 'Không có email'}</p>
                </div>
            `).join('');
            alertContainer.innerHTML = `<h6>Lịch sử cảnh báo:</h6>${alertsHTML}`;
        } else {
            alertContainer.innerHTML = '<h6>Lịch sử cảnh báo:</h6><p class="text-muted">Chưa có cảnh báo nào.</p>';
        }
    } catch (error) {
        console.error('Lỗi tải cảnh báo:', error);
        const alertContainer = document.getElementById('alerts-list');
        if (alertContainer) {
            alertContainer.innerHTML = '<h6>Lịch sử cảnh báo:</h6><p class="text-danger">Lỗi tải cảnh báo: ' + error.message + '</p>';
        }
    }
}

async function saveSchedule() {
    try {
        const device = document.getElementById('deviceSelect').value;
        const action = document.getElementById('actionSelect').value === 'on' ? 1 : 0;
        const startTime = document.getElementById('startTimeInput').value;
        const endTime = document.getElementById('endTimeInput').value;
        const selectedDate = document.getElementById('dateInput').value;
        const gardenId = getGardenIdFromUrl();
        const mcuId = 'mcu_001';

        if (!device || !startTime || !selectedDate) {
            alert('Vui lòng điền đầy đủ thông tin bắt buộc!');
            return;
        }

        const formattedStartTime = startTime + ':00';
        const formattedEndTime = endTime ? endTime + ':00' : '';

        if (!/^[0-2][0-9]:[0-5][0-9]$/.test(startTime)) {
            alert('Thời gian bắt đầu không hợp lệ (HH:MM)');
            return;
        }
        if (endTime && !/^[0-2][0-9]:[0-5][0-9]$/.test(endTime)) {
            alert('Thời gian kết thúc không hợp lệ (HH:MM)');
            return;
        }

        if (!/^\d{4}-\d{2}-\d{2}$/.test(selectedDate)) {
            alert('Ngày không hợp lệ (YYYY-MM-DD)');
            return;
        }

        const now = new Date();
        const scheduleDateTime = new Date(`${selectedDate}T${formattedStartTime}`);
        if (scheduleDateTime < now) {
            alert('Lịch trình không thể được đặt trong quá khứ!');
            return;
        }

        if (endTime) {
            const startDateTime = new Date(`${selectedDate}T${formattedStartTime}`);
            const endDateTime = new Date(`${selectedDate}T${formattedEndTime}`);
            const timeDiff = (endDateTime - startDateTime) / 1000 / 60;

            if (timeDiff < 1) {
                alert('Thời gian kết thúc phải ít nhất 1 phút sau thời gian bắt đầu!');
                return;
            }
        }

        const gardenNumber = await assignGarden(gardenId);
        const validDevices = gardenNumber === 1 ? ['den1', 'quat1'] : ['den2', 'quat2'];
        if (!validDevices.includes(device)) {
            alert(`Thiết bị ${device} không hợp lệ cho vườn số ${gardenNumber}`);
            return;
        }

        const scheduleData = {
            device_name: device,
            action: action,
            time: formattedStartTime,
            end_time: formattedEndTime,
            date: selectedDate,
            garden_id: gardenId,
            mcu_id: mcuId
        };

        console.log('Dữ liệu gửi đi:', { schedule: scheduleData });

        const response = await apiRequest('sensor.php', 'save_schedule', 'POST', { 
            schedule: scheduleData 
        });

        if (response.success) {
            alert('Đã lưu lịch thành công!');
            $('#scheduleModal').modal('hide');
            displaySchedules(gardenId);
        } else {
            alert('Lỗi: ' + (response.message || 'Không thể lưu lịch'));
        }
    } catch (error) {
        console.error('Lỗi khi lưu lịch:', error);
        alert('Lỗi khi lưu lịch: ' + error.message);
    }
}

function displaySchedules(gardenId) {
    apiRequest('sensor.php', 'get_schedules', 'POST', { garden_id: gardenId })
        .then(response => {
            const scheduleContainer = document.getElementById('schedule-container');
            
            if (response.data && response.data.length > 0) {
                const schedulesHTML = response.data.map(schedule => {
                    const startTime = schedule.time ? schedule.time.substring(0, 5) : '';
                    const endTime = schedule.end_time ? schedule.end_time.substring(0, 5) : '';
                    const deviceNameMap = {
                        'den1': 'Đèn 1',
                        'quat1': 'Quạt 1',
                        'den2': 'Đèn 2',
                        'quat2': 'Quạt 2'
                    };
                    const displayName = deviceNameMap[schedule.device_name] || schedule.device_name;
                    
                    return `
                        <div class="card mb-3">
                            <div class="card-body">
                                <h5>${displayName}</h5>
                                <p>Trạng thái: ${schedule.action ? 'BẬT' : 'TẮT'}</p>
                                <p>Thời gian: ${startTime} ${endTime ? `- ${endTime}` : ''}</p>
                                <p>Ngày: ${schedule.date}</p>
                                <button class="btn btn-sm btn-danger" onclick="deleteSchedule(${schedule.id})">
                                    Xóa
                                </button>
                            </div>
                        </div>
                    `;
                }).join('');
                scheduleContainer.innerHTML = `<h5 class="card-title text-success">📅 Lịch trình tự động</h5>${schedulesHTML}`;
            } else {
                scheduleContainer.innerHTML = `<h5 class="card-title text-success">📅 Lịch trình tự động</h5><p class="text-muted">Chưa có lịch trình nào.</p>`;
            }
        });
}

function populateDeviceOptionsByGarden(gardenNumber) {
    const deviceSelect = document.getElementById('deviceSelect');
    const deviceMap = {
        1: ['den1', 'quat1'],
        2: ['den2', 'quat2']
    };

    const displayNameMap = {
        'den1': 'Đèn 1',
        'quat1': 'Quạt 1',
        'den2': 'Đèn 2',
        'quat2': 'Quạt 2'
    };

    const devices = deviceMap[gardenNumber] || [];
    deviceSelect.innerHTML = '<option value="">Chọn thiết bị</option>';
    devices.forEach(device => {
        const option = document.createElement('option');
        option.value = device;
        option.textContent = displayNameMap[device];
        deviceSelect.appendChild(option);
    });
}

async function deleteSchedule(scheduleId) {
    if (!confirm('Bạn có chắc chắn muốn xóa lịch trình này?')) {
        return;
    }

    try {
        const response = await apiRequest('sensor.php', 'delete_schedule', 'POST', { id: scheduleId });
        if (response.success) {
            alert('Đã xóa lịch trình thành công');
            displaySchedules(getGardenIdFromUrl());
        } else {
            alert('Xóa lịch trình thất bại: ' + response.message);
        }
    } catch (error) {
        console.error('Lỗi khi xóa lịch trình:', error);
        alert('Lỗi khi xóa lịch trình: ' + error.message);
    }
}

document.addEventListener('DOMContentLoaded', async () => {
    setActiveNavLink();
    const gardenId = getGardenIdFromUrl();
    if (gardenId) {
        const gardenNumber = await assignGarden(gardenId);
        await loadGardenData(gardenId);
        await displaySchedules(gardenId);
        await displayAlerts(gardenId);
        await displayThresholds(gardenId);
        populateDeviceOptionsByGarden(gardenNumber);

        setInterval(async () => {
            await loadGardenData(gardenId);
            await displaySchedules(gardenId);
            await displayAlerts(gardenId);
            await displayThresholds(gardenId);
        }, 5000); 
    }
});
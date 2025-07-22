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
        // Lấy garden_number
        const data = await apiRequest('sensor.php', 'get_garden_number', 'POST', { garden_id: gardenId });
        const gardenNumber = data.garden_number || 1;

        // Gán vườn với mcu_id="mcu_001"
        await apiRequest('sensor.php', 'save_garden_assignment', 'POST', {
            garden_id: gardenId,
            garden_number: gardenNumber
        });

        // Đảm bảo mcu_id="mcu_001" trong mcu_assignments
        const macAddress = '94:E6:86:0D:EF:B4'; // MAC của ESP32
        await apiRequest('sensor.php', 'get_mcu_id', 'POST', { mac_address: macAddress });

        return gardenNumber;
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
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ action, ...data })
            };

            console.log('Sending request to:', endpoint);
            console.log('Request data:', options.body);

            const res = await fetch(`http://192.168.1.123/SmartGarden/backend-api/routes/${endpoint}`, options);
            
            const responseText = await res.text();
            console.log('Raw response:', responseText);

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
            if (attempt === retries) throw error;
            console.error(`Attempt ${attempt + 1} failed:`, error);
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

async function saveSchedule() {
    try {
        const device = document.getElementById('deviceSelect').value;
        const action = document.getElementById('actionSelect').value === 'on' ? 1 : 0;
        const startTime = document.getElementById('startTimeInput').value;
        const endTime = document.getElementById('endTimeInput').value;
        const selectedDate = document.getElementById('dateInput').value;
        const gardenId = getGardenIdFromUrl();
        const mcuId = 'mcu_001'; // Sử dụng mcu_id cố định cho một ESP32

        // Validate required fields
        if (!device || !startTime || !selectedDate) {
            alert('Vui lòng điền đầy đủ thông tin bắt buộc!');
            return;
        }

        // Format times to HH:MM:SS
        const formattedStartTime = startTime + ':00';
        const formattedEndTime = endTime ? endTime + ':00' : '';

        // Validate time format (HH:MM)
        if (!/^[0-2][0-9]:[0-5][0-9]$/.test(startTime)) {
            alert('Thời gian bắt đầu không hợp lệ (HH:MM)');
            return;
        }
        if (endTime && !/^[0-2][0-9]:[0-5][0-9]$/.test(endTime)) {
            alert('Thời gian kết thúc không hợp lệ (HH:MM)');
            return;
        }

        // Validate date format (YYYY-MM-DD)
        if (!/^\d{4}-\d{2}-\d{2}$/.test(selectedDate)) {
            alert('Ngày không hợp lệ (YYYY-MM-DD)');
            return;
        }

        // Check if schedule is in the past
        const now = new Date();
        const scheduleDateTime = new Date(`${selectedDate}T${formattedStartTime}`);
        if (scheduleDateTime < now) {
            alert('Lịch trình không thể được đặt trong quá khứ!');
            return;
        }

        // Validate time range (at least 1 minute difference, allow AM/PM)
        if (endTime) {
            const startDateTime = new Date(`${selectedDate}T${formattedStartTime}`);
            const endDateTime = new Date(`${selectedDate}T${formattedEndTime}`);
            const timeDiff = (endDateTime - startDateTime) / 1000 / 60; // Difference in minutes

            if (timeDiff < 1) {
                alert('Thời gian kết thúc phải ít nhất 1 phút sau thời gian bắt đầu!');
                return;
            }
        }

        // Chuẩn bị dữ liệu đúng định dạng
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
                    // Format thời gian từ HH:MM:SS thành HH:MM
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
                scheduleContainer.innerHTML = schedulesHTML;
            } else {
                scheduleContainer.innerHTML = '<p>Chưa có lịch trình nào.</p>';
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
        populateDeviceOptionsByGarden(gardenNumber);

        // Periodically refresh sensor data and schedules every 30 seconds
        setInterval(async () => {
            await loadGardenData(gardenId);
            await displaySchedules(gardenId);
        }, 30000); // 30 seconds
    }
});
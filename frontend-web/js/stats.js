document.addEventListener('DOMContentLoaded', function() {
    let temperatureChart, humidityChart, soilMoistureChart, waterLevelChart, rainChart, timeChart;
    let isAdmin = false;
    let userId = null;

    checkAuthAndInit();

    async function checkAuthAndInit() {
        try {
            const token = localStorage.getItem('accessToken');
            if (!token) {
                console.warn("Không tìm thấy accessToken trong localStorage");
                showLoginUI();
                return;
            }

            console.log("Retrieved accessToken from localStorage:", token.substring(0, 20) + "...");

            const response = await fetch('http://192.168.1.123/SmartGarden/backend-api/routes/home.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`
                },
                body: JSON.stringify({
                    action: 'check_login_status',
                    token: token
                })
            });

            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`HTTP error! status: ${response.status}, response: ${errorText}`);
            }

            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.message || 'Token không hợp lệ');
            }

            userId = data.data.user.id;
            isAdmin = data.data.user.administrator_rights == 1;
            localStorage.setItem('isAdmin', isAdmin);
            localStorage.setItem('currentUserId', userId);
            
            console.log("Authentication successful, userId:", userId, "isAdmin:", isAdmin);

            showAuthenticatedUI();
            if (typeof Chart === 'undefined') {
                throw new Error('Thư viện Chart.js chưa được tải');
            }
            initCharts();
            loadGardens();
        } catch (error) {
            console.error('Lỗi xác thực:', error.message);
            showError(`Lỗi xác thực: ${error.message}. Vui lòng đăng nhập lại.`);
            setTimeout(() => {
                localStorage.removeItem('accessToken');
                localStorage.removeItem('isAdmin');
                localStorage.removeItem('currentUserId');
                window.location.href = 'login.html';
            }, 3000);
        }
    }

    function showLoginUI() {
        document.querySelectorAll('.auth-required').forEach(el => {
            el.style.display = 'none';
        });
        
        const loginMessage = document.createElement('div');
        loginMessage.className = 'login-message';
        loginMessage.innerHTML = `
            <h2>Vui lòng đăng nhập để tiếp tục</h2>
            <a href="login.html" class="btn btn-primary">Đăng nhập</a>
        `;
        
        const container = document.querySelector('.container-fluid') || document.body;
        container.prepend(loginMessage);
    }

    function showAuthenticatedUI() {
        document.querySelectorAll('.auth-required').forEach(el => {
            el.style.display = '';
        });
        
        const loginMessage = document.querySelector('.login-message');
        if (loginMessage) {
            loginMessage.remove();
        }
    }

    function initCharts() {
        const temperatureCtx = document.getElementById('temperatureChart')?.getContext('2d');
        const humidityCtx = document.getElementById('humidityChart')?.getContext('2d');
        const soilMoistureCtx = document.getElementById('soilMoistureChart')?.getContext('2d');
        const waterLevelCtx = document.getElementById('waterLevelChart')?.getContext('2d');
        const rainCtx = document.getElementById('rainChart')?.getContext('2d');
        const timeCtx = document.getElementById('timeChart')?.getContext('2d');
        
        if (temperatureCtx) {
            temperatureChart = new Chart(temperatureCtx, {
                type: 'bar',
                data: { labels: [], datasets: [] },
                options: { responsive: true }
            });
        }
        
        if (humidityCtx) {
            humidityChart = new Chart(humidityCtx, {
                type: 'bar',
                data: { labels: [], datasets: [] },
                options: { responsive: true }
            });
        }
        
        if (soilMoistureCtx) {
            soilMoistureChart = new Chart(soilMoistureCtx, {
                type: 'bar',
                data: { labels: [], datasets: [] },
                options: { responsive: true }
            });
        }
        
        if (waterLevelCtx) {
            waterLevelChart = new Chart(waterLevelCtx, {
                type: 'bar',
                data: { labels: [], datasets: [] },
                options: { responsive: true }
            });
        }
        
        if (rainCtx) {
            rainChart = new Chart(rainCtx, {
                type: 'bar',
                data: { labels: [], datasets: [] },
                options: { responsive: true }
            });
        }
        
        if (timeCtx) {
            timeChart = new Chart(timeCtx, {
                type: 'bar',
                data: { labels: [], datasets: [] },
                options: { responsive: true }
            });
        }
    }

    async function loadGardens() {
        try {
            const token = localStorage.getItem('accessToken');
            if (!token) {
                showLoginUI();
                return;
            }

            const response = await fetch('http://192.168.1.123/SmartGarden/backend-api/routes/stats.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`
                },
                body: JSON.stringify({
                    action: 'get_accessible_gardens'
                })
            });

            if (!response.ok) {
                const errorText = await response.text();
                console.error('Server response:', errorText);
                throw new Error(`HTTP error! status: ${response.status}, response: ${errorText}`);
            }

            const data = await response.json();
            
            if (data.success) {
                console.log("Danh sách vườn:", data.data);
                populateGardenSelects(data.data);
            } else {
                throw new Error(data.message || 'Không thể tải danh sách vườn');
            }
        } catch (error) {
            console.error('Lỗi tải danh sách vườn:', error.message);
            showError(`Không thể tải danh sách vườn: ${error.message}. Vui lòng kiểm tra kết nối hoặc đăng nhập lại.`);
        }
    }

    document.getElementById('generateChartBtn')?.addEventListener('click', async function() {
        const gardenId1 = document.getElementById('gardenSelect1')?.value;
        const gardenId2 = document.getElementById('gardenSelect2')?.value;
        const timeRange = document.getElementById('timeRange')?.value || '7d';
        const chartType = document.getElementById('chartType')?.value || 'bar';
        const sensorType = document.getElementById('sensorType')?.value || 'all';
        
        if (!gardenId1) {
            showError('Vui lòng chọn ít nhất một vườn');
            return;
        }
        
        try {
            const token = localStorage.getItem('accessToken');
            if (!token) {
                showLoginUI();
                return;
            }

            const garden_ids = [gardenId1];
            if (gardenId2 && gardenId2 !== gardenId1 && gardenId2 !== '') {
                garden_ids.push(gardenId2);
            }

            const requestBody = {
                action: 'get_stats',
                garden_ids: garden_ids,
                time_range: timeRange
            };

            console.log('Gửi yêu cầu thống kê:', requestBody);

            const response = await fetch('http://192.168.1.123/SmartGarden/backend-api/routes/stats.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`
                },
                body: JSON.stringify(requestBody)
            });

            if (!response.ok) {
                const errorData = await response.json();
                console.error('Server error response:', errorData);
                throw new Error(errorData.message || `HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            
            if (data.success) {
                console.log("Dữ liệu thống kê:", data.data);
                if (data.data.length === 0 || data.data.every(garden => 
                    garden.avg_temperature === 0 &&
                    garden.avg_humidity === 0 &&
                    garden.avg_soil_moisture === 0 &&
                    garden.avg_water_level === 0 &&
                    garden.rain_percentage === 0)) {
                    showError('Không có dữ liệu thống kê cho vườn đã chọn trong khoảng thời gian này.');
                    return;
                }
                updateCharts(data.data, chartType, timeRange, sensorType);
                updateStatsTable(data.data);
            } else {
                throw new Error(data.message || 'Không thể tải thống kê');
            }
        } catch (error) {
            console.error('Lỗi tải thống kê:', error.message);
            showError(`Lỗi tải thống kê: ${error.message}. Vui lòng thử lại sau.`);
        }
    });

    function populateGardenSelects(gardens) {
        console.log('Dữ liệu vườn nhận được:', gardens);
        const gardenSelect1 = document.getElementById('gardenSelect1');
        const gardenSelect2 = document.getElementById('gardenSelect2');
        const generateBtn = document.getElementById('generateChartBtn');
        
        gardenSelect1.innerHTML = '<option value="" disabled selected>Chọn một vườn</option>';
        gardenSelect2.innerHTML = '<option value="" selected>Không so sánh</option>';
        
        if (!gardens || !Array.isArray(gardens) || gardens.length === 0) {
            console.warn('Không có vườn nào để hiển thị hoặc dữ liệu không đúng định dạng');
            gardenSelect1.innerHTML = '<option value="" disabled selected>Không có vườn nào</option>';
            gardenSelect2.innerHTML = '<option value="" selected>Không có vườn nào</option>';
            showError('Không tìm thấy vườn nào. Vui lòng tạo vườn trong Quản Lý Vườn Cây.');
            if (generateBtn) generateBtn.disabled = true;
            return;
        }

        gardens.forEach(garden => {
            const optionText = `${garden.garden_name || 'Không tên'} (Chủ: ${garden.user_full_name || 'Không xác định'})`;
            const optionValue = garden.garden_id;
            
            const option1 = new Option(optionText, optionValue);
            const option2 = new Option(optionText, optionValue);
            gardenSelect1.add(option1);
            gardenSelect2.add(option2);
        });

        gardenSelect1.addEventListener('change', () => {
            console.log('Vườn được chọn (gardenSelect1):', gardenSelect1.value);
            if (generateBtn) {
                generateBtn.disabled = !gardenSelect1.value;
            }
        });
    }

    function updateCharts(gardenData, chartType, timeRange, sensorType) {
        if (temperatureChart) temperatureChart.destroy();
        if (humidityChart) humidityChart.destroy();
        if (soilMoistureChart) soilMoistureChart.destroy();
        if (waterLevelChart) waterLevelChart.destroy();
        if (rainChart) rainChart.destroy();
        if (timeChart) timeChart.destroy();
        
        if (!gardenData || !Array.isArray(gardenData) || gardenData.length === 0) {
            console.warn('Không có dữ liệu vườn để vẽ biểu đồ');
            showError('Không có dữ liệu để vẽ biểu đồ.');
            return;
        }

        const temperatureLabels = gardenData.map(garden => garden.name || 'Không xác định');
        const temperatureDatasets = [
            {
                label: 'Nhiệt độ trung bình (°C)',
                data: gardenData.map(garden => parseFloat(garden.avg_temperature) || 0),
                backgroundColor: gardenData.map(garden => (parseFloat(garden.avg_temperature) || 0) > 35 ? 'rgba(255, 99, 132, 0.8)' : 'rgba(255, 99, 132, 0.5)'),
                borderColor: 'rgba(255, 99, 132, 1)',
                borderWidth: 1
            },
            {
                label: 'Nhiệt độ tối đa (°C)',
                data: gardenData.map(garden => parseFloat(garden.max_temperature) || 0),
                backgroundColor: gardenData.map(garden => (parseFloat(garden.max_temperature) || 0) > 35 ? 'rgba(255, 159, 64, 0.8)' : 'rgba(255, 159, 64, 0.5)'),
                borderColor: 'rgba(255, 159, 64, 1)',
                borderWidth: 1
            },
            {
                label: 'Nhiệt độ tối thiểu (°C)',
                data: gardenData.map(garden => parseFloat(garden.min_temperature) || 0),
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }
        ];
        
        const temperatureCtx = document.getElementById('temperatureChart')?.getContext('2d');
        if (temperatureCtx && (sensorType === 'all' || sensorType === 'temperature')) {
            temperatureChart = new Chart(temperatureCtx, {
                type: chartType,
                data: {
                    labels: temperatureLabels,
                    datasets: temperatureDatasets
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: { display: true, text: 'Thống kê nhiệt độ' },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) label += ': ';
                                    label += context.parsed.y.toFixed(1);
                                    if (context.dataset.label.includes('trung bình') && context.parsed.y > 35) {
                                        label += ' (Cảnh báo: Nhiệt độ cao)';
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: 'Nhiệt độ (°C)' }
                        }
                    }
                }
            });
        }
        
        const humidityLabels = gardenData.map(garden => garden.name || 'Không xác định');
        const humidityDatasets = [
            {
                label: 'Độ ẩm không khí trung bình (%)',
                data: gardenData.map(garden => parseFloat(garden.avg_humidity) || 0),
                backgroundColor: 'rgba(75, 192, 192, 0.5)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            },
            {
                label: 'Độ ẩm không khí tối đa (%)',
                data: gardenData.map(garden => parseFloat(garden.max_humidity) || 0),
                backgroundColor: 'rgba(153, 102, 255, 0.5)',
                borderColor: 'rgba(153, 102, 255, 1)',
                borderWidth: 1
            },
            {
                label: 'Độ ẩm không khí tối thiểu (%)',
                data: gardenData.map(garden => parseFloat(garden.min_humidity) || 0),
                backgroundColor: 'rgba(255, 206, 86, 0.5)',
                borderColor: 'rgba(255, 206, 86, 1)',
                borderWidth: 1
            }
        ];
        
        const humidityCtx = document.getElementById('humidityChart')?.getContext('2d');
        if (humidityCtx && (sensorType === 'all' || sensorType === 'humidity')) {
            humidityChart = new Chart(humidityCtx, {
                type: chartType,
                data: {
                    labels: humidityLabels,
                    datasets: humidityDatasets
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: { display: true, text: 'Thống kê độ ẩm không khí' }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            title: { display: true, text: 'Độ ẩm không khí (%)' }
                        }
                    }
                }
            });
        }
        
        const soilMoistureLabels = gardenData.map(garden => garden.name || 'Không xác định');
        const soilMoistureDatasets = [
            {
                label: 'Độ ẩm đất trung bình (%)',
                data: gardenData.map(garden => parseFloat(garden.avg_soil_moisture) || 0),
                backgroundColor: gardenData.map(garden => (parseFloat(garden.avg_soil_moisture) || 0) < 20 ? 'rgba(255, 99, 132, 0.8)' : 'rgba(54, 162, 235, 0.5)'),
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            },
            {
                label: 'Độ ẩm đất tối đa (%)',
                data: gardenData.map(garden => parseFloat(garden.max_soil_moisture) || 0),
                backgroundColor: 'rgba(75, 192, 192, 0.5)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            },
            {
                label: 'Độ ẩm đất tối thiểu (%)',
                data: gardenData.map(garden => parseFloat(garden.min_soil_moisture) || 0),
                backgroundColor: gardenData.map(garden => (parseFloat(garden.min_soil_moisture) || 0) < 20 ? 'rgba(255, 99, 132, 0.8)' : 'rgba(255, 206, 86, 0.5)'),
                borderColor: 'rgba(255, 206, 86, 1)',
                borderWidth: 1
            }
        ];
        
        const soilMoistureCtx = document.getElementById('soilMoistureChart')?.getContext('2d');
        if (soilMoistureCtx && (sensorType === 'all' || sensorType === 'soil_moisture')) {
            soilMoistureChart = new Chart(soilMoistureCtx, {
                type: chartType,
                data: {
                    labels: soilMoistureLabels,
                    datasets: soilMoistureDatasets
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: { display: true, text: 'Thống kê độ ẩm đất' },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) label += ': ';
                                    label += context.parsed.y.toFixed(1);
                                    if (context.dataset.label.includes('trung bình') && context.parsed.y < 20) {
                                        label += ' (Cảnh báo: Độ ẩm đất thấp)';
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            title: { display: true, text: 'Độ ẩm đất (%)' }
                        }
                    }
                }
            });
        }

        const waterLevelLabels = gardenData.map(garden => garden.name || 'Không xác định');
        const waterLevelDatasets = [
            {
                label: 'Mực nước trung bình (cm)',
                data: gardenData.map(garden => parseFloat(garden.avg_water_level) || 0),
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            },
            {
                label: 'Mực nước tối đa (cm)',
                data: gardenData.map(garden => parseFloat(garden.max_water_level) || 0),
                backgroundColor: 'rgba(75, 192, 192, 0.5)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            },
            {
                label: 'Mực nước tối thiểu (cm)',
                data: gardenData.map(garden => parseFloat(garden.min_water_level) || 0),
                backgroundColor: 'rgba(153, 102, 255, 0.5)',
                borderColor: 'rgba(153, 102, 255, 1)',
                borderWidth: 1
            }
        ];
        
        const waterLevelCtx = document.getElementById('waterLevelChart')?.getContext('2d');
        if (waterLevelCtx && (sensorType === 'all' || sensorType === 'water_level')) {
            waterLevelChart = new Chart(waterLevelCtx, {
                type: chartType,
                data: {
                    labels: waterLevelLabels,
                    datasets: waterLevelDatasets
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: { display: true, text: 'Thống kê mực nước' }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: 'Mực nước (cm)' }
                        }
                    }
                }
            });
        }

        const rainLabels = gardenData.map(garden => garden.name || 'Không xác định');
        const rainDatasets = [
            {
                label: 'Tỷ lệ mưa (%)',
                data: gardenData.map(garden => parseFloat(garden.rain_percentage) || 0),
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }
        ];
        
        const rainCtx = document.getElementById('rainChart')?.getContext('2d');
        if (rainCtx && (sensorType === 'all' || sensorType === 'rain')) {
            rainChart = new Chart(rainCtx, {
                type: chartType,
                data: {
                    labels: rainLabels,
                    datasets: rainDatasets
                },
                options: {
                    responsive: true,
                    plugins: {
                        title: { display: true, text: 'Thống kê tỷ lệ mưa' }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            title: { display: true, text: 'Tỷ lệ mưa (%)' }
                        }
                    }
                }
            });
        }

        if (gardenData.length > 0 && gardenData[0].monthly_stats && gardenData[0].monthly_stats.length > 0) {
            const timeData = gardenData[0].monthly_stats;
            let groupedData = [];
            
            if (timeRange === 'quarterly') {
                const quarters = {};
                timeData.forEach(entry => {
                    const date = new Date(entry.month + '-01');
                    const quarter = Math.floor(date.getMonth() / 3) + 1;
                    const yearQuarter = `${date.getFullYear()} Q${quarter}`;
                    
                    if (!quarters[yearQuarter]) {
                        quarters[yearQuarter] = {
                            avg_temperature: 0,
                            avg_soil_moisture: 0,
                            avg_humidity: 0,
                            avg_water_level: 0,
                            rain_percentage: 0,
                            count: 0
                        };
                    }
                    
                    quarters[yearQuarter].avg_temperature += parseFloat(entry.avg_temperature) || 0;
                    quarters[yearQuarter].avg_soil_moisture += parseFloat(entry.avg_soil_moisture) || 0;
                    quarters[yearQuarter].avg_humidity += parseFloat(entry.avg_humidity) || 0;
                    quarters[yearQuarter].avg_water_level += parseFloat(entry.avg_water_level) || 0;
                    quarters[yearQuarter].rain_percentage += parseFloat(entry.rain_percentage) || 0;
                    quarters[yearQuarter].count += 1;
                });
                
                groupedData = Object.keys(quarters).map(key => ({
                    period: key,
                    avg_temperature: quarters[key].avg_temperature / quarters[key].count,
                    avg_soil_moisture: quarters[key].avg_soil_moisture / quarters[key].count,
                    avg_humidity: quarters[key].avg_humidity / quarters[key].count,
                    avg_water_level: quarters[key].avg_water_level / quarters[key].count,
                    rain_percentage: quarters[key].rain_percentage / quarters[key].count
                }));
            } else if (timeRange === 'yearly') {
                const years = {};
                timeData.forEach(entry => {
                    const year = entry.month.split('-')[0];
                    
                    if (!years[year]) {
                        years[year] = {
                            avg_temperature: 0,
                            avg_soil_moisture: 0,
                            avg_humidity: 0,
                            avg_water_level: 0,
                            rain_percentage: 0,
                            count: 0
                        };
                    }
                    
                    years[year].avg_temperature += parseFloat(entry.avg_temperature) || 0;
                    years[year].avg_soil_moisture += parseFloat(entry.avg_soil_moisture) || 0;
                    years[year].avg_humidity += parseFloat(entry.avg_humidity) || 0;
                    years[year].avg_water_level += parseFloat(entry.avg_water_level) || 0;
                    years[year].rain_percentage += parseFloat(entry.rain_percentage) || 0;
                    years[year].count += 1;
                });
                
                groupedData = Object.keys(years).map(key => ({
                    period: key,
                    avg_temperature: years[key].avg_temperature / years[key].count,
                    avg_soil_moisture: years[key].avg_soil_moisture / years[key].count,
                    avg_humidity: years[key].avg_humidity / years[key].count,
                    avg_water_level: years[key].avg_water_level / years[key].count,
                    rain_percentage: years[key].rain_percentage / years[key].count
                }));
            } else {
                groupedData = timeData.map(entry => ({
                    period: entry.month,
                    avg_temperature: parseFloat(entry.avg_temperature) || 0,
                    avg_soil_moisture: parseFloat(entry.avg_soil_moisture) || 0,
                    avg_humidity: parseFloat(entry.avg_humidity) || 0,
                    avg_water_level: parseFloat(entry.avg_water_level) || 0,
                    rain_percentage: parseFloat(entry.rain_percentage) || 0
                }));
            }
            
            const timeLabels = groupedData.map(entry => entry.period);
            const timeDatasets = [];
            
            if (sensorType === 'all' || sensorType === 'temperature') {
                timeDatasets.push({
                    label: 'Nhiệt độ trung bình (°C)',
                    data: groupedData.map(entry => entry.avg_temperature),
                    backgroundColor: groupedData.map(entry => entry.avg_temperature > 35 ? 'rgba(255, 99, 132, 0.8)' : 'rgba(255, 99, 132, 0.5)'),
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1
                });
            }
            if (sensorType === 'all' || sensorType === 'soil_moisture') {
                timeDatasets.push({
                    label: 'Độ ẩm đất trung bình (%)',
                    data: groupedData.map(entry => entry.avg_soil_moisture),
                    backgroundColor: groupedData.map(entry => entry.avg_soil_moisture < 20 ? 'rgba(255, 99, 132, 0.8)' : 'rgba(54, 162, 235, 0.5)'),
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1,
                    type: 'line',
                    yAxisID: 'y1'
                });
            }
            if (sensorType === 'all' || sensorType === 'humidity') {
                timeDatasets.push({
                    label: 'Độ ẩm không khí trung bình (%)',
                    data: groupedData.map(entry => entry.avg_humidity),
                    backgroundColor: 'rgba(75, 192, 192, 0.5)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1,
                    type: 'line',
                    yAxisID: 'y1'
                });
            }
            if (sensorType === 'all' || sensorType === 'water_level') {
                timeDatasets.push({
                    label: 'Mực nước trung bình (cm)',
                    data: groupedData.map(entry => entry.avg_water_level),
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1,
                    type: 'line',
                    yAxisID: 'y2'
                });
            }
            if (sensorType === 'all' || sensorType === 'rain') {
                timeDatasets.push({
                    label: 'Tỷ lệ mưa (%)',
                    data: groupedData.map(entry => entry.rain_percentage),
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1,
                    type: 'line',
                    yAxisID: 'y1'
                });
            }
            
            const timeCtx = document.getElementById('timeChart')?.getContext('2d');
            if (timeCtx) {
                timeChart = new Chart(timeCtx, {
                    type: 'bar',
                    data: {
                        labels: timeLabels,
                        datasets: timeDatasets
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            title: {
                                display: true,
                                text: `Thống kê cảm biến theo thời gian (${gardenData[0]?.name || 'Vườn'})`
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) label += ': ';
                                        label += context.parsed.y.toFixed(1);
                                        if (context.dataset.label.includes('Nhiệt độ') && context.parsed.y > 35) {
                                            label += ' (Cảnh báo: Nhiệt độ cao)';
                                        } else if (context.dataset.label.includes('Độ ẩm đất') && context.parsed.y < 20) {
                                            label += ' (Cảnh báo: Độ ẩm đất thấp)';
                                        }
                                        return label;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: { display: true, text: 'Nhiệt độ (°C)' }
                            },
                            y1: {
                                position: 'right',
                                beginAtZero: true,
                                max: 100,
                                title: { display: true, text: 'Độ ẩm/Tỷ lệ mưa (%)' },
                                grid: { drawOnChartArea: false }
                            },
                            y2: {
                                position: 'right',
                                beginAtZero: true,
                                title: { display: true, text: 'Mực nước (cm)' },
                                grid: { drawOnChartArea: false }
                            }
                        }
                    }
                });
            }
        } else {
            console.warn('Không có dữ liệu thống kê theo tháng để vẽ biểu đồ thời gian');
            showError('Không có dữ liệu thống kê theo tháng cho vườn đã chọn.');
        }
    }

    function updateStatsTable(gardenData) {
        const tableBody = document.getElementById('statsTable');
        if (tableBody) {
            tableBody.innerHTML = '';
            gardenData.forEach(garden => {
                tableBody.innerHTML += `
                    <tr>
                        <td>${garden.name || 'Không xác định'}</td>
                        <td>${(parseFloat(garden.avg_temperature) || 0).toFixed(1)}</td>
                        <td>${(parseFloat(garden.max_temperature) || 0).toFixed(1)}</td>
                        <td>${(parseFloat(garden.min_temperature) || 0).toFixed(1)}</td>
                        <td>${(parseFloat(garden.avg_soil_moisture) || 0).toFixed(1)}</td>
                        <td>${(parseFloat(garden.max_soil_moisture) || 0).toFixed(1)}</td>
                        <td>${(parseFloat(garden.min_soil_moisture) || 0).toFixed(1)}</td>
                        <td>${(parseFloat(garden.avg_humidity) || 0).toFixed(1)}</td>
                        <td>${(parseFloat(garden.max_humidity) || 0).toFixed(1)}</td>
                        <td>${(parseFloat(garden.min_humidity) || 0).toFixed(1)}</td>
                        <td>${(parseFloat(garden.avg_water_level) || 0).toFixed(1)}</td>
                        <td>${(parseFloat(garden.max_water_level) || 0).toFixed(1)}</td>
                        <td>${(parseFloat(garden.min_water_level) || 0).toFixed(1)}</td>
                        <td>${(parseFloat(garden.rain_percentage) || 0).toFixed(1)}</td>
                    </tr>
                `;
            });
        }
    }

    function showError(message) {
        let errorElement = document.getElementById('error-message');
        if (!errorElement) {
            errorElement = document.createElement('div');
            errorElement.id = 'error-message';
            errorElement.className = 'alert alert-danger';
            errorElement.style.position = 'fixed';
            errorElement.style.top = '20px';
            errorElement.style.right = '20px';
            errorElement.style.zIndex = '1000';
            document.body.appendChild(errorElement);
        }
        
        errorElement.textContent = message;
        errorElement.style.display = 'block';
        
        setTimeout(() => {
            errorElement.style.display = 'none';
        }, 5000);
    }

    window.logout = function() {
        const token = localStorage.getItem('accessToken');
        if (token) {
            fetch('http://192.168.1.123/SmartGarden/backend-api/routes/home.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`
                },
                body: JSON.stringify({
                    action: 'logout',
                    token: token
                })
            }).finally(() => {
                localStorage.removeItem('accessToken');
                localStorage.removeItem('isAdmin');
                localStorage.removeItem('currentUserId');
                window.location.href = 'login.html';
            });
        } else {
            localStorage.removeItem('accessToken');
            localStorage.removeItem('isAdmin');
            localStorage.removeItem('currentUserId');
            window.location.href = 'login.html';
        }
    };
});
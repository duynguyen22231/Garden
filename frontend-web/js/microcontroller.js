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

// Get token from localStorage
function getToken() {
    const token = localStorage.getItem('accessToken');
    if (!token) {
        alert("Vui lòng đăng nhập lại!");
        window.location.href = "/SmartGarden/frontend-web/pages/login.html";
        return null;
    }
    return token;
}

// Get current user ID and admin status
function getUserInfo() {
    return {
        isAdmin: localStorage.getItem('isAdmin') === 'true',
        currentUserId: localStorage.getItem('currentUserId')
    };
}

// Load garden list for dropdown
function loadGardenList() {
    const token = getToken();
    if (!token) return;

    const { isAdmin, currentUserId } = getUserInfo();

    fetch('http://192.168.1.123/SmartGarden/backend-api/routes/garden.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`,
            'Is-Admin': isAdmin.toString(),
            'Current-User-Id': currentUserId || ''
        },
        body: JSON.stringify({ action: 'get_all_gardens' })
    })
    .then(res => {
        if (!res.ok) {
            throw new Error(`Lỗi HTTP: ${res.status} - ${res.statusText}`);
        }
        return res.json();
    })
    .then(data => {
        if (data.success) {
            const addGardenSelect = document.getElementById('add-garden-id');
            const editGardenSelect = document.getElementById('edit-garden-id');
            if (addGardenSelect && editGardenSelect) {
                addGardenSelect.innerHTML = '<option value="">Chọn vườn</option>';
                editGardenSelect.innerHTML = '<option value="">Chọn vườn</option>';
                data.data.forEach(garden => {
                    const optionAdd = document.createElement('option');
                    optionAdd.value = garden.id;
                    optionAdd.textContent = garden.garden_names || `Vườn ${garden.id}`;
                    addGardenSelect.appendChild(optionAdd);

                    const optionEdit = document.createElement('option');
                    optionEdit.value = garden.id;
                    optionEdit.textContent = garden.garden_names || `Vườn ${garden.id}`;
                    editGardenSelect.appendChild(optionEdit);
                });
            }
        } else {
            console.error('Lỗi khi tải danh sách vườn:', data.message);
            alert('Không thể tải danh sách vườn: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Lỗi:', error);
        alert('Không thể tải danh sách vườn: ' + error.message);
    });
}

// Load component status from API with garden names
async function loadComponentStatus() {
    const token = getToken();
    if (!token) return;

    const { isAdmin, currentUserId } = getUserInfo();
    
    try {
        const response = await fetch('http://192.168.1.123/SmartGarden/backend-api/routes/microcontrollers.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`,
                'Is-Admin': isAdmin.toString(),
                'Current-User-Id': currentUserId || ''
            },
            body: JSON.stringify({
                action: 'getMicrocontrollerStatus',
                isAdmin: isAdmin,
                userId: currentUserId ? parseInt(currentUserId) : null
            })
        });

        if (!response.ok) {
            throw new Error(`Lỗi HTTP: ${response.status} - ${response.statusText}`);
        }

        const data = await response.json();
        console.log('API Response:', data);
        if (data.success) {
            if (data.data.length === 0) {
                showErrorMessage('Không có vi điều khiển nào để hiển thị. Vui lòng kiểm tra quyền truy cập hoặc thêm vi điều khiển.');
                return;
            }
            const gardenIds = [...new Set(data.data.map(comp => comp.garden_id))];
            const gardenResponse = await fetch('http://192.168.1.123/SmartGarden/backend-api/routes/garden.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`,
                    'Is-Admin': isAdmin.toString(),
                    'Current-User-Id': currentUserId || ''
                },
                body: JSON.stringify({ 
                    action: 'get_gardens_by_ids',
                    ids: gardenIds.join(',')
                })
            });

            if (!gardenResponse.ok) {
                throw new Error(`Lỗi HTTP: ${gardenResponse.status} - ${gardenResponse.statusText}`);
            }

            const gardenData = await gardenResponse.json();
            console.log('Garden Data:', gardenData);
            if (gardenData.success) {
                const gardenMap = gardenData.data.reduce((map, garden) => {
                    map[garden.id] = garden.garden_names || `Vườn ${garden.id}`;
                    return map;
                }, {});
                updateComponentList(data.data, gardenMap);
            } else {
                throw new Error(gardenData.message || 'Không thể tải tên vườn');
            }
        } else {
            throw new Error(data.message || 'Không thể tải danh sách vi điều khiển');
        }
    } catch (error) {
        console.error('Lỗi tải trạng thái vi điều khiển:', error);
        showErrorMessage('Lỗi: ' + error.message);
    }
}

// Update component list UI with garden names
function updateComponentList(components, gardenMap) {
    const componentList = document.getElementById('component-list');
    if (!componentList) {
        console.error('Không tìm thấy phần tử #component-list');
        return;
    }

    if (components.length === 0) {
        componentList.innerHTML = '<div class="col-12"><p class="text-muted text-center">Không có vi điều khiển nào.</p></div>';
        return;
    }

    componentList.innerHTML = components.map(component => {
        const gardenName = gardenMap[component.garden_id] || 'Không xác định';
        const statusClass = component.status === 'online' ? 'text-success' : 'text-danger';
        const statusText = component.status === 'online' ? 'Online' : 'Offline';

        return `
            <div class="col-4">
                <div class="card h-100 component-card">
                    <div class="card-body text-center">
                        <i class="bi bi-cpu text-info"></i>
                        <h6 class="card-title">${component.name || 'Không có tên'}</h6>
                        <p class="card-text">ID: ${component.mcu_id}</p>
                        <p class="card-text">Vườn: ${gardenName}</p>
                        <p class="card-text">IP: ${component.ip_address || 'Không xác định'}</p>
                        <p class="card-text component-status ${statusClass}">${statusText}</p>
                        <p class="text-muted small">Cập nhật: ${component.last_seen ? new Date(component.last_seen).toLocaleString('vi-VN') : '--'}</p>
                        <p class="text-muted small">Tạo: ${component.created_at ? new Date(component.created_at).toLocaleString('vi-VN') : '--'}</p>
                        <div class="action-buttons">
                            <button class="btn btn-warning btn-sm" onclick='openEditModal(${JSON.stringify(component)})'>Sửa</button>
                            <button class="btn btn-danger btn-sm" onclick="deleteComponent('${component.mcu_id}', ${component.garden_id})">Xóa</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

// Function to update Arduino IP from device
function updateArduinoIp(mcuId, ipAddress) {
    const token = getToken();
    if (!token) return;

    const { isAdmin, currentUserId } = getUserInfo();

    fetch('http://192.168.1.123/SmartGarden/backend-api/routes/microcontrollers.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`,
            'Is-Admin': isAdmin.toString(),
            'Current-User-Id': currentUserId || ''
        },
        body: JSON.stringify({ 
            action: 'update_ip',
            mcu_id: mcuId,
            ip_address: ipAddress
        })
    })
    .then(res => {
        if (!res.ok) throw new Error(`Lỗi HTTP: ${res.status} - ${res.statusText}`);
        return res.json();
    })
    .then(data => {
        if (data.success) {
            console.log('Cập nhật IP thành công:', data.message);
            loadComponentStatus();
        } else {
            console.error('Lỗi cập nhật IP:', data.message);
            showErrorMessage('Lỗi cập nhật IP: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Lỗi khi cập nhật IP:', error);
        showErrorMessage('Lỗi: ' + error.message);
    });
}

// Show error message
function showErrorMessage(message) {
    const componentList = document.getElementById('component-list');
    if (componentList) {
        componentList.innerHTML = `<div class="alert alert-danger text-center">${message}</div>`;
    }
}

// Check API availability
async function checkAPI() {
    const token = getToken();
    if (!token) return;

    const { isAdmin, currentUserId } = getUserInfo();

    try {
        const response = await fetch('http://192.168.1.123/SmartGarden/backend-api/routes/microcontrollers.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`,
                'Is-Admin': isAdmin.toString(),
                'Current-User-Id': currentUserId || ''
            },
            body: JSON.stringify({
                action: 'check_api'
            })
        });

        if (!response.ok) {
            throw new Error(`Lỗi HTTP: ${response.status} - ${response.statusText}`);
        }

        const data = await response.json();
        if (!data.success) {
            throw new Error(data.message || 'Không thể kiểm tra trạng thái API');
        }
    } catch (error) {
        console.error('Lỗi kiểm tra API:', error);
        showErrorMessage('Lỗi: Không thể kết nối đến máy chủ');
    }
}

// Add component with garden permission check
function addComponent() {
    const name = document.getElementById('add-name').value.trim();
    const garden_id = document.getElementById('add-garden-id').value;
    const ip_address = document.getElementById('add-ip-address').value.trim();
    const status = document.getElementById('add-status').value;
    const mcu_id = 'mcu_' + Date.now(); // Tạo mcu_id tự động
    const errorMessage = document.getElementById('add-error-message');
    const { isAdmin, currentUserId } = getUserInfo();

    if (!name || !garden_id || !ip_address || !status) {
        errorMessage.textContent = 'Vui lòng điền đầy đủ thông tin';
        errorMessage.classList.remove('d-none');
        return;
    }
    if (!/^\d+$/.test(garden_id) || garden_id <= 0) {
        errorMessage.textContent = 'ID vườn không hợp lệ';
        errorMessage.classList.remove('d-none');
        return;
    }
    if (!/^(\d{1,3}\.){3}\d{1,3}$/.test(ip_address)) {
        errorMessage.textContent = 'Địa chỉ IP không hợp lệ';
        errorMessage.classList.remove('d-none');
        return;
    }

    const token = getToken();
    if (!token) return;

    fetch('http://192.168.1.123/SmartGarden/backend-api/routes/microcontrollers.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`,
            'Is-Admin': isAdmin.toString(),
            'Current-User-Id': currentUserId || ''
        },
        body: JSON.stringify({ 
            action: 'addMicrocontroller',
            name,
            garden_id: parseInt(garden_id),
            ip_address,
            status,
            mcu_id,
            isAdmin,
            userId: currentUserId ? parseInt(currentUserId) : null
        })
    })
    .then(res => {
        if (!res.ok) throw new Error(`Lỗi HTTP: ${res.status} - ${res.statusText}`);
        return res.json();
    })
    .then(data => {
        if (data.success) {
            const modal = bootstrap.Modal.getInstance(document.getElementById('addComponentModal'));
            modal.hide();
            document.getElementById('add-name').value = '';
            document.getElementById('add-garden-id').value = '';
            document.getElementById('add-ip-address').value = '';
            document.getElementById('add-status').value = 'online';
            errorMessage.classList.add('d-none');
            loadComponentStatus();
        } else {
            errorMessage.textContent = data.message || 'Thêm vi điều khiển thất bại';
            errorMessage.classList.remove('d-none');
        }
    })
    .catch(error => {
        console.error('Lỗi thêm vi điều khiển:', error);
        errorMessage.textContent = 'Lỗi: ' + error.message;
        errorMessage.classList.remove('d-none');
    });
}

// Open edit modal
function openEditModal(component) {
    if (!component || !component.mcu_id) {
        alert('Dữ liệu vi điều khiển không hợp lệ');
        return;
    }

    document.getElementById('edit-mcu-id').value = component.mcu_id;
    document.getElementById('edit-name').value = component.name;
    document.getElementById('edit-garden-id').value = component.garden_id;
    document.getElementById('edit-ip-address').value = component.ip_address;
    document.getElementById('edit-status').value = component.status;
    document.getElementById('edit-error-message').classList.add('d-none');

    const modal = new bootstrap.Modal(document.getElementById('editComponentModal'));
    modal.show();
}

// Update component with permission check
function updateComponent() {
    const mcu_id = document.getElementById('edit-mcu-id').value;
    const name = document.getElementById('edit-name').value.trim();
    const garden_id = document.getElementById('edit-garden-id').value;
    const ip_address = document.getElementById('edit-ip-address').value.trim();
    const status = document.getElementById('edit-status').value;
    const errorMessage = document.getElementById('edit-error-message');
    const { isAdmin, currentUserId } = getUserInfo();

    if (!mcu_id || !name || !garden_id || !ip_address || !status) {
        errorMessage.textContent = 'Vui lòng điền đầy đủ thông tin';
        errorMessage.classList.remove('d-none');
        return;
    }
    if (!/^\d+$/.test(garden_id) || garden_id <= 0) {
        errorMessage.textContent = 'ID vườn không hợp lệ';
        errorMessage.classList.remove('d-none');
        return;
    }
    if (!/^(\d{1,3}\.){3}\d{1,3}$/.test(ip_address)) {
        errorMessage.textContent = 'Địa chỉ IP không hợp lệ';
        errorMessage.classList.remove('d-none');
        return;
    }

    const token = getToken();
    if (!token) return;

    fetch('http://192.168.1.123/SmartGarden/backend-api/routes/microcontrollers.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`,
            'Is-Admin': isAdmin.toString(),
            'Current-User-Id': currentUserId || ''
        },
        body: JSON.stringify({ 
            action: 'updateMicrocontroller',
            mcu_id,
            name,
            garden_id: parseInt(garden_id),
            ip_address,
            status,
            isAdmin,
            userId: currentUserId ? parseInt(currentUserId) : null
        })
    })
    .then(res => {
        if (!res.ok) throw new Error(`Lỗi HTTP: ${res.status} - ${res.statusText}`);
        return res.json();
    })
    .then(data => {
        if (data.success) {
            const modal = bootstrap.Modal.getInstance(document.getElementById('editComponentModal'));
            modal.hide();
            errorMessage.classList.add('d-none');
            loadComponentStatus();
        } else {
            errorMessage.textContent = data.message || 'Cập nhật vi điều khiển thất bại';
            errorMessage.classList.remove('d-none');
        }
    })
    .catch(error => {
        console.error('Lỗi cập nhật vi điều khiển:', error);
        errorMessage.textContent = 'Lỗi: ' + error.message;
        errorMessage.classList.remove('d-none');
    });
}

// Delete component with permission check
function deleteComponent(mcu_id, garden_id) {
    if (!confirm('Bạn có chắc muốn xóa vi điều khiển này?')) return;

    const token = getToken();
    if (!token) return;

    const { isAdmin, currentUserId } = getUserInfo();

    fetch('http://192.168.1.123/SmartGarden/backend-api/routes/microcontrollers.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`,
            'Is-Admin': isAdmin.toString(),
            'Current-User-Id': currentUserId || ''
        },
        body: JSON.stringify({ 
            action: 'deleteMicrocontroller',
            mcu_id,
            garden_id: parseInt(garden_id),
            isAdmin,
            userId: currentUserId ? parseInt(currentUserId) : null
        })
    })
    .then(res => {
        if (!res.ok) throw new Error(`Lỗi HTTP: ${res.status} - ${res.statusText}`);
        return res.json();
    })
    .then(data => {
        if (data.success) {
            loadComponentStatus();
        } else {
            alert(data.message || 'Xóa vi điều khiển thất bại');
        }
    })
    .catch(error => {
        console.error('Lỗi xóa vi điều khiển:', error);
        alert('Lỗi: ' + error.message);
    });
}

// Hàm đăng xuất
function logout() {
    localStorage.removeItem("accessToken");
    localStorage.removeItem("isAdmin");
    localStorage.removeItem("currentUserId");
    alert("Đăng xuất thành công!");
    window.location.href = "/SmartGarden/frontend-web/pages/login.html";
}

// Khởi tạo khi trang tải
window.onload = function() {
    setActiveNavLink();
    checkAPI();
    loadGardenList();
    loadComponentStatus();
    setInterval(loadComponentStatus, 10000); 
};
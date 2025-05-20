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

// Load component status from API
function loadComponentStatus() {
    fetch('http://localhost/SmartGarden/backend-api/routes/microcontrollers.php?action=status')
        .then(res => {
            if (!res.ok) {
                throw new Error(`Lỗi HTTP: ${res.status} - ${res.statusText}`);
            }
            return res.json();
        })
        .then(data => {
            if (data.success) {
                updateComponentList(data.data);
            } else {
                showErrorMessage(data.message || 'Không thể tải danh sách vi điều khiển');
            }
        })
        .catch(error => {
            console.error('Lỗi tải danh sách vi điều khiển:', error);
            showErrorMessage('Lỗi: ' + error.message);
        });
}

// Update component list UI
function updateComponentList(components) {
    const componentList = document.getElementById('component-list');
    if (!componentList) return;

    componentList.innerHTML = components.length === 0 
        ? '<div class="col-12"><p class="text-muted text-center">Không có vi điều khiển nào.</p></div>'
        : components.map(component => {
            const statusClass = component.status === 'online' ? 'text-success' : 'text-danger';
            const statusText = component.status === 'online' ? 'Online' : 'Offline';

            return `
                <div class="col-4">
                    <div class="card h-100 component-card">
                        <div class="card-body text-center">
                            <i class="bi bi-cpu text-info"></i>
                            <h6 class="card-title">${component.name}</h6>
                            <p class="card-text">ID: ${component.mcu_id}</p>
                            <p class="card-text">Vườn: ${component.garden_id}</p>
                            <p class="card-text">IP: ${component.ip_address}</p>
                            <p class="card-text component-status ${statusClass}">${statusText}</p>
                            <p class="text-muted small">Cập nhật: ${component.last_seen ? new Date(component.last_seen).toLocaleString() : '--'}</p>
                            <div class="action-buttons">
                                <button class="btn btn-warning btn-sm" onclick='openEditModal(${JSON.stringify(component)})'>Sửa</button>
                                <button class="btn btn-danger btn-sm" onclick="deleteComponent('${component.mcu_id}')">Xóa</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
}

// Show error message
function showErrorMessage(message) {
    const componentList = document.getElementById('component-list');
    if (componentList) {
        componentList.innerHTML = `<div class="alert alert-danger text-center">${message}</div>`;
    }
}

// Check API availability
function checkAPI() {
    fetch('http://localhost/SmartGarden/backend-api/routes/microcontrollers.php?action=status')
        .then(res => {
            if (!res.ok) {
                console.error('API không khả dụng');
                showErrorMessage('Không thể kết nối đến máy chủ');
            }
        })
        .catch(error => {
            console.error('Lỗi kiểm tra API:', error);
            showErrorMessage('Lỗi: ' + error.message);
        });
}

// Add component
function addComponent() {
    const name = document.getElementById('add-name').value.trim();
    const garden_id = document.getElementById('add-garden-id').value;
    const ip_address = document.getElementById('add-ip-address').value.trim();
    const status = document.getElementById('add-status').value;
    const errorMessage = document.getElementById('add-error-message');

    if (!name || !garden_id || !ip_address || !status) {
        errorMessage.textContent = 'Vui lòng điền đầy đủ thông tin';
        errorMessage.classList.remove('d-none');
        return;
    }
    if (!/^\d+$/.test(garden_id) || garden_id <= 0) {
        errorMessage.textContent = 'ID vườn phải là số dương';
        errorMessage.classList.remove('d-none');
        return;
    }
    if (!/^(\d{1,3}\.){3}\d{1,3}$/.test(ip_address)) {
        errorMessage.textContent = 'Địa chỉ IP không hợp lệ';
        errorMessage.classList.remove('d-none');
        return;
    }

    fetch('http://localhost/SmartGarden/backend-api/routes/microcontrollers.php?action=add', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, garden_id, ip_address, status })
    })
    .then(res => {
        if (!res.ok) throw new Error(`Lỗi HTTP: ${res.status}`);
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

// Update component
function updateComponent() {
    const mcu_id = document.getElementById('edit-mcu-id').value;
    const name = document.getElementById('edit-name').value.trim();
    const garden_id = document.getElementById('edit-garden-id').value;
    const ip_address = document.getElementById('edit-ip-address').value.trim();
    const status = document.getElementById('edit-status').value;
    const errorMessage = document.getElementById('edit-error-message');

    if (!mcu_id || !name || !garden_id || !ip_address || !status) {
        errorMessage.textContent = 'Vui lòng điền đầy đủ thông tin';
        errorMessage.classList.remove('d-none');
        return;
    }
    if (!/^\d+$/.test(garden_id) || garden_id <= 0) {
        errorMessage.textContent = 'ID vườn phải là số dương';
        errorMessage.classList.remove('d-none');
        return;
    }
    if (!/^(\d{1,3}\.){3}\d{1,3}$/.test(ip_address)) {
        errorMessage.textContent = 'Địa chỉ IP không hợp lệ';
        errorMessage.classList.remove('d-none');
        return;
    }

    fetch('http://localhost/SmartGarden/backend-api/routes/microcontrollers.php?action=update', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ mcu_id, name, garden_id, ip_address, status })
    })
    .then(res => {
        if (!res.ok) throw new Error(`Lỗi HTTP: ${res.status}`);
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

// Delete component
function deleteComponent(mcu_id) {
    if (!confirm('Bạn có chắc muốn xóa vi điều khiển này?')) return;

    fetch('http://localhost/SmartGarden/backend-api/routes/microcontrollers.php?action=delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ mcu_id })
    })
    .then(res => {
        if (!res.ok) throw new Error(`Lỗi HTTP: ${res.status}`);
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

// Initialize when page loads
window.onload = function() {
    setActiveNavLink();
    checkAPI();
    loadComponentStatus();
    setInterval(loadComponentStatus, 30000); // Cập nhật mỗi 30 giây
};
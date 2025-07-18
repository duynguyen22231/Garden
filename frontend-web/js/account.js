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

// Get user info
function getUserInfo() {
    return {
        isAdmin: localStorage.getItem('isAdmin') === 'true',
        currentUserId: localStorage.getItem('currentUserId')
    };
}

// Load account status from API
function loadUserStatus() {
    const token = getToken();
    if (!token) return;

    const { isAdmin, currentUserId } = getUserInfo();
    const permissionError = document.getElementById('permission-error');
    const accountList = document.getElementById('account-list');
    const addUserBtn = document.getElementById('add-user-btn');

    if (!isAdmin) {
        permissionError.classList.remove('d-none');
        addUserBtn.style.display = 'none';
    } else {
        permissionError.classList.add('d-none');
        addUserBtn.style.display = 'inline-block';
    }

    fetch('http://192.168.1.123/SmartGarden/backend-api/routes/account.php?action=status', {
        headers: { 
            'Authorization': `Bearer ${token}`,
            'Is-Admin': isAdmin.toString(),
            'Current-User-Id': currentUserId
        },
        method: 'POST'
    })
        .then(res => {
            if (!res.ok) {
                throw new Error(`Lỗi HTTP: ${res.status} - ${res.statusText}`);
            }
            const contentType = res.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                throw new Error('Phản hồi từ server không phải JSON');
            }
            return res.json();
        })
        .then(data => {
            if (data.success) {
                let users = data.data;
                updateUserList(users);
            } else {
                showErrorMessage(data.message);
            }
        })
        .catch(error => {
            console.error('Lỗi:', error);
            showErrorMessage(error.message);
        });
}

// Update account list UI
function updateUserList(users) {
    const userList = document.getElementById('account-list');
    userList.innerHTML = '';

    users.forEach(user => {
        const { isAdmin, currentUserId } = getUserInfo();
        const roleText = user.administrator_rights ? 'Admin' : 'Người Dùng';
        const imgSrc = user.img_user ? `data:image/jpeg;base64,${user.img_user}` : '';
        const isCurrentUser = user.id == currentUserId;
        const card = `
            <div class="col-4">
                <div class="card h-100 user-card">
                    <div class="card-background">
                        ${imgSrc ? `<img src="${imgSrc}" alt="${user.username}'s avatar">` : ''}
                    </div>
                    <div class="card-body text-center">
                        <h6 class="card-title">${user.username}</h6>
                        <p class="card-text user-role">Vai trò: ${roleText}</p>
                        <p class="card-text">Email: ${user.email}</p>
                        <p class="card-text">Họ tên: ${user.full_name}</p>
                        <p class="text-muted small">Tham gia: ${new Date(user.created_at).toLocaleString()}</p>
                        <div class="action-buttons">
                            ${isCurrentUser || isAdmin ? `
                                <button class="btn btn-warning btn-sm" onclick='openEditModal(${JSON.stringify(user)})'>Sửa</button>
                                ${isAdmin && !isCurrentUser ? `<button class="btn btn-danger btn-sm" onclick="deleteUser(${user.id})">Xóa</button>` : ''}
                            ` : ''}
                        </div>
                    </div>
                </div>
            </div>
        `;
        userList.innerHTML += card;
    });
}

function showErrorMessage(message) {
    const userList = document.getElementById('account-list');
    userList.innerHTML = `<div class="alert alert-danger text-center">${message}</div>`;
}

// Check API availability
function checkAPI() {
    const token = getToken();
    if (!token) return;

    fetch('http://192.168.1.123/SmartGarden/backend-api/routes/account.php?action=status', {
        headers: { 'Authorization': `Bearer ${token}` },
        method: 'POST'
    })
        .then(res => {
            if (!res.ok) {
                console.error('API không khả dụng');
                const contentType = res.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    throw new Error('Phản hồi từ server không phải JSON');
                }
                return res.json().then(data => {
                    throw new Error(data.message || 'Không thể kết nối đến máy chủ');
                });
            }
        })
        .catch(error => {
            console.error('Lỗi kiểm tra API:', error);
            showErrorMessage(error.message);
        });
}

// Add user
function addUser() {
    const { isAdmin } = getUserInfo();
    if (!isAdmin) {
        alert("Bạn không có quyền thực hiện hành động này!");
        return;
    }

    const formData = new FormData(document.getElementById('add-user-form'));
    const errorMessage = document.getElementById('add-error-message');
    const imgUser = document.getElementById('add-img-user');

    if (imgUser.files.length > 0) {
        const file = imgUser.files[0];
        const validImageTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!validImageTypes.includes(file.type)) {
            errorMessage.textContent = 'Vui lòng chọn tệp ảnh (JPEG, PNG, GIF).';
            errorMessage.classList.remove('d-none');
            return;
        }
        formData.append('img_user', file);
    }

    const token = getToken();
    if (!token) return;

    fetch('http://192.168.1.123/SmartGarden/backend-api/routes/account.php?action=add', {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${token}` },
        body: formData
    })
    .then(res => {
        const contentType = res.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            throw new Error('Phản hồi từ server không phải JSON');
        }
        return res.json();
    })
    .then(data => {
        if (data.success) {
            const modal = bootstrap.Modal.getInstance(document.getElementById('addUserModal'));
            modal.hide();
            document.getElementById('addUserModal').addEventListener('hidden.bs.modal', function () {
                document.querySelector('button[data-bs-target="#addUserModal"]').focus();
            }, { once: true });
            loadUserStatus();
        } else {
            errorMessage.textContent = data.message;
            errorMessage.classList.remove('d-none');
        }
    })
    .catch(error => {
        errorMessage.textContent = 'Lỗi: ' + error.message;
        errorMessage.classList.remove('d-none');
    });
}

// Open edit modal
function openEditModal(user) {
    const { isAdmin, currentUserId } = getUserInfo();
    if (!isAdmin && user.id != currentUserId) {
        alert("Bạn chỉ có thể sửa thông tin của chính mình!");
        return;
    }

    if (!user.id) {
        console.error('Lỗi: user.id không tồn tại', user);
        alert('Không thể mở modal sửa vì ID người dùng không hợp lệ.');
        return;
    }

    document.getElementById('edit-id').value = user.id;
    document.getElementById('edit-username').value = user.username || '';
    document.getElementById('edit-email').value = user.email || '';
    document.getElementById('edit-password').value = '';
    document.getElementById('edit-admin-rights').value = user.administrator_rights ? 1 : 0;
    document.getElementById('edit-admin-rights').disabled = !isAdmin; // Disable admin rights for non-admins
    document.getElementById('edit-full-name').value = user.full_name || '';
    document.getElementById('edit-error-message').classList.add('d-none');

    const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
    modal.show();
}

// Update user
function updateUser() {
    const { isAdmin, currentUserId } = getUserInfo();
    const formData = new FormData(document.getElementById('edit-user-form'));
    const errorMessage = document.getElementById('edit-error-message');
    const imgUser = document.getElementById('edit-img-user');
    const id = formData.get('id');

    if (!isAdmin && id != currentUserId) {
        errorMessage.textContent = 'Bạn chỉ có thể chỉnh sửa thông tin của chính mình!';
        errorMessage.classList.remove('d-none');
        return;
    }

    if (imgUser.files.length > 0) {
        const file = imgUser.files[0];
        const validImageTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!validImageTypes.includes(file.type)) {
            errorMessage.textContent = 'Vui lòng chọn tệp ảnh (JPEG, PNG, GIF).';
            errorMessage.classList.remove('d-none');
            return;
        }
        formData.append('img_user', file);
    }

    if (!id) {
        errorMessage.textContent = 'Lỗi: ID người dùng không được gửi.';
        errorMessage.classList.remove('d-none');
        return;
    }

    const token = getToken();
    if (!token) return;

    fetch('http://192.168.1.123/SmartGarden/backend-api/routes/account.php?action=update', {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${token}` },
        body: formData
    })
    .then(res => {
        const contentType = res.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            throw new Error('Phản hồi từ server không phải JSON');
        }
        return res.json();
    })
    .then(data => {
        if (data.success) {
            const modal = bootstrap.Modal.getInstance(document.getElementById('editUserModal'));
            modal.hide();
            document.getElementById('editUserModal').addEventListener('hidden.bs.modal', function () {
                const editButtons = document.querySelectorAll('.btn-warning');
                editButtons.forEach(button => {
                    if (button.getAttribute('onclick').includes(formData.get('id'))) {
                        button.focus();
                    }
                });
            }, { once: true });
            loadUserStatus();
        } else {
            errorMessage.textContent = data.message;
            errorMessage.classList.remove('d-none');
        }
    })
    .catch(error => {
        errorMessage.textContent = 'Lỗi: ' + error.message;
        errorMessage.classList.remove('d-none');
    });
}

// Delete user
function deleteUser(id) {
    const { isAdmin } = getUserInfo();
    if (!isAdmin) {
        alert("Bạn không có quyền thực hiện hành động này!");
        return;
    }

    if (!confirm('Bạn có chắc muốn xóa người dùng này?')) return;

    const token = getToken();
    if (!token) return;

    fetch('http://192.168.1.123/SmartGarden/backend-api/routes/account.php?action=delete', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({ id })
    })
    .then(res => {
        const contentType = res.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            throw new Error('Phản hồi từ server không phải JSON');
        }
        return res.json();
    })
    .then(data => {
        if (data.success) {
            loadUserStatus();
        } else {
            alert(data.message);
        }
    })
    .catch(error => {
        alert('Lỗi: ' + error.message);
    });
}

// Initialize when page loads
window.onload = function() {
    setActiveNavLink();
    checkAPI();
    loadUserStatus();
    setInterval(loadUserStatus, 30000);
};

// Logout function
function logout() {
    localStorage.removeItem("accessToken");
    localStorage.removeItem("isAdmin");
    localStorage.removeItem("currentUserId");
    alert("Đăng xuất thành công!");
    window.location.href = "/SmartGarden/frontend-web/pages/login.html";
}
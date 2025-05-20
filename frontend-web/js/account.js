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

// Load account status from API
function loadUserStatus() {
    fetch('http://localhost/SmartGarden/backend-api/routes/account.php?action=status')
        .then(res => {
            if (!res.ok) {
                throw new Error(`Lỗi HTTP: ${res.status} - ${res.statusText}`);
            }
            return res.json();
        })
        .then(data => {
            if (data.success) {
                updateUserList(data.data);
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
        const roleText = user.administrator_rights ? 'Admin' : 'Người Dùng';
        const imgSrc = user.img_user ? `data:image/jpeg;base64,${user.img_user}` : '';
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
                        <p class="text-muted small">Tham gia: ${user.created_at}</p>
                        <div class="action-buttons">
                            <button class="btn btn-warning btn-sm" onclick='openEditModal(${JSON.stringify(user)})'>Sửa</button>
                            <button class="btn btn-danger btn-sm" onclick="deleteUser(${user.id})">Xóa</button>
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
    fetch('http://localhost/SmartGarden/backend-api/routes/account.php?action=status')
        .then(res => {
            if (!res.ok) {
                console.error('API không khả dụng');
                showErrorMessage('Không thể kết nối đến máy chủ');
            }
        })
        .catch(error => {
            console.error('Lỗi kiểm tra API:', error);
            showErrorMessage(error.message);
        });
}

// Add user
function addUser() {
    const formData = new FormData(document.getElementById('add-user-form'));
    const errorMessage = document.getElementById('add-error-message');
    const imgUser = document.getElementById('add-img-user');

    // Kiểm tra định dạng ảnh
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

    fetch('http://localhost/SmartGarden/backend-api/routes/account.php?action=add', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
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
    document.getElementById('edit-full-name').value = user.full_name || '';
    document.getElementById('edit-error-message').classList.add('d-none');

    const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
    modal.show();
}

// Update user
function updateUser() {
    const formData = new FormData(document.getElementById('edit-user-form'));
    const errorMessage = document.getElementById('edit-error-message');
    const imgUser = document.getElementById('edit-img-user');

    // Kiểm tra định dạng ảnh
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

    // Kiểm tra xem id có được gửi không
    const id = formData.get('id');
    if (!id) {
        errorMessage.textContent = 'Lỗi: ID người dùng không được gửi.';
        errorMessage.classList.remove('d-none');
        return;
    }

    fetch('http://localhost/SmartGarden/backend-api/routes/account.php?action=update', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
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
    if (!confirm('Bạn có chắc muốn xóa người dùng này?')) return;

    fetch('http://localhost/SmartGarden/backend-api/routes/account.php?action=delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    })
    .then(res => res.json())
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
    setInterval(loadUserStatus, 30000); // Cập nhật mỗi 30 giây
};
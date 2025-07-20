document.addEventListener("DOMContentLoaded", () => {
    const token = localStorage.getItem("accessToken");
    const isAdmin = localStorage.getItem("isAdmin") === "true";
    const currentUserId = localStorage.getItem("currentUserId");

    if (!token) {
        alert("Vui lòng đăng nhập lại!");
        window.location.href = "/SmartGarden/frontend-web/pages/login.html";
        return;
    }

    loadGardens(isAdmin, currentUserId);
    loadStatusOptions();
    loadUsers(isAdmin);
    setupImagePreview();

    const searchInput = document.getElementById("searchInput");
    if (searchInput) {
        searchInput.addEventListener("input", (e) => {
            const query = e.target.value.trim();
            if (query) searchGardens(query, isAdmin, currentUserId);
            else loadGardens(isAdmin, currentUserId);
        });
    }

    const gardenForm = document.getElementById("gardenForm");
    if (gardenForm) {
        gardenForm.addEventListener("submit", (e) => {
            e.preventDefault();
            const formData = new FormData(gardenForm);
            const id = formData.get("id");
            formData.append("action", id ? "update_garden" : "save_garden");
            if (!isAdmin) formData.set("user_id", currentUserId);

            apiRequest(formData, (data) => {
                alert(data.message);
                if (data.success) {
                    gardenForm.reset();
                    document.getElementById("id").value = "";
                    document.getElementById("imagePreview").style.display = "none";
                    closeEditPopup();
                    loadGardens(isAdmin, currentUserId);
                }
            });
        });
    }
});

function saveToken(token) {
    localStorage.setItem('accessToken', token);
}

function getToken() {
    return localStorage.getItem('accessToken');
}

function apiRequest(formData, callback) {
    const token = getToken();
    if (!token) {
        alert("Vui lòng đăng nhập lại!");
        window.location.href = "/SmartGarden/frontend-web/pages/login.html";
        return;
    }

    fetch("http://192.168.1.123/SmartGarden/backend-api/routes/garden.php", {
        method: "POST",
        headers: {
            'Authorization': `Bearer ${token}`
        },
        body: formData
    })
    .then(async res => {
        const text = await res.text();
        try {
            const data = JSON.parse(text);
            if (!data.success) {
                if (data.message === 'Chưa đăng nhập' || data.message === 'Phiên đăng nhập không hợp lệ') {
                    alert("Phiên đăng nhập hết hạn. Vui lòng đăng nhập lại!");
                    localStorage.removeItem('accessToken');
                    window.location.href = "/SmartGarden/frontend-web/pages/login.html";
                } else {
                    alert(data.message);
                }
            } else {
                callback(data);
            }
        } catch (e) {
            console.error("Phản hồi không phải JSON:", text);
            alert("Đã có lỗi xảy ra!\n" + text);
        }
    })
    .catch(err => {
        console.error("Lỗi kết nối đến API:", err);
        alert("Không thể kết nối đến máy chủ!");
    });
}

function loadStatusOptions() {
    const formData = new FormData();
    formData.append("action", "get_status_options");

    apiRequest(formData, (data) => {
        if (data.success) {
            const select = document.getElementById("statusSelect");
            select.innerHTML = '<option value="">Chọn trạng thái</option>';
            data.data.forEach((option) => {
                const opt = document.createElement("option");
                opt.value = option.id;
                opt.textContent = option.name;
                select.appendChild(opt);
            });
        }
    });
}

function loadUsers(isAdmin) {
    const currentUserId = localStorage.getItem("currentUserId");
    if (!isAdmin) {
        const userSelect = document.getElementById("user_id");
        if (userSelect) {
            userSelect.value = currentUserId;
            userSelect.disabled = true;
        }
        return;
    }

    const formData = new FormData();
    formData.append("action", "get_users");

    apiRequest(formData, (data) => {
        if (data.success) {
            const select = document.getElementById("user_id");
            select.innerHTML = '<option value="">Chọn người dùng</option>';
            data.data.forEach((user) => {
                const opt = document.createElement("option");
                opt.value = user.id;
                opt.textContent = user.full_name;
                select.appendChild(opt);
            });
        }
    });
}

function setupImagePreview() {
    const fileInput = document.getElementById("img");
    const preview = document.getElementById("imagePreview");
    if (fileInput && preview) {
        fileInput.addEventListener("change", () => {
            const file = fileInput.files[0];
            if (file) {
                if (!file.type.startsWith('image/')) {
                    alert("Vui lòng chọn một tệp hình ảnh hợp lệ (JPEG, PNG, GIF).");
                    fileInput.value = "";
                    preview.src = "";
                    preview.style.display = 'none';
                    return;
                }
                if (file.size > 5 * 1024 * 1024) {
                    alert("Kích thước ảnh không được vượt quá 5MB.");
                    fileInput.value = "";
                    preview.src = "";
                    preview.style.display = 'none';
                    return;
                }
                const reader = new FileReader();
                reader.onload = (e) => {
                    preview.src = e.target.result;
                    preview.style.display = "block";
                };
                reader.readAsDataURL(file);
            } else {
                preview.src = "";
                preview.style.display = "none";
            }
        });
    }
}

function loadGardens(isAdmin, currentUserId) {
    const formData = new FormData();
    formData.append("action", "get_gardens");

    // Gọi home.php thay vì garden.php để lấy danh sách vườn với img_url
    fetch("http://192.168.1.123/SmartGarden/backend-api/routes/home.php", {
        method: "POST",
        headers: {
            "Authorization": `Bearer ${getToken()}`,
            "Content-Type": "application/x-www-form-urlencoded"
        },
        body: new URLSearchParams({ action: "get_gardens" })
    })
    .then(async res => {
        const text = await res.text();
        try {
            const data = JSON.parse(text);
            if (data.success) {
                displayGardens(data.gardens, isAdmin, currentUserId);
            } else {
                alert(data.message || "Không thể tải danh sách vườn!");
            }
        } catch (e) {
            console.error("Phản hồi không phải JSON:", text);
            alert("Đã có lỗi xảy ra khi tải danh sách vườn!");
        }
    })
    .catch(err => {
        console.error("Lỗi kết nối đến API:", err);
        alert("Không thể kết nối đến máy chủ!");
    });
}

function searchGardens(query, isAdmin, currentUserId) {
    const formData = new FormData();
    formData.append("action", "search_gardens");
    formData.append("search", query);

    apiRequest(formData, (data) => {
        if (data.success) {
            displayGardens(data.data, isAdmin, currentUserId);
        }
    });
}

async function getGardenImageBlobUrl(gardenId) {
    try {
        const token = localStorage.getItem("accessToken");
        const res = await fetch("http://192.168.1.123/SmartGarden/backend-api/routes/garden.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
                "Authorization": `Bearer ${token}`
            },
            body: new URLSearchParams({
                action: "get_garden_image",
                id: gardenId
            })
        });
        if (!res.ok) {
            if (res.status === 404) {
                console.warn(`Không tìm thấy ảnh cho vườn ${gardenId}`);
                return '';
            }
            console.error(`Lỗi lấy ảnh cho vườn ${gardenId}: HTTP ${res.status}`);
            return '';
        }
        const blob = await res.blob();
        if (blob.type.startsWith('image/')) {
            return URL.createObjectURL(blob);
        } else {
            const text = await res.text();
            console.error(`Phản hồi không phải ảnh cho vườn ${gardenId}: ${text}`);
            return '';
        }
    } catch (err) {
        console.error(`Lỗi khi lấy ảnh cho vườn ${gardenId}:`, err);
        return '';
    }
}

    async function displayGardens(gardens, isAdmin, currentUserId) {
    const tableBody = document.getElementById("gardenTableBody");
    if (!tableBody) return;

    tableBody.innerHTML = "";

    for (const garden of gardens) {
        if (!isAdmin && garden.user_id != currentUserId) continue;

        const imgSrc = garden.img_url || (garden.img_id ? await getGardenImageBlobUrl(garden.img_id) : '');
        const row = document.createElement("tr");

        row.innerHTML = `
            <td>${garden.id}</td>
            <td>${garden.garden_names}</td>
            <td>${garden.location}</td>
            <td>${imgSrc ? `<img src="${imgSrc}" alt="Ảnh vườn" width="80" onerror="this.style.display='none';this.nextSibling.style.display='block';" /><span style="display:none;">Không có ảnh</span>` : "Không có ảnh"}</td>
            <td>${garden.note || ''}</td>
            <td>${garden.area} m²</td>
            <td>${garden.status}</td>
            <td>${garden.owner_name}</td>
            <td>
                <button class="btn btn-sm btn-primary" onclick="editGarden(${garden.id})">
                    <i class="bi bi-pencil-square"></i>
                </button>
                <button class="btn btn-sm btn-danger" onclick="deleteGarden(${garden.id})">
                    <i class="bi bi-trash-fill"></i>
                </button>
                <button class="btn btn-sm btn-info" onclick="window.location.href='sensor.html?garden_id=${garden.id}'">
                    <i class="bi bi-eye-fill"></i>
                </button>
            </td>
        `;

        tableBody.appendChild(row);
    }
}

async function editGarden(gardenId) {
    const isAdmin = localStorage.getItem("isAdmin") === "true";
    const currentUserId = localStorage.getItem("currentUserId");

    const formData = new FormData();
    formData.append("action", "get_garden_by_id");
    formData.append("id", gardenId);

    apiRequest(formData, async (data) => {
        if (!data.success) {
            alert(data.message || "Không thể lấy thông tin vườn!");
            return;
        }

        const garden = data.data;

        if (!isAdmin && garden.user_id != currentUserId) {
            alert("Bạn không có quyền sửa vườn này!");
            return;
        }

        document.getElementById("id").value = garden.id;
        document.getElementById("name").value = garden.garden_names;
        document.getElementById("location").value = garden.location;
        document.getElementById("latitude").value = garden.latitude;
        document.getElementById("longitude").value = garden.longitude;
        document.getElementById("area").value = garden.area;
        document.getElementById("note").value = garden.note || "";
        document.getElementById("user_id").value = isAdmin ? garden.user_id : currentUserId;
        document.getElementById("statusSelect").value = garden.status;

        const preview = document.getElementById("imagePreview");
        const imgSrc = garden.img ? 'data:image/jpeg;base64,' + garden.img : (garden.img_id ? await getGardenImageBlobUrl(garden.img_id) : '');
        if (imgSrc) {
            preview.src = imgSrc;
            preview.style.display = "block";
        } else {
            preview.src = "";
            preview.style.display = "none";
        }

        document.getElementById("editGardenModal").classList.remove("d-none");
    });
}

function closeEditPopup() {
    document.getElementById("editGardenModal").classList.add("d-none");
    const preview = document.getElementById("imagePreview");
    preview.src = "";
    preview.style.display = "none";
}

function deleteGarden(id) {
    const isAdmin = localStorage.getItem("isAdmin") === "true";
    const currentUserId = localStorage.getItem("currentUserId");

    const formData = new FormData();
    formData.append("action", "get_garden_by_id");
    formData.append("id", id);

    apiRequest(formData, (data) => {
        if (!data.success || (!isAdmin && data.data.user_id != currentUserId)) {
            alert("Bạn không có quyền xóa vườn này!");
            return;
        }

        if (!confirm("Bạn có chắc chắn muốn xóa vườn này không?")) return;

        const deleteFormData = new FormData();
        deleteFormData.append("action", "delete_garden");
        deleteFormData.append("id", id);

        apiRequest(deleteFormData, (data) => {
            alert(data.message);
            if (data.success) loadGardens(isAdmin, currentUserId);
        });
    });
}

let map;
let marker;

function openMapModal() {
    const lat = parseFloat(document.getElementById("latitude").value) || 10.762622;
    const lng = parseFloat(document.getElementById("longitude").value) || 106.660172;
    const modal = new bootstrap.Modal(document.getElementById("mapModal"));
    modal.show();

    setTimeout(() => {
        if (!map) {
            map = L.map("leafletMap").setView([lat, lng], 13);

            L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
                attribution: "© OpenStreetMap contributors",
            }).addTo(map);

            marker = L.marker([lat, lng], { draggable: true }).addTo(map);

            marker.on("dragend", (e) => {
                const { lat, lng } = marker.getLatLng();
                updateLatLngInputs(lat, lng);
            });

            map.on("click", (e) => {
                const { lat, lng } = e.latlng;
                marker.setLatLng([lat, lng]);
                updateLatLngInputs(lat, lng);
            });
        } else {
            map.setView([lat, lng], 13);
            marker.setLatLng([lat, lng]);
        }

        updateLatLngInputs(lat, lng);
    }, 300);
}

function updateLatLngInputs(lat, lng) {
    document.getElementById("latitude").value = lat.toFixed(6);
    document.getElementById("longitude").value = lng.toFixed(6);
    document.getElementById("latPreview").innerText = lat.toFixed(6);
    document.getElementById("lngPreview").innerText = lng.toFixed(6);
}

function logout() {
    localStorage.removeItem("accessToken");
    localStorage.removeItem("isAdmin");
    localStorage.removeItem("currentUserId");
    alert("Đăng xuất thành công!");
    window.location.href = "/SmartGarden/frontend-web/pages/login.html";
}
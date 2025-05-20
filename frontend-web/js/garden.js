document.addEventListener("DOMContentLoaded", () => {
    loadGardens();
    loadStatusOptions();
    loadUsers();
    setupImagePreview();

    const searchInput = document.getElementById("searchInput");
    if (searchInput) {
        searchInput.addEventListener("input", (e) => {
            const query = e.target.value.trim();
            if (query) searchGardens(query);
            else loadGardens();
        });
    }

    const gardenForm = document.getElementById("gardenForm");
    if (gardenForm) {
        gardenForm.addEventListener("submit", (e) => {
            e.preventDefault();
            const formData = new FormData(gardenForm);
            const id = formData.get("id");
            formData.append("action", id ? "update_garden" : "save_garden");

            apiRequest(formData, (data) => {
                alert(data.message);
                if (data.success) {
                    gardenForm.reset();
                    document.getElementById("id").value = "";
                    document.getElementById("imagePreview").style.display = "none";
                    closeEditPopup();
                    loadGardens();
                }
            });
        });
    }
});

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

function loadUsers() {
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

    fileInput.addEventListener("change", () => {
        const file = fileInput.files[0];
        if (file) {
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

function apiRequest(formData, callback) {
    fetch("http://localhost/SmartGarden/backend-api/routes/garden.php", {
        method: "POST",
        body: formData,
    })
    .then(async res => {
        const text = await res.text();
        try {
            const data = JSON.parse(text);
            callback(data);
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

function loadGardens() {
    const formData = new FormData();
    formData.append("action", "get_gardens");

    apiRequest(formData, (data) => {
        if (data.success) {
            displayGardens(data.data);
        } else {
            alert(data.message);
        }
    });
}

function searchGardens(query) {
    const formData = new FormData();
    formData.append("action", "search_gardens");
    formData.append("search", query);

    apiRequest(formData, (data) => {
        if (data.success) {
            displayGardens(data.data);
        } else {
            alert(data.message);
        }
    });
}

function displayGardens(gardens) {
    const tableBody = document.getElementById("gardenTableBody");
    if (!tableBody) return;

    tableBody.innerHTML = "";

    gardens.forEach((garden) => {
        const imgSrc = garden.img ? `data:image/jpeg;base64,${garden.img}` : ""; // Hiển thị ảnh từ base64
        const row = document.createElement("tr");

        row.innerHTML = `
            <td>${garden.id}</td>
            <td>${garden.garden_names}</td>
            <td>${garden.location}</td>
            <td style="display: none;">${garden.latitude}, ${garden.longitude}</td>
            <td>${imgSrc ? `<img src="${imgSrc}" alt="Ảnh" width="80" />` : "Không có ảnh"}</td>
            <td>${garden.note}</td>
            <td>${garden.area} m²</td>
            <td style="display: none;">${garden.created_at}</td>
            <td>${garden.status}</td>
            <td>${garden.owner_name}</td>
            <td>
                <button class="btn btn-sm btn-primary" onclick='editGarden(${JSON.stringify(garden)})'>
                    <i class="bi bi-pencil-square"></i>
                </button>
                <button class="btn btn-sm btn-danger" onclick="deleteGarden(${garden.id})">
                    <i class="bi bi-trash-fill"></i>
                </button>
                <button class="btn btn-sm btn-info" onclick="window.location.href='sensor.html?id=${garden.id}'">
                    <i class="bi bi-eye-fill"></i>
                </button>
            </td>
        `;

        tableBody.appendChild(row);
    });
}

function editGarden(garden) {
    document.getElementById("id").value = garden.id;
    document.getElementById("name").value = garden.garden_names;
    document.getElementById("location").value = garden.location;
    document.getElementById("latitude").value = garden.latitude;
    document.getElementById("longitude").value = garden.longitude;
    document.getElementById("area").value = garden.area;
    document.getElementById("note").value = garden.note || "";
    document.getElementById("user_id").value = garden.user_id;
    document.getElementById("statusSelect").value = garden.status;

    const preview = document.getElementById("imagePreview");
    const imgSrc = garden.img ? `data:image/jpeg;base64,${garden.img}` : "";
    if (garden.img) {
        preview.src = imgSrc;
        preview.style.display = "block";
    } else {
        preview.src = "";
        preview.style.display = "none";
    }

    document.getElementById("editGardenModal").classList.remove("d-none");
}

function closeEditPopup() {
    document.getElementById("editGardenModal").classList.add("d-none");
    const preview = document.getElementById("imagePreview");
    preview.src = "";
    preview.style.display = "none";
}

function deleteGarden(id) {
    if (!confirm("Bạn có chắc chắn muốn xóa vườn này không?")) return;

    const formData = new FormData();
    formData.append("action", "delete_garden");
    formData.append("id", id);

    apiRequest(formData, (data) => {
        alert(data.message);
        if (data.success) loadGardens();
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
document.addEventListener("DOMContentLoaded", async () => {
    const token = localStorage.getItem("accessToken");
    console.log("Retrieved token from localStorage:", token ? token.substring(0, 20) + "..." : "null");
    if (!token) {
        console.warn("No token found, redirecting to login");
        window.location.href = "/SmartGarden/frontend-web/pages/login.html";
        return;
    }

    try {
        const res = await fetch("http://localhost/SmartGarden/backend-api/routes/home.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Authorization": `Bearer ${token}`,
                "Accept": "application/json"
            },
            body: JSON.stringify({ action: "check_login_status", token: token })
        });
        console.log("check_login_status response status:", res.status);
        let data;
        try {
            data = await res.json();
        } catch (e) {
            console.error("Failed to parse JSON response:", e.message);
            data = { success: false, message: "Lỗi server: Phản hồi không phải JSON" };
        }
        console.log("check_login_status response data:", data);

        if (!data.success) {
            console.error("check_login_status failed:", data.message);
            localStorage.removeItem('accessToken');
            localStorage.removeItem('isAdmin');
            localStorage.removeItem('currentUserId');
            alert("Phiên đăng nhập không hợp lệ: " + (data.message || "Không rõ nguyên nhân"));
            window.location.href = "/SmartGarden/frontend-web/pages/login.html";
            return;
        }

        const isAdmin = data.data.user.administrator_rights === 1;
        localStorage.setItem('isAdmin', isAdmin);
        localStorage.setItem('currentUserId', data.data.user.id);
        console.log("Login status verified, isAdmin:", isAdmin, "userId:", data.data.user.id);

        // Kiểm tra xem Leaflet (L) có được định nghĩa không
        if (typeof L !== 'undefined') {
            initMap();
        } else {
            console.error("Leaflet library not loaded. Map functionality disabled.");
            alert("Không thể tải thư viện bản đồ. Vui lòng kiểm tra kết nối hoặc thử lại sau.");
        }
        loadUsers(isAdmin);
        loadGardens();
        setupImagePreview(isAdmin);
        setupFormHandlers(isAdmin);
        initChart();
    } catch (err) {
        console.error("Error in check_login_status:", err.message);
        localStorage.removeItem('accessToken');
        localStorage.removeItem('isAdmin');
        localStorage.removeItem('currentUserId');
        alert("Lỗi kết nối khi kiểm tra đăng nhập: " + err.message);
        window.location.href = "/SmartGarden/frontend-web/pages/login.html";
    }
});

let currentGardenId = null;
let map, addMode = false, tempMarker = null, realMarker = null;

function initMap() {
    map = L.map("map").setView([10.0125, 105.0809], 12);
    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png").addTo(map);
    addAddGardenButton();
    setupMapMouseEvents();
    setupMapClickEvent();
}

function addAddGardenButton() {
    const addIcon = L.control({ position: "topleft" });
    addIcon.onAdd = () => {
        const div = L.DomUtil.create("div", "map-button");
        div.innerHTML = "🌱";
        div.title = "Chọn vị trí vườn cây";
        L.DomEvent.disableClickPropagation(div);
        div.onclick = () => {
            addMode = !addMode;
            div.style.backgroundColor = addMode ? "#28a745" : "";
            if (!addMode && tempMarker) {
                map.removeLayer(tempMarker);
                tempMarker = null;
            }
        };
        return div;
    };
    addIcon.addTo(map);
}

function setupMapMouseEvents() {
    map.on("mousemove", e => {
        if (!addMode) return;
        if (!tempMarker) {
            tempMarker = L.circleMarker(e.latlng, { color: "green", radius: 3, fillOpacity: 1 }).addTo(map);
        } else {
            tempMarker.setLatLng(e.latlng);
        }
    });
}

function setupMapClickEvent() {
    map.on("click", e => {
        if (!addMode) return;
        const lat = e.latlng.lat.toFixed(6);
        const lng = e.latlng.lng.toFixed(6);
        if (tempMarker) {
            map.removeLayer(tempMarker);
            tempMarker = null;
        }
        map.flyTo([lat, lng], 16, { animate: true });
        if (realMarker) map.removeLayer(realMarker);
        realMarker = L.marker([lat, lng]).addTo(map);

        if (typeof Android !== "undefined") {
            Android.selectLocation(lat, lng);
        }

        const popup = document.getElementById("gardenFormPopup");
        if (popup) {
            popup.classList.add("show");
            document.getElementById("map")?.classList.add("popup-active");
            document.getElementById("latitude").value = lat;
            document.getElementById("longitude").value = lng;
            document.getElementById("garden_names").value = "";
            document.getElementById("location").value = "";
            document.getElementById("area").value = "";
            document.getElementById("note").value = "";
            document.getElementById("image_url").value = "";
            document.getElementById("imagePreview").style.display = 'none';
            const ownerSelect = document.getElementById("owner_name");
            if (ownerSelect) ownerSelect.value = "";
        } else {
            console.error("Không tìm thấy phần tử gardenFormPopup");
        }

        addMode = false;
        document.querySelector(".map-button").style.backgroundColor = "";
        map.off("mousemove");
        map.off("click");
        setupMapMouseEvents();
        setupMapClickEvent();
    });
}

async function loadSensorData(garden_id = currentGardenId) {
    const sensorDataDiv = document.getElementById("sensorData");
    if (!sensorDataDiv) return;

    sensorDataDiv.innerHTML = `<p class="text-muted">Đang tải dữ liệu...</p>`;
    try {
        const token = localStorage.getItem("accessToken");
        const body = new URLSearchParams({ action: "get_sensor_data" });
        if (garden_id) body.append("garden_id", garden_id);
        const res = await fetch("http://localhost/SmartGarden/backend-api/routes/home.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
                "Authorization": `Bearer ${token}`
            },
            body: body
        });
        let data;
        try {
            data = await res.json();
        } catch (e) {
            console.error("Failed to parse JSON response for sensor data:", e);
            data = { success: false, message: "Lỗi phản hồi JSON từ server" };
        }
        sensorDataDiv.innerHTML = "";
        if (garden_id && data.success && data.data[garden_id]) {
            const sensor = data.data[garden_id];
            sensorDataDiv.innerHTML = `
                <div>
                    <p><strong>Nhiệt độ:</strong> <span id="temperature">${sensor.temperature || '--'} °C</span></p>
                    <p><strong>Độ ẩm đất:</strong> <span id="soilMoisture">${sensor.soil_moisture || '--'} %</span></p>
                    <p><strong>Độ ẩm không khí:</strong> <span id="humidity">${sensor.humidity || '--'} %</span></p>
                    <p><strong>Trạng thái tưới:</strong> <span id="irrigationStatus">${sensor.irrigation ? "Đang tưới" : "Chưa tưới"}</span></p>
                    <button id="toggleIrrigation" class="btn btn-outline-${sensor.irrigation ? "danger" : "success"} btn-sm">
                        ${sensor.irrigation ? "Tắt tưới" : "Bật tưới"}
                    </button>
                </div>
            `;
            document.getElementById("toggleIrrigation")?.addEventListener("click", toggleIrrigation);
        } else {
            sensorDataDiv.innerHTML = `<p class="text-muted">${data.message || "Không có dữ liệu cảm biến cho vườn này."}</p>`;
        }
    } catch (err) {
        console.error("Lỗi khi tải dữ liệu cảm biến:", err);
        sensorDataDiv.innerHTML = `<p class="text-danger">Lỗi tải dữ liệu cảm biến: ${err.message}</p>`;
    }
}

let envChart;

function initChart() {
    const ctx = document.getElementById("envChart")?.getContext("2d");
    if (!ctx) return;
    envChart = new Chart(ctx, {
        type: "line",
        data: {
            labels: [],
            datasets: [
                { label: "Nhiệt độ (°C)", data: [], borderColor: "#ff5722", fill: false },
                { label: "Độ ẩm đất (%)", data: [], borderColor: "#2196f3", fill: false },
                { label: "Độ ẩm không khí (%)", data: [], borderColor: "#4caf50", fill: false }
            ]
        },
        options: {
            responsive: true,
            scales: {
                x: { title: { display: true, text: "Thời gian" } },
                y: { title: { display: true, text: "Giá trị" } }
            }
        }
    });
}

async function loadChartData(garden_id = currentGardenId) {
    const chartContainer = document.getElementById("envChart")?.parentElement;
    if (!envChart || !chartContainer) return;

    try {
        const token = localStorage.getItem("accessToken");
        const body = new URLSearchParams({ action: "get_chart_data" });
        if (garden_id) body.append("garden_id", garden_id);
        const res = await fetch("http://localhost/SmartGarden/backend-api/routes/home.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
                "Authorization": `Bearer ${token}`
            },
            body: body
        });
        let data;
        try {
            data = await res.json();
        } catch (e) {
            console.error("Failed to parse JSON response for chart data:", e);
            data = { success: false, message: "Lỗi phản hồi JSON từ server" };
        }
        if (data.success && garden_id && data.data[garden_id]) {
            envChart.data.labels = data.data[garden_id].labels || [];
            envChart.data.datasets[0].data = data.data[garden_id].temperature || [];
            envChart.data.datasets[1].data = data.data[garden_id].soil_moisture || [];
            envChart.data.datasets[2].data = data.data[garden_id].humidity || [];
            envChart.update();
            chartContainer.querySelector(".no-data")?.remove();
        } else {
            envChart.data.labels = [];
            envChart.data.datasets.forEach(dataset => dataset.data = []);
            envChart.update();
            if (!chartContainer.querySelector(".no-data")) {
                chartContainer.insertAdjacentHTML("beforeend", `<p class="no-data text-muted text-center">${data.message || "Không có dữ liệu biểu đồ cho vườn này."}</p>`);
            }
        }
    } catch (err) {
        console.error("Lỗi khi tải dữ liệu biểu đồ:", err);
        envChart.data.labels = [];
        envChart.data.datasets.forEach(dataset => dataset.data = []);
        envChart.update();
        if (!chartContainer.querySelector(".no-data")) {
            chartContainer.insertAdjacentHTML("beforeend", `<p class="no-data text-danger text-center">Lỗi tải dữ liệu biểu đồ: ${err.message}</p>`);
        }
    }
}

async function loadAlerts(garden_id = currentGardenId) {
    const alertsList = document.getElementById("alertsList");
    if (!alertsList) return;

    alertsList.innerHTML = `<li class="list-group-item">Đang tải cảnh báo...</li>`;
    try {
        const token = localStorage.getItem("accessToken");
        const body = new URLSearchParams({ action: "get_alerts" });
        if (garden_id) body.append("garden_id", garden_id);
        const res = await fetch("http://localhost/SmartGarden/backend-api/routes/home.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
                "Authorization": `Bearer ${token}`
            },
            body: body
        });
        let data;
        try {
            data = await res.json();
        } catch (e) {
            console.error("Failed to parse JSON response for alerts:", e);
            data = { success: false, message: "Lỗi phản hồi JSON từ server" };
        }
        alertsList.innerHTML = "";
        if (data.success && garden_id && data.data[garden_id]) {
            data.data[garden_id].forEach(alert => {
                const li = document.createElement("li");
                li.className = `list-group-item list-group-item-${alert.severity || 'warning'}`;
                li.textContent = `${alert.message} (${new Date(alert.timestamp).toLocaleString()})`;
                alertsList.appendChild(li);
            });
            if (!data.data[garden_id].length) {
                alertsList.innerHTML = `<li class="list-group-item">Không có cảnh báo cho vườn này.</li>`;
            }
        } else {
            alertsList.innerHTML = `<li class="list-group-item">${data.message || "Không có cảnh báo."}</li>`;
        }
    } catch (err) {
        console.error("Lỗi khi tải cảnh báo:", err);
        alertsList.innerHTML = `<li class="list-group-item text-danger">Lỗi tải cảnh báo: ${err.message}</li>`;
    }
}

async function toggleIrrigation() {
    if (!currentGardenId) {
        alert("Vui lòng chọn một vườn để điều khiển tưới!");
        return;
    }
    try {
        const token = localStorage.getItem("accessToken");
        const res = await fetch("http://localhost/SmartGarden/backend-api/routes/home.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
                "Authorization": `Bearer ${token}`
            },
            body: new URLSearchParams({
                action: "toggle_irrigation",
                garden_id: currentGardenId
            })
        });
        let data;
        try {
            data = await res.json();
        } catch (e) {
            console.error("Failed to parse JSON response for irrigation:", e);
            data = { success: false, message: "Lỗi phản hồi JSON từ server" };
        }
        if (data.success) {
            loadSensorData(currentGardenId);
        } else {
            alert("Lỗi khi điều khiển tưới: " + (data.message || "Lỗi không xác định"));
        }
    } catch (err) {
        console.error("Lỗi khi điều khiển tưới:", err);
        alert("Không thể điều khiển tưới: " + err.message);
    }
}

async function loadUsers(isAdmin) {
    if (!isAdmin) {
        const ownerSelect = document.getElementById("owner_name");
        if (ownerSelect) ownerSelect.parentElement.style.display = 'none';
        return;
    }

    try {
        const token = localStorage.getItem("accessToken");
        const res = await fetch("http://localhost/SmartGarden/backend-api/routes/home.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
                "Authorization": `Bearer ${token}`
            },
            body: new URLSearchParams({ action: "get_users" })
        });
        let data;
        try {
            data = await res.json();
        } catch (e) {
            console.error("Failed to parse JSON response for users:", e);
            data = { success: false, message: "Lỗi phản hồi JSON từ server" };
        }
        if (data.success && Array.isArray(data.users)) {
            const select = document.getElementById("owner_name");
            if (select) {
                select.innerHTML = '<option value="">Chọn chủ vườn</option>';
                data.users.forEach(user => {
                    const option = document.createElement("option");
                    option.value = user.id;
                    option.textContent = user.full_name || `User ${user.id}`;
                    select.appendChild(option);
                });
            }
        } else {
            console.warn("loadUsers: Không có người dùng hoặc lỗi:", data.message);
            const select = document.getElementById("owner_name");
            if (select) {
                select.innerHTML = '<option value="">Không có người dùng nào</option>';
                alert("Không thể tải danh sách người dùng: " + (data.message || "Lỗi không xác định"));
            }
        }
    } catch (err) {
        console.error("Lỗi tải danh sách người dùng:", err);
        const select = document.getElementById("owner_name");
        if (select) {
            select.innerHTML = '<option value="">Không tải được</option>';
            alert("Lỗi kết nối khi tải danh sách người dùng: " + err.message);
        }
    }
}

async function loadGardens() {
    try {
        const token = localStorage.getItem("accessToken");
        const res = await fetch("http://localhost/SmartGarden/backend-api/routes/home.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
                "Authorization": `Bearer ${token}`
            },
            body: new URLSearchParams({ action: "get_gardens" })
        });
        let data;
        try {
            data = await res.json();
        } catch (e) {
            console.error("Failed to parse JSON response for gardens:", e);
            data = { success: false, message: "Lỗi phản hồi JSON từ server" };
        }
        if (data.success && Array.isArray(data.gardens)) {
            const gardenSelect = document.createElement("select");
            gardenSelect.id = "gardenSelect";
            gardenSelect.className = "form-control mb-3";
            gardenSelect.innerHTML = '<option value="">Tất cả vườn</option>';
            data.gardens.forEach(g => {
                const option = document.createElement("option");
                option.value = g.id;
                option.textContent = g.garden_names;
                gardenSelect.appendChild(option);
            });

            const mapContainer = document.getElementById("map");
            if (mapContainer && mapContainer.parentNode) {
                mapContainer.parentNode.insertBefore(gardenSelect, mapContainer);
            }

            gardenSelect.addEventListener("change", async e => {
                currentGardenId = e.target.value ? parseInt(e.target.value) : null;
                await Promise.all([
                    loadSensorData(currentGardenId),
                    loadChartData(currentGardenId),
                    loadAlerts(currentGardenId)
                ]);
                const garden = data.gardens.find(g => g.id == currentGardenId);
                if (garden) {
                    const lat = parseFloat(garden.latitude);
                    const lng = parseFloat(garden.longitude);
                    if (!isNaN(lat) && !isNaN(lng)) {
                        map.flyTo([lat, lng], 16, { animate: true });
                    }
                }
            });

            for (const g of data.gardens) {
                const lat = parseFloat(g.latitude);
                const lng = parseFloat(g.longitude);
                if (!isNaN(lat) && !isNaN(lng)) {
                    L.marker([lat, lng])
                        .addTo(map)
                        .bindPopup(`
                            <b>${g.garden_names}</b><br>
                            Chủ vườn: ${g.owner_name || 'Không rõ'}<br>
                            Địa chỉ: ${g.location || ''}<br>
                            Diện tích: ${g.area || 0} m²<br>
                            Ghi chú: ${g.note || ''}<br>
                            ${g.img_url ? `<img src="${g.img_url}" style="max-width:100px;" onerror="this.style.display='none'" />` : ''}
                        `)
                        .on('click', async () => {
                            currentGardenId = g.id;
                            gardenSelect.value = g.id;
                            await Promise.all([
                                loadSensorData(currentGardenId),
                                loadChartData(currentGardenId),
                                loadAlerts(currentGardenId)
                            ]);
                            map.flyTo([lat, lng], 16, { animate: true });
                        });
                }
            }

            if (!currentGardenId) {
                await Promise.all([
                    loadSensorData(),
                    loadChartData(),
                    loadAlerts()
                ]);
            }

            if (!data.gardens.length) {
                document.getElementById("sensorData").innerHTML = `<p class="text-muted">Không có vườn hoạt động nào.</p>`;
            }
        } else {
            const mapContainer = document.getElementById("map");
            if (mapContainer) {
                mapContainer.insertAdjacentHTML("beforebegin", `<div class="alert alert-danger">${data.message || "Không thể tải danh sách vườn."}</div>`);
            }
        }
    } catch (err) {
        console.error("Lỗi khi tải danh sách vườn:", err);
        const mapContainer = document.getElementById("map");
        if (mapContainer) {
            mapContainer.insertAdjacentHTML("beforebegin", `<div class="alert alert-danger">Lỗi kết nối server: ${err.message}</div>`);
        }
    }
}

function setupImagePreview(isAdmin) {
    const fileInput = document.getElementById("image_url");
    const preview = document.getElementById("imagePreview");
    if (fileInput && preview) {
        fileInput.addEventListener("change", () => {
            const file = fileInput.files[0];
            if (file) {
                preview.src = URL.createObjectURL(file);
                preview.style.display = 'block';
            } else {
                preview.src = "";
                preview.style.display = 'none';
            }
        });
    }
}

function setupFormHandlers(isAdmin) {
    const closePopup = document.getElementById("closePopup");
    if (closePopup) {
        closePopup.onclick = () => {
            document.getElementById("gardenFormPopup").classList.remove("show");
            document.getElementById("map")?.classList.remove("popup-active");
            if (realMarker) {
                map.removeLayer(realMarker);
                realMarker = null;
            }
            if (tempMarker) {
                map.removeLayer(tempMarker);
                tempMarker = null;
            }
            addMode = false;
            document.querySelector(".map-button").style.backgroundColor = "";
            setupMapMouseEvents();
            setupMapClickEvent();
        };
    }

    const saveGardenBtn = document.getElementById("saveGardenBtn");
    if (saveGardenBtn) {
        saveGardenBtn.onclick = async () => {
            const token = localStorage.getItem("accessToken");
            const name = document.getElementById("garden_names")?.value.trim();
            const ownerId = isAdmin ? document.getElementById("owner_name")?.value : localStorage.getItem('currentUserId');
            const address = document.getElementById("location")?.value.trim();
            const area = document.getElementById("area")?.value.trim();
            const note = document.getElementById("note")?.value.trim();
            const lat = document.getElementById("latitude")?.value;
            const lng = document.getElementById("longitude")?.value;
            const fileInput = document.getElementById("image_url");
            const imageFile = fileInput?.files[0];

            if (!name || !ownerId || !address || !area || !lat || !lng) {
                alert("Vui lòng nhập đầy đủ thông tin bắt buộc!");
                return;
            }

            const formData = new FormData();
            formData.append("action", "save_garden");
            formData.append("name", name);
            formData.append("user_id", ownerId);
            formData.append("location", address);
            formData.append("area", area);
            formData.append("note", note || "");
            formData.append("latitude", lat);
            formData.append("longitude", lng);
            if (imageFile) {
                formData.append("image", imageFile);
            }

            try {
                const res = await fetch("http://localhost/SmartGarden/backend-api/routes/home.php", {
                    method: "POST",
                    headers: { "Authorization": `Bearer ${token}` },
                    body: formData
                });
                let data;
                try {
                    data = await res.json();
                } catch (e) {
                    console.error("Failed to parse JSON response for save garden:", e);
                    data = { success: false, message: "Lỗi phản hồi JSON từ server" };
                }
                if (data.success) {
                    alert("✅ Thêm vườn thành công!");
                    window.location.reload();
                } else {
                    alert("❌ Thêm vườn thất bại!\nLý do: " + (data.message || "Lỗi không xác định"));
                }
            } catch (err) {
                console.error("Lỗi khi lưu vườn:", err);
                alert("❌ Lỗi kết nối server: " + err.message);
            }
        };
    }
}

async function logout() {
    try {
        const token = localStorage.getItem("accessToken");
        const res = await fetch("http://localhost/SmartGarden/backend-api/routes/home.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
                "Authorization": `Bearer ${token}`
            },
            body: new URLSearchParams({ action: "logout" })
        });
        let data;
        try {
            data = await res.json();
        } catch (e) {
            console.error("Failed to parse JSON response for logout:", e);
            data = { success: false, message: "Lỗi phản hồi JSON từ server" };
        }
        localStorage.removeItem("accessToken");
        localStorage.removeItem("isAdmin");
        localStorage.removeItem("currentUserId");
        sessionStorage.clear();
        window.location.href = "/SmartGarden/frontend-web/pages/login.html";
    } catch (err) {
        console.error("Lỗi khi đăng xuất:", err);
        localStorage.removeItem("accessToken");
        localStorage.removeItem("isAdmin");
        localStorage.removeItem("currentUserId");
        sessionStorage.clear();
        window.location.href = "/SmartGarden/frontend-web/pages/login.html";
    }
}
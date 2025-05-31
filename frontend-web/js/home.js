document.addEventListener("DOMContentLoaded", async () => {
    const token = localStorage.getItem("accessToken");
    console.log("Token from localStorage:", token); // Debug

    if (!token) {
        console.log("No token found, redirecting to login");
        window.location.href = "/SmartGarden/frontend-web/pages/login.html";
        return;
    }

    try {
        const res = await fetch("http://localhost/SmartGarden/backend-api/routes/home.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
                "Authorization": `Bearer ${token}`
            },
            body: new URLSearchParams({ 
                action: "check_login_status"
            })
        });
        const data = await res.json();
        console.log("Check login status response:", data); // Debug

        if (!data.success) {
            console.log("Login status check failed, redirecting to login");
            localStorage.removeItem('accessToken');
            window.location.href = "/SmartGarden/frontend-web/pages/login.html";
            return;
        }

        // Lưu trạng thái admin
        const isAdmin = data.data.user.is_admin;
        localStorage.setItem('isAdmin', isAdmin);
        localStorage.setItem('currentUserId', data.data.user.id);

        initMap();
        loadUsers(isAdmin);
        loadGardens();
        setupImagePreview(isAdmin);
        setupFormHandlers(isAdmin);
        initChart();
    } catch (err) {
        console.error("Lỗi khi kiểm tra trạng thái đăng nhập:", err);
        localStorage.removeItem('accessToken');
        window.location.href = "/SmartGarden/frontend-web/pages/login.html";
    }
});

// Biến toàn cục để lưu garden_id hiện tại
let currentGardenId = null;

// Hàm lấy ảnh qua POST và trả về blob URL
async function getGardenImageBlobUrl(gardenId) {
    try {
        const token = localStorage.getItem("accessToken");
        const res = await fetch("http://localhost/SmartGarden/backend-api/routes/home.php", {
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

// ================== Bản đồ ==================
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
        div.title = "Thêm vườn cây";
        L.DomEvent.disableClickPropagation(div);
        div.onclick = () => {
            addMode = !addMode;
            div.style.backgroundColor = addMode ? "#28a745" : "";
            if (!addMode && tempMarker) {
                map.removeLayer(tempMarker);
                tempMarker = null;
            }
            console.log("addMode:", addMode); 
        };
        return div;
    };
    addIcon.addTo(map);
}

function setupMapMouseEvents() {
    map.on("mousemove", function handler(e) {
        if (!addMode) return;
        if (!tempMarker) {
            tempMarker = L.circleMarker(e.latlng, { color: "green", radius: 3, fillOpacity: 1 }).addTo(map);
        } else {
            tempMarker.setLatLng(e.latlng);
        }
    });
}

function setupMapClickEvent() {
    map.on("click", function handler(e) {
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
        document.getElementById("latitude").value = lat;
        document.getElementById("longitude").value = lng;
        
        const popup = document.getElementById("gardenFormPopup");
        popup.classList.add("show");
        document.getElementById("map").classList.add("popup-active");
        
        map.off("mousemove");
        map.off("click");
        
        addMode = false;
        const addButton = document.querySelector(".map-button");
        if (addButton) addButton.style.backgroundColor = "";
    });
}

// ================== Dữ liệu cảm biến ==================
async function loadSensorData(garden_id = currentGardenId) {
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
        if (!res.ok) throw new Error(`HTTP error! Status: ${res.status}`);
        const data = await res.json();
        if (data.success) {
            const sensorDataDiv = document.getElementById("sensorData");
            if (sensorDataDiv) {
                sensorDataDiv.innerHTML = "";
                if (garden_id && data.data[garden_id]) {
                    const sensor = data.data[garden_id];
                    sensorDataDiv.innerHTML = `
                        <div>
                            <p><strong>Nhiệt độ:</strong> <span id="temperature">${sensor.temperature} °C</span></p>
                            <p><strong>Độ ẩm đất:</strong> <span id="soilMoisture">${sensor.soil_moisture} %</span></p>
                            <p><strong>Độ ẩm không khí:</strong> <span id="humidity">${sensor.humidity} %</span></p>
                            <p><strong>Trạng thái tưới:</strong> <span id="irrigationStatus">${sensor.irrigation ? "Đang tưới" : "Chưa tưới"}</span></p>
                            <button id="toggleIrrigation" class="btn btn-outline-${sensor.irrigation ? "danger" : "success"} btn-sm">
                                ${sensor.irrigation ? "Tắt tưới" : "Bật tưới"}
                            </button>
                        </div>
                    `;
                    document.getElementById("toggleIrrigation")?.addEventListener("click", toggleIrrigation);
                } else {
                    for (const [id, sensor] of Object.entries(data.data)) {
                        sensorDataDiv.innerHTML += `
                            <div>
                                <h5>Vườn ID: ${id}</h5>
                                <p><strong>Nhiệt độ:</strong> ${sensor.temperature} °C</p>
                                <p><strong>Độ ẩm đất:</strong> ${sensor.soil_moisture} %</p>
                                <p><strong>Độ ẩm không khí:</strong> ${sensor.humidity} %</p>
                                <p><strong>Trạng thái tưới:</strong> ${sensor.irrigation ? "Đang tưới" : "Chưa tưới"}</p>
                            </div>
                        `;
                    }
                }
            }
        } else {
            console.error("Lỗi dữ liệu cảm biến:", data.message);
        }
    } catch (err) {
        console.error("Lỗi khi tải dữ liệu cảm biến:", err);
    }
}

// ================== Biểu đồ ==================
let envChart;

function initChart() {
    const ctx = document.getElementById("envChart")?.getContext("2d");
    if (!ctx) return;
    envChart = new Chart(ctx, {
        type: "line",
        data: {
            labels: [],
            datasets: [
                {
                    label: "Nhiệt độ (°C)",
                    data: [],
                    borderColor: "#ff6384",
                    fill: false,
                },
                {
                    label: "Độ ẩm đất (%)",
                    data: [],
                    borderColor: "#36a2eb",
                    fill: false,
                },
                {
                    label: "Độ ẩm không khí (%)",
                    data: [],
                    borderColor: "#4bc0c0",
                    fill: false,
                },
            ],
        },
        options: {
            responsive: true,
            scales: {
                x: { title: { display: true, text: "Thời gian" } },
                y: { title: { display: true, text: "Giá trị" } },
            },
        },
    });
}

async function loadChartData(garden_id = currentGardenId) {
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
        if (!res.ok) throw new Error(`HTTP error! Status: ${res.status}`);
        const data = await res.json();
        if (data.success && envChart) {
            if (garden_id && data.data[garden_id]) {
                envChart.data.labels = data.data[garden_id].labels;
                envChart.data.datasets[0].data = data.data[garden_id].temperature;
                envChart.data.datasets[1].data = data.data[garden_id].soil_moisture;
                envChart.data.datasets[2].data = data.data[garden_id].humidity;
                envChart.update();
            } else {
                envChart.data.labels = [];
                envChart.data.datasets[0].data = [];
                envChart.data.datasets[1].data = [];
                envChart.data.datasets[2].data = [];
                envChart.update();
                console.log("Vui lòng chọn một vườn để xem biểu đồ");
            }
        } else {
            console.error("Lỗi dữ liệu biểu đồ:", data.message);
        }
    } catch (err) {
        console.error("Lỗi khi tải dữ liệu biểu đồ:", err);
    }
}

// ================== Cảnh báo ==================
async function loadAlerts(garden_id = currentGardenId) {
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
        if (!res.ok) throw new Error(`HTTP error! Status: ${res.status}`);
        const data = await res.json();
        const alertsList = document.getElementById("alertsList");
        if (alertsList) {
            alertsList.innerHTML = "";
            if (data.success) {
                if (garden_id && data.data[garden_id]) {
                    data.data[garden_id].forEach(alert => {
                        const li = document.createElement("li");
                        li.className = `list-group-item list-group-item-${alert.severity}`;
                        li.textContent = alert.message;
                        alertsList.appendChild(li);
                    });
                } else {
                    for (const [id, alerts] of Object.entries(data.data)) {
                        const li = document.createElement("li");
                        li.className = "list-group-item";
                        li.innerHTML = `<strong>Vườn ID: ${id}</strong>`;
                        alerts.forEach(alert => {
                            const subLi = document.createElement("li");
                            subLi.className = `list-group-item list-group-item-${alert.severity}`;
                            subLi.textContent = alert.message;
                            li.appendChild(subLi);
                        });
                        alertsList.appendChild(li);
                    };
                }
                if (alertsList.innerHTML === "") {
                    alertsList.innerHTML = `<li class="list-group-item">Không có cảnh báo</li>`;
                }
            } else {
                console.error("Lỗi dữ liệu cảnh báo:", data.message);
            }
        }
    } catch (err) {
        console.error("Lỗi khi tải cảnh báo:", err);
    }
}

// ================== Kiểm soát tưới tiêu ==================
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
        const data = await res.json();
        if (data.success) {
            loadSensorData(currentGardenId);
        } else {
            alert("Lỗi khi điều khiển tưới: " + data.message);
        }
    } catch (err) {
        console.error("Lỗi khi điều khiển tưới:", err);
        alert("Không thể điều khiển tưới!");
    }
}

// ================== Các chức năng hiện có ==================
async function loadUsers(isAdmin) {
    if (!isAdmin) {
        // Nếu không phải admin, ẩn phần chọn người dùng
        const ownerSelect = document.getElementById("owner_name");
        if (ownerSelect) {
            ownerSelect.parentElement.style.display = 'none';
        }
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
        const data = await res.json();
        if (data.success && Array.isArray(data.users)) {
            const select = document.getElementById("owner_name");
            if (select) {
                select.innerHTML = '<option value="">Chọn chủ vườn</option>';
                data.users.forEach(user => {
                    const option = document.createElement("option");
                    option.value = user.id;
                    option.textContent = user.full_name;
                    select.appendChild(option);
                });
            }
        } else {
            console.error("Lỗi dữ liệu người dùng:", data.message);
        }
    } catch (err) {
        console.error("Lỗi khi tải danh sách người dùng:", err);
    }
}

async function loadGardens() {
    try {
        const token = localStorage.getItem("accessToken");
        console.log("Gửi yêu cầu get_gardens...");
        const res = await fetch("http://localhost/SmartGarden/backend-api/routes/home.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
                "Authorization": `Bearer ${token}`
            },
            body: new URLSearchParams({ action: "get_gardens" })
        });
        console.log("Trạng thái HTTP:", res.status, res.statusText);
        if (!res.ok) throw new Error(`HTTP error! Status: ${res.status}`);
        const text = await res.text();
        console.log("Phản hồi từ API get_gardens:", text);
        if (!text) throw new Error("Phản hồi API rỗng");
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            throw new Error("Lỗi phân tích JSON: " + e.message + "\nPhản hồi: " + text);
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

            gardenSelect.addEventListener("change", async (e) => {
                currentGardenId = e.target.value ? parseInt(e.target.value) : null;
                await loadSensorData(currentGardenId);
                await loadChartData(currentGardenId);
                await loadAlerts(currentGardenId);
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
                    // Lấy blob URL cho ảnh
                    const imgSrc = g.img_url && g.img_id ? await getGardenImageBlobUrl(g.img_id) : '';
                    L.marker([lat, lng])
                        .addTo(map)
                        .bindPopup(`
                            <b>${g.garden_names}</b><br>
                            Chủ vườn: ${g.owner_name || "Không xác định"}<br>
                            Địa chỉ: ${g.location || "Không có"}<br>
                            Diện tích: ${g.area || 0} m²<br>
                            Ghi chú: ${g.note || "Không có"}<br>
                            ${imgSrc ? `<img src="${imgSrc}" style="max-width:100px;" onerror="this.style.display='none'">` : ""}
                        `)
                        .on("click", () => {
                            currentGardenId = g.id;
                            gardenSelect.value = g.id;
                            loadSensorData(currentGardenId);
                            loadChartData(currentGardenId);
                            loadAlerts(currentGardenId);
                            map.flyTo([lat, lng], 16, { animate: true });
                        });
                } else {
                    console.warn(`Vườn ${g.garden_names} (ID: ${g.id}) có tọa độ không hợp lệ: lat=${g.latitude}, lng=${g.longitude}`);
                }
            }

            if (!currentGardenId) {
                loadSensorData();
                loadAlerts();
            }

            if (data.gardens.length === 0) {
                console.log("Không có vườn hoạt động nào được tìm thấy");
            }
        } else {
            console.error("Lỗi dữ liệu vườn:", data.message || "Không có dữ liệu vườn");
        }
    } catch (err) {
        console.error("Lỗi khi tải danh sách vườn:", err);
        const mapContainer = document.getElementById("map");
        if (mapContainer) {
            const errorDiv = document.createElement("div");
            errorDiv.className = "alert alert-danger";
            errorDiv.textContent = "Không thể tải danh sách vườn. Vui lòng thử lại sau.";
            mapContainer.parentNode.insertBefore(errorDiv, mapContainer);
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
                const reader = new FileReader();
                reader.onload = e => {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(file);
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
            const popup = document.getElementById("gardenFormPopup");
            if (popup) {
                popup.classList.remove("show");
            }
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
            const addButton = document.querySelector(".map-button");
            if (addButton) addButton.style.backgroundColor = "";
            
            setupMapMouseEvents();
            setupMapClickEvent();
        };
    }

    const saveGardenBtn = document.getElementById("saveGardenBtn");
    if (saveGardenBtn) {
        saveGardenBtn.onclick = async () => {
            const token = localStorage.getItem("accessToken");
            const name = document.getElementById("garden_names")?.value.trim();
            const owner = isAdmin ? document.getElementById("owner_name")?.value : localStorage.getItem('currentUserId');
            const address = document.getElementById("location")?.value.trim();
            const area = document.getElementById("area")?.value;
            const note = document.getElementById("note")?.value.trim();
            const lat = document.getElementById("latitude")?.value;
            const lng = document.getElementById("longitude")?.value;
            const fileInput = document.getElementById("image_url");
            const imageFile = fileInput?.files[0];

            if (!name || !owner || !address || !area || !lat || !lng) {
                alert("Vui lòng nhập đầy đủ thông tin bắt buộc!");
                return;
            }

            const formData = new FormData();
            formData.append("action", "save_garden");
            formData.append("name", name);
            formData.append("owner_name", owner);
            formData.append("location", address);
            formData.append("area", area);
            formData.append("note", note);
            formData.append("latitude", lat);
            formData.append("longitude", lng);
            if (imageFile) formData.append("image", imageFile);

            try {
                const res = await fetch("http://localhost/SmartGarden/backend-api/routes/home.php", {
                    method: "POST",
                    headers: {
                        "Authorization": `Bearer ${token}`
                    },
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    alert("✅ Thêm vườn cây thành công!");
                    window.location.reload();
                } else {
                    alert("❌ Thêm vườn cây thất bại!\nLý do: " + data.message);
                }
            } catch (err) {
                console.error("Lỗi khi lưu vườn cây:", err);
                alert("❌ Lỗi khi gửi dữ liệu!");
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
        const data = await res.json();
        if (!data.success) {
            console.error("Lỗi đăng xuất từ server:", data.message);
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
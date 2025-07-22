document.addEventListener("DOMContentLoaded", function () {
    const loginForm = document.querySelector("#loginForm");
    const registerForm = document.querySelector("#registerForm");

    // Xử lý đăng nhập
    if (loginForm) {
        loginForm.addEventListener("submit", function (e) {
            e.preventDefault();

            const username = loginForm.querySelector("#username")?.value.trim();
            const password = loginForm.querySelector("#password")?.value.trim();

            if (!username || !password) {
                alert("Vui lòng nhập tên đăng nhập và mật khẩu.");
                return;
            }

            console.log("Sending login request:", { action: "login", username, password });

            fetch("../../backend-api/routes/auth.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({ action: "login", username, password })
            })
                .then(res => {
                    console.log("Response status:", res.status);
                    if (!res.ok) {
                        return res.text().then(text => {
                            throw new Error(`HTTP error! status: ${res.status}, response: ${text}`);
                        });
                    }
                    return res.json();
                })
                .then(data => {
                    console.log("Login response:", data);
                    if (data.success && data.data && data.data.token) {
                        localStorage.setItem("accessToken", data.data.token);
                        console.log("Token saved to localStorage:", localStorage.getItem("accessToken"));
                        alert(data.message || "Chào mừng bạn trở lại!");
                        const savedToken = localStorage.getItem("accessToken");
                        if (!savedToken) {
                            console.error("Token not found in localStorage after saving");
                            alert("Lỗi lưu token, vui lòng thử lại.");
                            return;
                        }
                        setTimeout(() => {
                            window.location.href = "/SmartGarden/frontend-web/pages/home.html";
                        }, 1000);
                    } else {
                        console.error("Login failed:", data.message);
                        alert(data.message || "Đăng nhập thất bại.");
                    }
                })
                .catch(err => {
                    console.error("Lỗi khi gửi yêu cầu đăng nhập:", err);
                    alert("Lỗi kết nối: " + err.message);
                });
        });
    }

    // Xử lý đăng ký
    if (registerForm) {
        registerForm.addEventListener("submit", function (e) {
            e.preventDefault();
            const fullNameInput = registerForm.querySelector("#full_name");
            const usernameInput = registerForm.querySelector("#username");
            const emailInput = registerForm.querySelector("#email");
            const passwordInput = registerForm.querySelector("#password");

            if (!fullNameInput || !usernameInput || !emailInput || !passwordInput) {
                alert("Một hoặc nhiều trường input không tồn tại. Vui lòng kiểm tra HTML.");
                return;
            }

            const full_name = fullNameInput.value.trim();
            const username = usernameInput.value.trim();
            const email = emailInput.value.trim();
            const password = passwordInput.value.trim();

            if (!full_name || !username || !email || !password) {
                alert("Vui lòng nhập đầy đủ thông tin.");
                return;
            }

            console.log("Sending register request:", { action: "register", full_name, username, email, password });

            fetch("../../backend-api/routes/auth.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({ action: "register", full_name, username, email, password })
            })
                .then(res => {
                    console.log("Response status:", res.status);
                    if (!res.ok) {
                        return res.text().then(text => {
                            throw new Error(`HTTP error! status: ${res.status}, response: ${text}`);
                        });
                    }
                    return res.json();
                })
                .then(data => {
                    console.log("Register response:", data);
                    if (data.success) {
                        alert(data.message || "Bạn đã đăng ký thành công! Hãy đăng nhập để tiếp tục.");
                        setTimeout(() => {
                            window.location.href = "login.html";
                        }, 1000);
                    } else {
                        console.error("Register failed:", data.message);
                        alert(data.message || "Đăng ký thất bại.");
                    }
                })
                .catch(err => {
                    console.error("Lỗi khi gửi yêu cầu đăng ký:", err);
                    alert("Lỗi kết nối: " + err.message);
                });
        });
    }
});
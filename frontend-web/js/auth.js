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

            fetch("../../backend-api/routes/auth.php?action=login", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({ username, password })
            })
                .then(res => res.json())
                .then(data => {
                    console.log("Login response:", data); // Debug
                    if (data.success && data.data && data.data.token) {
                        localStorage.setItem("accessToken", data.data.token);
                        console.log("Token saved to localStorage:", data.data.token); // Debug
                        alert(data.message || "Đăng nhập thành công!");
                        setTimeout(() => {
                            window.location.href = "/SmartGarden/frontend-web/pages/home.html";
                        }, 1000);
                    } else {
                        alert(data.message || "Đăng nhập thất bại.");
                    }
                })
                .catch(err => {
                    console.error("Lỗi khi gửi yêu cầu đăng nhập:", err);
                    alert("Lỗi kết nối. Vui lòng thử lại sau.");
                });
        });
    }

    // Xử lý đăng ký (giữ nguyên)
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

            fetch("../../backend-api/routes/auth.php?action=register", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({ full_name, username, email, password })
            })
                .then(res => res.json().then(data => ({ status: res.status, body: data })))
                .then(({ status, body }) => {
                    console.log("Register response:", body);
                    if (status === 201) {
                        alert(body.message || "Đăng ký thành công!");
                        setTimeout(() => {
                            window.location.href = "login.html";
                        }, 1000);
                    } else {
                        alert(body.message || "Đăng ký thất bại.");
                    }
                })
                .catch(err => {
                    console.error("Lỗi khi gửi yêu cầu đăng ký:", err);
                    alert("Lỗi kết nối. Vui lòng thử lại sau.");
                });
        });
    }
});
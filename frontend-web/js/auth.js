document.addEventListener("DOMContentLoaded", function () {
  const loginForm = document.querySelector("#loginForm");
  const registerForm = document.querySelector("#registerForm");

  // Xử lý đăng nhập
  if (loginForm) {
    loginForm.addEventListener("submit", function (e) {
      e.preventDefault();

      const username = loginForm.querySelector("#username").value.trim();
      const password = loginForm.querySelector("#password").value.trim();

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
          if (data.success) {
            localStorage.setItem("accessToken", data.token);
            alert(data.message || "Đăng nhập thành công!");
            setTimeout(() => {
              window.location.href = "/SmartGarden/frontend-web/pages/home.html"; // Sửa lại đường dẫn
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

  // Xử lý đăng ký
  if (registerForm) {
    registerForm.addEventListener("submit", function (e) {
      e.preventDefault();

      const full_name = registerForm.querySelector("#full_name").value.trim();
      const username = registerForm.querySelector("#username").value.trim();
      const email = registerForm.querySelector("#email").value.trim();
      const password = registerForm.querySelector("#password").value.trim();

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
          if (status === 201) {
            alert(body.message || "Đăng ký thành công!");
            registerForm.classList.add("hidden");
            loginForm.classList.remove("hidden");
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
<?php
// Kiểm tra sự tồn tại của tệp autoload
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    error_log("Lỗi: Không tìm thấy vendor/autoload.php tại $autoloadPath. Vui lòng chạy 'composer require phpmailer/phpmailer' trong thư mục backend-api.");
    return false;
}

// Bao gồm PHPMailer classes
require_once $autoloadPath;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Khởi tạo PHPMailer
$mail = new PHPMailer(true);

try {
    // Cấu hình server
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com'; // SMTP host của Gmail
    $mail->SMTPAuth = true;
    $mail->Username = 'your_email@gmail.com'; // Thay bằng email của bạn
    $mail->Password = 'your_app_password'; // Thay bằng mật khẩu ứng dụng
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    // Cấu hình mã hóa ký tự
    $mail->CharSet = 'UTF-8';
} catch (Exception $e) {
    error_log("Lỗi khởi tạo PHPMailer: " . $e->getMessage());
    return false;
}
?>
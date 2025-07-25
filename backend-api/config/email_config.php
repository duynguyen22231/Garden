<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'ntd8406@gmail.com'; // Thay bằng email của bạn
    $mail->Password = 'nlmd cwmn kufn dalo'; // Thay bằng App Password của bạn
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;
    $mail->CharSet = 'UTF-8';
    $mail->setFrom('ntd8406@gmail.com', 'Smart Garden');
    $mail->isHTML(true);
} catch (Exception $e) {
    error_log("Lỗi khởi tạo PHPMailer: {$mail->ErrorInfo}");
}

function sendAlertEmail($to, $subject, $body) {
    global $mail;
    try {
        $mail->clearAddresses();
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->send();
        error_log("Email sent to $to with subject: $subject");
        return true;
    } catch (Exception $e) {
        error_log("Failed to send email to $to. Error: {$mail->ErrorInfo}");
        return false;
    }
}
?>
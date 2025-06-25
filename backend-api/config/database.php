<?php
$host = 'localhost';  
$dbname = 'garden';
$username = 'root';
$password = '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    error_log("Database connection successful: $dbname");
} catch (PDOException $e) {
    error_log("Connection failed: " . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Kết nối cơ sở dữ liệu thất bại',
        'error' => $e->getMessage()
    ]);
    exit;
}
?>
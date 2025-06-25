<?php
header("Content-Type: application/json");
echo json_encode([
    "status" => "ok",
    "message" => "🌱 Smart Garden API is running"
]);
require_once __DIR__ . '/routes/auth.php';
?>

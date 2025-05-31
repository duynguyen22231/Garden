<?php
require_once '../config/database.php';

class AuthModel {
    private $conn;

    public function __construct() {
        global $conn;
        if (!$conn instanceof PDO) {
            throw new Exception("Không thể kết nối đến cơ sở dữ liệu.");
        }
        $this->conn = $conn;
    }

    public function findByUsername($username) {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findByEmail($email) {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findById($userId) {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($data) {
        $stmt = $this->conn->prepare("INSERT INTO users (username, email, password, full_name) VALUES (?, ?, ?, ?)");
        return $stmt->execute([
            $data['username'],
            $data['email'],
            $data['password'],
            $data['full_name']
        ]);
    }

    public function saveToken($userId, $token) {
        $stmt = $this->conn->prepare("INSERT INTO tokens (user_id, token, created_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE token = ?, created_at = NOW()");
        return $stmt->execute([$userId, $token, $token]);
    }

    public function findByToken($token) {
        $stmt = $this->conn->prepare("SELECT user_id FROM tokens WHERE token = ? AND created_at >= NOW() - INTERVAL 1 DAY");
        $stmt->execute([$token]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function deleteToken($token) {
        $stmt = $this->conn->prepare("DELETE FROM tokens WHERE token = ?");
        return $stmt->execute([$token]);
    }
}
?>
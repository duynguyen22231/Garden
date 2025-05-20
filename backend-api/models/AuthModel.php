<?php
require_once __DIR__ . '/../config/database.php';

class AuthModel {
    private $conn;

    public function __construct() {
        global $conn; // lấy từ file database.php
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

    public function create($data) {
        $stmt = $this->conn->prepare("INSERT INTO users (username, email, password, full_name) VALUES (?, ?, ?, ?)");
        return $stmt->execute([
            $data['username'],
            $data['email'],
            $data['password'],
            $data['full_name']
        ]);
    }
}

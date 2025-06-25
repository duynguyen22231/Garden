<?php
error_log("Starting AuthModel.php");

if (!file_exists(__DIR__ . '/../config/database.php')) {
    error_log("database.php not found at " . __DIR__ . '/../config/');
    throw new Exception("database.php not found");
}
require_once __DIR__ . '/../config/database.php';

class AuthModel {
    private $conn;

    public function __construct() {
        global $conn;
        error_log("Initializing AuthModel");
        if (!$conn instanceof PDO) {
            error_log("AuthModel: Database connection is not a PDO instance");
            throw new Exception("Không thể kết nối đến cơ sở dữ liệu.");
        }
        $this->conn = $conn;
    }

    public function findByUsername($username) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("findByUsername: username=$username, found=" . ($user ? 'true' : 'false'));
            return $user;
        } catch (PDOException $e) {
            error_log("findByUsername error: " . $e->getMessage());
            throw new Exception("Lỗi truy vấn người dùng: " . $e->getMessage());
        }
    }

    public function findByEmail($email) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("findByEmail: email=$email, found=" . ($user ? 'true' : 'false'));
            return $user;
        } catch (PDOException $e) {
            error_log("findByEmail error: " . $e->getMessage());
            throw new Exception("Lỗi truy vấn email: " . $e->getMessage());
        }
    }

    public function findById($userId) {
        try {
            if (!is_numeric($userId) || $userId <= 0) {
                error_log("findById: Invalid userId=$userId");
                return false;
            }
            $stmt = $this->conn->prepare("SELECT id, username, email, full_name, administrator_rights FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("findById: userId=$userId, found=" . ($user ? 'true' : 'false'));
            return $user;
        } catch (PDOException $e) {
            error_log("findById error: " . $e->getMessage());
            throw new Exception("Lỗi truy vấn ID người dùng: " . $e->getMessage());
        }
    }

    public function create($data) {
        try {
            $stmt = $this->conn->prepare(
                "INSERT INTO users (username, email, password, full_name, img_user, created_at, administrator_rights) 
                 VALUES (?, ?, ?, ?, ?, NOW(), 0)"
            );
            $result = $stmt->execute([
                $data['username'],
                $data['email'],
                $data['password'],
                $data['full_name'],
                $data['img_user'] ?? null
            ]);
            error_log("create: username={$data['username']}, success=" . ($result ? 'true' : 'false'));
            return $result;
        } catch (PDOException $e) {
            error_log("create error: " . $e->getMessage());
            throw new Exception("Lỗi tạo tài khoản: " . $e->getMessage());
        }
    }

    public function saveToken($userId, $token) {
        try {
            $this->conn->beginTransaction();
            
            $deleteStmt = $this->conn->prepare("DELETE FROM tokens WHERE user_id = ?");
            $deleteResult = $deleteStmt->execute([$userId]);
            error_log("saveToken: Deleted old tokens for userId=$userId, success=" . ($deleteResult ? 'true' : 'false'));

            $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
            $stmt = $this->conn->prepare(
                "INSERT INTO tokens (user_id, token, created_at, expiry) 
                 VALUES (?, ?, NOW(), ?)"
            );
            $result = $stmt->execute([$userId, $token, $expiry]);
            error_log("saveToken: userId=$userId, token=" . substr($token, 0, 20) . "... , expiry=$expiry, success=" . ($result ? 'true' : 'false'));

            $this->conn->commit();
            return $result;
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("saveToken error: " . $e->getMessage());
            throw new Exception("Lỗi lưu token: " . $e->getMessage());
        }
    }

    public function findByToken($token) {
        try {
            $stmt = $this->conn->prepare("SELECT user_id, expiry FROM tokens WHERE token = ? AND expiry > NOW()");
            $stmt->execute([$token]);
            $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("findByToken: token=" . substr($token, 0, 20) . "... , found=" . ($tokenData ? json_encode($tokenData) : 'false'));
            return $tokenData;
        } catch (PDOException $e) {
            error_log("findByToken error: " . $e->getMessage());
            throw new Exception("Lỗi tìm token: " . $e->getMessage());
        }
    }

    public function deleteToken($token) {
        try {
            $stmt = $this->conn->prepare("DELETE FROM tokens WHERE token = ?");
            $result = $stmt->execute([$token]);
            error_log("deleteToken: token=" . substr($token, 0, 20) . "... , success=" . ($result ? 'true' : 'false'));
            return $result;
        } catch (PDOException $e) {
            error_log("deleteToken error: " . $e->getMessage());
            throw new Exception("Lỗi xóa token: " . $e->getMessage());
        }
    }
}
?>
<?php
require_once '../config/database.php';

class AccountModel {
    private $conn;

    public function __construct() {
        global $conn;
        $this->conn = $conn;
        if (!$this->conn) {
            throw new Exception("Không thể kết nối đến cơ sở dữ liệu");
        }
    }

    public function getAllUsers() {
        $query = "SELECT id, username, email, administrator_rights, full_name, created_at, img_user FROM users";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        $users = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $users[] = [
                'id' => $row['id'],
                'username' => $row['username'],
                'email' => $row['email'],
                'administrator_rights' => (bool)$row['administrator_rights'],
                'full_name' => $row['full_name'],
                'created_at' => $row['created_at'],
                'img_user' => $row['img_user'] ? base64_encode($row['img_user']) : null
            ];
        }
        return $users;
    }

    public function addUser($username, $email, $password, $administrator_rights, $full_name, $img_user = null) {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        $query = "INSERT INTO users (username, email, password, administrator_rights, full_name, img_user) VALUES (:username, :email, :password, :administrator_rights, :full_name, :img_user)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':administrator_rights', $administrator_rights, PDO::PARAM_INT);
        $stmt->bindParam(':full_name', $full_name);

        // Handle base64 image
        $img_data = null;
        if ($img_user) {
            // Remove data URL prefix if present (e.g., "data:image/jpeg;base64,")
            $img_user = preg_replace('/^data:image\/[a-z]+;base64,/', '', $img_user);
            $img_data = base64_decode($img_user);
            if ($img_data === false) {
                throw new Exception("Dữ liệu ảnh không hợp lệ");
            }
        }
        $stmt->bindParam(':img_user', $img_data, PDO::PARAM_LOB);
        return $stmt->execute();
    }

    public function updateUser($id, $username, $email, $password, $administrator_rights, $full_name, $img_user = null) {
        // Get current user data to preserve unchanged fields
        $query = "SELECT username, email, administrator_rights, full_name FROM users WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new Exception("Không tìm thấy người dùng với ID: $id");
        }

        // Use existing values if not provided
        $username = !empty($username) ? $username : $user['username'];
        $email = !empty($email) ? $email : $user['email'];
        $administrator_rights = isset($administrator_rights) ? (int)$administrator_rights : $user['administrator_rights'];
        $full_name = !empty($full_name) ? $full_name : $user['full_name'];

        // Build dynamic query
        $query = "UPDATE users SET username = :username, email = :email, administrator_rights = :administrator_rights, full_name = :full_name";
        $params = [
            ':username' => $username,
            ':email' => $email,
            ':administrator_rights' => $administrator_rights,
            ':full_name' => $full_name,
            ':id' => $id
        ];

        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $query .= ", password = :password";
            $params[':password'] = $hashed_password;
        }
        if ($img_user !== null) {
            $query .= ", img_user = :img_user";
            // Handle base64 image
            $img_data = null;
            if ($img_user) {
                $img_user = preg_replace('/^data:image\/[a-z]+;base64,/', '', $img_user);
                $img_data = base64_decode($img_user);
                if ($img_data === false) {
                    throw new Exception("Dữ liệu ảnh không hợp lệ");
                }
            }
            $params[':img_user'] = $img_data;
        }
        $query .= " WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            if ($key === ':img_user') {
                $stmt->bindParam($key, $value, PDO::PARAM_LOB);
            } else {
                $stmt->bindValue($key, $value);
            }
        }
        return $stmt->execute();
    }

    public function deleteUser($id) {
        $query = "DELETE FROM users WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function __destruct() {
        $this->conn = null;
    }
}
?>
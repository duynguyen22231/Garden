<?php
require_once '../config/database.php';

class MicrocontrollerModel {
    private $conn;

    public function __construct() {
        global $conn;
        $this->conn = $conn;
        if (!$this->conn) {
            throw new Exception("Không thể kết nối đến cơ sở dữ liệu");
        }
    }

    public function getAllMicrocontrollers() {
        try {
            $query = "SELECT id, name, type, status, last_updated FROM microcontrollers";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();

            $microcontrollers = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $microcontrollers[] = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'type' => $row['type'],
                    'status' => $row['status'],
                    'last_updated' => $row['last_updated']
                ];
            }
            return $microcontrollers;
        } catch (PDOException $e) {
            throw new Exception("Lỗi truy vấn getAllMicrocontrollers: " . $e->getMessage());
        }
    }

    public function addMicrocontroller($name, $type, $status) {
        try {
            $query = "INSERT INTO microcontrollers (name, type, status, last_updated) 
                     VALUES (:name, :type, :status, NOW())";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $stmt->bindParam(':type', $type, PDO::PARAM_STR);
            $stmt->bindParam(':status', $status, PDO::PARAM_STR);
            return $stmt->execute();
        } catch (PDOException $e) {
            throw new Exception("Lỗi thêm linh kiện: " . $e->getMessage());
        }
    }

    public function updateMicrocontroller($id, $name, $type, $status) {
        try {
            $query = "UPDATE microcontrollers 
                     SET name = :name, type = :type, status = :status, last_updated = NOW() 
                     WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $stmt->bindParam(':type', $type, PDO::PARAM_STR);
            $stmt->bindParam(':status', $status, PDO::PARAM_STR);
            return $stmt->execute();
        } catch (PDOException $e) {
            throw new Exception("Lỗi cập nhật linh kiện: " . $e->getMessage());
        }
    }

    public function deleteMicrocontroller($id) {
        try {
            $query = "DELETE FROM microcontrollers WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            throw new Exception("Lỗi xóa linh kiện: " . $e->getMessage());
        }
    }

    public function __destruct() {
        $this->conn = null;
    }
}
?>
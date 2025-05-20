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
            $query = "SELECT mcu_id, name, garden_id, ip_address, status, last_seen FROM microcontrollers";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();

            $microcontrollers = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $microcontrollers[] = [
                    'mcu_id' => $row['mcu_id'],
                    'name' => $row['name'],
                    'garden_id' => $row['garden_id'],
                    'ip_address' => $row['ip_address'],
                    'status' => $row['status'],
                    'last_seen' => $row['last_seen']
                ];
            }
            return $microcontrollers;
        } catch (PDOException $e) {
            throw new Exception("Lỗi truy vấn getAllMicrocontrollers: " . $e->getMessage());
        }
    }

    public function addMicrocontroller($name, $garden_id, $ip_address, $status) {
        try {
            $query = "INSERT INTO microcontrollers (name, garden_id, ip_address, status, last_seen) 
                     VALUES (:name, :garden_id, :ip_address, :status, NOW())";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $stmt->bindParam(':garden_id', $garden_id, PDO::PARAM_INT);
            $stmt->bindParam(':ip_address', $ip_address, PDO::PARAM_STR);
            $stmt->bindParam(':status', $status, PDO::PARAM_STR);
            return $stmt->execute();
        } catch (PDOException $e) {
            throw new Exception("Lỗi thêm vi điều khiển: " . $e->getMessage());
        }
    }

    public function updateMicrocontroller($mcu_id, $name, $garden_id, $ip_address, $status) {
        try {
            $query = "UPDATE microcontrollers 
                     SET name = :name, garden_id = :garden_id, ip_address = :ip_address, 
                         status = :status, last_seen = NOW() 
                     WHERE mcu_id = :mcu_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':mcu_id', $mcu_id, PDO::PARAM_STR);
            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $stmt->bindParam(':garden_id', $garden_id, PDO::PARAM_INT);
            $stmt->bindParam(':ip_address', $ip_address, PDO::PARAM_STR);
            $stmt->bindParam(':status', $status, PDO::PARAM_STR);
            return $stmt->execute();
        } catch (PDOException $e) {
            throw new Exception("Lỗi cập nhật vi điều khiển: " . $e->getMessage());
        }
    }

    public function deleteMicrocontroller($mcu_id) {
        try {
            $query = "DELETE FROM microcontrollers WHERE mcu_id = :mcu_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':mcu_id', $mcu_id, PDO::PARAM_STR);
            return $stmt->execute();
        } catch (PDOException $e) {
            throw new Exception("Lỗi xóa vi điều khiển: " . $e->getMessage());
        }
    }

    public function __destruct() {
        $this->conn = null;
    }
}
?>
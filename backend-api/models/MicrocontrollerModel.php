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
            $query = "SELECT id, mcu_id, name, garden_id, ip_address, status, last_seen, created_at, mapping_updated_at FROM microcontrollers";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();

            $microcontrollers = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $microcontrollers[] = [
                    'id' => (int)$row['id'],
                    'mcu_id' => $row['mcu_id'],
                    'name' => $row['name'] ?? 'Không có tên',
                    'garden_id' => (int)$row['garden_id'],
                    'ip_address' => $row['ip_address'] ?? 'Không xác định',
                    'status' => $row['status'],
                    'last_seen' => $row['last_seen'],
                    'created_at' => $row['created_at'],
                    'mapping_updated_at' => $row['mapping_updated_at']
                ];
            }
            error_log("Microcontrollers fetched: " . json_encode($microcontrollers));
            return $microcontrollers;
        } catch (PDOException $e) {
            error_log("Lỗi truy vấn getAllMicrocontrollers: " . $e->getMessage());
            throw new Exception("Lỗi truy vấn getAllMicrocontrollers: " . $e->getMessage());
        }
    }

    public function addMicrocontroller($name, $garden_id, $ip_address, $status, $mcu_id) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO microcontrollers (mcu_id, name, garden_id, ip_address, status, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            return $stmt->execute([$mcu_id, $name, $garden_id, $ip_address, $status]);
        } catch (PDOException $e) {
            error_log("Lỗi trong addMicrocontroller: " . $e->getMessage());
            throw new Exception("Lỗi thêm vi điều khiển: " . $e->getMessage());
        }
    }

    public function updateMicrocontrollerIpAllGardens($mcu_id, $ip_address) {
        try {
            $query = "UPDATE microcontrollers 
                      SET ip_address = :ip_address, last_seen = NOW(), status = 'online'
                      WHERE mcu_id = :mcu_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':mcu_id', $mcu_id, PDO::PARAM_STR);
            $stmt->bindParam(':ip_address', $ip_address, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Lỗi trong updateMicrocontrollerIpAllGardens: " . $e->getMessage());
            throw new Exception("Lỗi cập nhật địa chỉ IP: " . $e->getMessage());
        }
    }

    public function updateMicrocontroller($mcu_id, $name, $garden_id, $ip_address, $status) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE microcontrollers 
                SET name = ?, garden_id = ?, ip_address = ?, status = ?, last_seen = NOW()
                WHERE mcu_id = ?
            ");
            return $stmt->execute([$name, $garden_id, $ip_address, $status, $mcu_id]);
        } catch (PDOException $e) {
            error_log("Lỗi trong updateMicrocontroller: " . $e->getMessage());
            throw new Exception("Lỗi cập nhật vi điều khiển: " . $e->getMessage());
        }
    }

    public function getMicrocontrollerByGarden($garden_id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT mcu_id, garden_id, name, ip_address, status, last_seen
                FROM microcontrollers 
                WHERE garden_id = ? 
                LIMIT 1
            ");
            $stmt->execute([$garden_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Lỗi trong getMicrocontrollerByGarden: " . $e->getMessage());
            throw new Exception("Lỗi truy vấn cơ sở dữ liệu: " . $e->getMessage());
        }
    }

    public function updateIpAddress($mcu_id, $garden_id, $ip_address) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE microcontrollers 
                SET ip_address = ?, last_seen = NOW()
                WHERE mcu_id = ? AND garden_id = ?
            ");
            return $stmt->execute([$ip_address, $mcu_id, $garden_id]);
        } catch (PDOException $e) {
            error_log("Lỗi trong updateIpAddress: " . $e->getMessage());
            throw new Exception("Lỗi cập nhật IP: " . $e->getMessage());
        }
    }

    public function deleteMicrocontroller($mcu_id) {
        try {
            $stmt = $this->conn->prepare("
                DELETE FROM microcontrollers WHERE mcu_id = ?
            ");
            return $stmt->execute([$mcu_id]);
        } catch (PDOException $e) {
            error_log("Lỗi trong deleteMicrocontroller: " . $e->getMessage());
            throw new Exception("Lỗi xóa vi điều khiển: " . $e->getMessage());
        }
    }

    public function checkMcuId($mcu_id) {
        try {
            $query = "SELECT COUNT(*) as count FROM microcontrollers WHERE mcu_id = :mcu_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':mcu_id', $mcu_id, PDO::PARAM_STR);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'] > 0;
        } catch (PDOException $e) {
            error_log("Lỗi trong checkMcuId: " . $e->getMessage());
            throw new Exception("Lỗi kiểm tra mcu_id: " . $e->getMessage());
        }
    }

    public function initializeMcu($mcu_id, $ip_address) {
        try {
            // Kiểm tra xem mcu_id đã tồn tại chưa
            $query = "SELECT COUNT(*) as count, garden_id FROM microcontrollers WHERE mcu_id = :mcu_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':mcu_id', $mcu_id, PDO::PARAM_STR);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result['count'] == 0) {
                throw new Exception("MCU with mcu_id $mcu_id does not exist");
            }

            // Cập nhật ip_address và last_seen
            $query = "UPDATE microcontrollers 
                      SET ip_address = :ip_address, status = 'online', last_seen = NOW()
                      WHERE mcu_id = :mcu_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':mcu_id', $mcu_id, PDO::PARAM_STR);
            $stmt->bindParam(':ip_address', $ip_address, PDO::PARAM_STR);
            $stmt->execute();

            $garden_number = $result['garden_id'] ?? 1;

            // Trả về thông tin MCU
            return [
                'mcu_id' => $mcu_id,
                'garden_number' => (int)$garden_number,
                'devices' => ['maybom', 'vantren', 'vanduoi', 'den1', 'quat1', 'den2', 'quat2']
            ];
        } catch (PDOException $e) {
            error_log("Lỗi trong initializeMcu: " . $e->getMessage());
            throw new Exception("Lỗi khởi tạo MCU: " . $e->getMessage());
        }
    }

    public function __destruct() {
        $this->conn = null;
    }
}
?>
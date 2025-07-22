<?php
require_once __DIR__ . '/../config/database.php';

class SensorModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function getMcuId($mac_address) {
        try {
            $stmt = $this->db->prepare("
                SELECT mcu_id 
                FROM mcu_assignments 
                WHERE mac_address = :mac_address
            ");
            $stmt->execute([':mac_address' => $mac_address]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                return ['success' => true, 'mcu_id' => $result['mcu_id']];
            }
            // Tự động tạo bản ghi với mcu_id="mcu_001"
            $stmt = $this->db->prepare("
                INSERT INTO mcu_assignments (mac_address, mcu_id, created_at)
                VALUES (:mac_address, 'mcu_001', NOW())
            ");
            $stmt->execute([':mac_address' => $mac_address]);
            return ['success' => true, 'mcu_id' => 'mcu_001'];
        } catch (PDOException $e) {
            error_log("Lỗi getMcuId: " . $e->getMessage());
            throw new Exception("Lỗi lấy hoặc tạo mcu_id: " . $e->getMessage());
        }
    }

    public function getSensorReadings($garden_number) {
        try {
            $stmt = $this->db->prepare("
                SELECT soil_moisture, temperature, humidity, water_level_cm, is_raining, created_at 
                FROM sensor_readings 
                WHERE garden_number = :garden_number 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([':garden_number' => (int)$garden_number]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("getSensorReadings: garden_number=$garden_number, result=" . json_encode($result));
            return $result ?: null;
        } catch (PDOException $e) {
            error_log("Lỗi getSensorReadings: " . $e->getMessage());
            throw new Exception("Lỗi lấy dữ liệu cảm biến: " . $e->getMessage());
        }
    }

    public function saveSensorData($garden_number, $data) {
        try {
            error_log("saveSensorData: garden_number=$garden_number, data=" . json_encode($data));
            $stmt = $this->db->prepare("
                INSERT INTO sensor_readings (
                    garden_number, soil_moisture, temperature, humidity, water_level_cm, is_raining, created_at
                ) VALUES (
                    :garden_number, :soil_moisture, :temperature, :humidity, :water_level_cm, :is_raining, :created_at
                )
            ");
            $params = [
                ':garden_number' => (int)$garden_number,
                ':soil_moisture' => floatval($data['soil_moisture']),
                ':temperature' => floatval($data['temperature']),
                ':humidity' => floatval($data['humidity']),
                ':water_level_cm' => floatval($data['water_level_cm']),
                ':is_raining' => (int)$data['is_raining'],
                ':created_at' => date('Y-m-d H:i:s')
            ];
            error_log("saveSensorData: SQL params=" . json_encode($params));
            $stmt->execute($params);
            error_log("saveSensorData: Successfully saved data for garden_number=$garden_number");
        } catch (PDOException $e) {
            error_log("Lỗi saveSensorData: " . $e->getMessage());
            throw new Exception("Lỗi lưu dữ liệu cảm biến: " . $e->getMessage());
        }
    }

    public function updateRelay($garden_number, $device_name, $status) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO device_status (garden_number, device_name, status, last_updated)
                VALUES (:garden_number, :device_name, :status, NOW())
                ON DUPLICATE KEY UPDATE status = :status, last_updated = NOW()
            ");
            return $stmt->execute([
                ':garden_number' => (int)$garden_number,
                ':device_name' => trim($device_name),
                ':status' => (int)$status
            ]);
        } catch (PDOException $e) {
            error_log("Lỗi updateRelay: " . $e->getMessage());
            throw new Exception("Lỗi cập nhật relay: " . $e->getMessage());
        }
    }

    public function getControlCommands($garden_number) {
        try {
            $stmt = $this->db->prepare("
                SELECT device_name, status 
                FROM device_status 
                WHERE garden_number = :garden_number
            ");
            $stmt->execute([':garden_number' => (int)$garden_number]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Lỗi getControlCommands: " . $e->getMessage());
            throw new Exception("Lỗi lấy lệnh điều khiển: " . $e->getMessage());
        }
    }

    public function getGardenNumber($garden_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT garden_number 
                FROM garden_assignments 
                WHERE garden_id = :garden_id
            ");
            $stmt->execute([':garden_id' => (int)$garden_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                return ['success' => true, 'garden_number' => (int)$result['garden_number']];
            }

            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM garden_assignments
            ");
            $stmt->execute();
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            $garden_number = ($count % 2 === 0) ? 1 : 2;

            $stmt = $this->db->prepare("
                INSERT INTO garden_assignments (garden_id, garden_number, assigned_at)
                VALUES (:garden_id, :garden_number, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE garden_number = :garden_number, assigned_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([
                ':garden_id' => (int)$garden_id,
                ':garden_number' => $garden_number
            ]);

            return ['success' => true, 'garden_number' => $garden_number];
        } catch (PDOException $e) {
            error_log("Lỗi getGardenNumber: " . $e->getMessage());
            return ['success' => false, 'message' => "Lỗi lấy hoặc gán số vườn: " . $e->getMessage()];
        }
    }

    public function saveGardenAssignment($garden_id, $garden_number) {
        try {
            $stmt = $this->db->prepare("
                SELECT garden_id 
                FROM garden_assignments 
                WHERE garden_number = :garden_number AND garden_id != :garden_id
            ");
            $stmt->execute([':garden_number' => (int)$garden_number, ':garden_id' => (int)$garden_id]);
            if ($stmt->fetchColumn()) {
                $new_garden_number = ($garden_number == 1) ? 2 : 1;
                $stmt = $this->db->prepare("
                    UPDATE garden_assignments 
                    SET garden_number = :new_garden_number, assigned_at = CURRENT_TIMESTAMP
                    WHERE garden_number = :garden_number AND garden_id != :garden_id
                ");
                $stmt->execute([
                    ':new_garden_number' => $new_garden_number,
                    ':garden_number' => (int)$garden_number,
                    ':garden_id' => (int)$garden_id
                ]);
            }

            $stmt = $this->db->prepare("
                INSERT INTO garden_assignments (garden_id, garden_number, assigned_at)
                VALUES (:garden_id, :garden_number, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE garden_number = :garden_number, assigned_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([
                ':garden_id' => (int)$garden_id,
                ':garden_number' => (int)$garden_number
            ]);
            return true;
        } catch (PDOException $e) {
            error_log("Lỗi saveGardenAssignment: " . $e->getMessage());
            throw new Exception("Lỗi lưu gán vườn: " . $e->getMessage());
        }
    }

    public function saveSchedule($device_name, $action, $time, $end_time, $date, $garden_id, $mcu_id) {
        try {
            $is_range = ($end_time !== null && $end_time !== '') ? 1 : 0;
            
            $stmt = $this->db->prepare("
                INSERT INTO schedules 
                (device_name, action, is_range, time, end_time, date, garden_id, mcu_id, created_at) 
                VALUES 
                (:device_name, :action, :is_range, :time, :end_time, :date, :garden_id, :mcu_id, NOW())
            ");
            
            $stmt->execute([
                ':device_name' => $device_name,
                ':action' => $action,
                ':is_range' => $is_range,
                ':time' => $time,
                ':end_time' => $is_range ? $end_time : null,
                ':date' => $date,
                ':garden_id' => $garden_id,
                ':mcu_id' => $mcu_id
            ]);

            return true;
        } catch (PDOException $e) {
            error_log("Database error in saveSchedule: " . $e->getMessage());
            throw new Exception("Lỗi database: " . $e->getMessage());
        }
    }

    public function getSchedules($garden_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM schedules 
                WHERE garden_id = :garden_id 
                AND (date >= CURDATE() OR is_range = 1)
                ORDER BY date, time
            ");
            $stmt->execute([':garden_id' => (int)$garden_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Lỗi getSchedules: " . $e->getMessage());
            throw new Exception("Lỗi lấy lịch trình: " . $e->getMessage());
        }
    }

    public function getGardenAssignments($mcu_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT garden_id, garden_number
                FROM garden_assignments
                WHERE garden_id IN (
                    SELECT garden_id
                    FROM microcontrollers
                    WHERE mcu_id = :mcu_id
                )
            ");
            $stmt->execute([':mcu_id' => $mcu_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Lỗi getGardenAssignments: " . $e->getMessage());
            throw new Exception("Lỗi lấy danh sách gán vườn: " . $e->getMessage());
        }
    }

    public function getSchedulesByMcu($mcu_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT s.*
                FROM schedules s
                WHERE s.mcu_id = :mcu_id
                AND (s.date >= CURDATE() OR s.is_range = 1)
                ORDER BY s.date, s.time
            ");
            $stmt->execute([':mcu_id' => $mcu_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Lỗi getSchedulesByMcu: " . $e->getMessage());
            throw new Exception("Lỗi lấy lịch trình theo mcu_id: " . $e->getMessage());
        }
    }

    public function deleteSchedule($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM schedules WHERE id = :id");
            $stmt->execute([':id' => (int)$id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Lỗi deleteSchedule: " . $e->getMessage());
            throw new Exception("Lỗi xóa lịch trình: " . $e->getMessage());
        }
    }
}
?>
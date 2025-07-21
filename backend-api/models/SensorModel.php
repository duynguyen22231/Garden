<?php
require_once __DIR__ . '/../config/database.php';

class SensorModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function getSensorReadings($garden_number) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM sensor_readings 
                WHERE garden_number = :garden_number 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([':garden_number' => (int)$garden_number]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            error_log("Lỗi getSensorReadings: " . $e->getMessage());
            throw new Exception("Lỗi lấy dữ liệu cảm biến: " . $e->getMessage());
        }
    }

    public function saveSensorData($data) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO sensor_readings (
                    garden_number, soil_moisture, temperature, humidity, water_level_cm, is_raining, created_at
                ) VALUES (
                    :garden_number, :soil_moisture, :temperature, :humidity, :water_level_cm, :is_raining, NOW()
                )
            ");
            $stmt->execute([
                ':garden_number' => (int)$data['garden_number'],
                ':soil_moisture' => (int)$data['soil_moisture'],
                ':temperature' => (float)$data['temperature'],
                ':humidity' => (float)$data['humidity'],
                ':water_level_cm' => (float)$data['water_level_cm'],
                ':is_raining' => (int)$data['is_raining']
            ]);
            return true;
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
            (device_name, action, is_range, time, end_time, date, garden_id, mcu_id) 
            VALUES 
            (:device_name, :action, :is_range, :time, :end_time, :date, :garden_id, :mcu_id)
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
                ORDER BY created_at DESC
            ");
            $stmt->execute([':garden_id' => (int)$garden_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Lỗi getSchedules: " . $e->getMessage());
            throw new Exception("Lỗi lấy lịch trình: " . $e->getMessage());
        }
}

}
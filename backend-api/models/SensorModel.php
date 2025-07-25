<?php
require_once __DIR__ . '/../config/database.php';

class SensorModel {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function getLatestSensorData($garden_number) {
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
            error_log("getLatestSensorData: garden_number=$garden_number, result=" . json_encode($result));
            return $result ?: null;
        } catch (PDOException $e) {
            error_log("Lỗi getLatestSensorData: " . $e->getMessage());
            throw new Exception("Lỗi lấy dữ liệu cảm biến: " . $e->getMessage());
        }
    }

    public function saveSensorData($garden_number, $data) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO sensor_readings (
                    garden_number, soil_moisture, temperature, humidity, water_level_cm, is_raining, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, NOW()
                )
            ");
            $params = [
                (int)$garden_number,
                floatval($data['soil_moisture'] ?? 0),
                floatval($data['temperature'] ?? 0),
                floatval($data['humidity'] ?? 0),
                floatval($data['water_level_cm'] ?? 0),
                (int)($data['is_raining'] ?? 0)
            ];
            $stmt->execute($params);
            error_log("saveSensorData: Saved data for garden_number=$garden_number, data=" . json_encode($data));

            $stmt = $this->db->prepare("
                SELECT garden_id 
                FROM garden_assignments 
                WHERE garden_number = ?
                ORDER BY assigned_at DESC 
                LIMIT 1
            ");
            $stmt->execute([(int)$garden_number]);
            $garden_id = $stmt->fetchColumn();

            if (!$garden_id) {
                error_log("Không tìm thấy garden_id cho garden_number=$garden_number");
                throw new Exception("Không tìm thấy garden_id cho garden_number=$garden_number");
            }
            error_log("saveSensorData: Found garden_id=$garden_id for garden_number=$garden_number");

            $this->checkThresholdsAndSendAlerts($garden_id, $garden_number, $data);
        } catch (PDOException $e) {
            error_log("Lỗi saveSensorData: " . $e->getMessage());
            throw new Exception("Lỗi lưu dữ liệu cảm biến: " . $e->getMessage());
        }
    }

    public function updateRelayStatus($garden_number, $device_name, $status) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO device_status (garden_number, device_name, status, last_updated)
                VALUES (:garden_number, :device_name, :status, NOW())
                ON DUPLICATE KEY UPDATE status = :status, last_updated = NOW()
            ");
            $stmt->execute([
                ':garden_number' => (int)$garden_number,
                ':device_name' => trim($device_name),
                ':status' => (int)$status
            ]);
            error_log("updateRelayStatus: Updated garden_number=$garden_number, device_name=$device_name, status=$status");
        } catch (PDOException $e) {
            error_log("Lỗi updateRelayStatus: " . $e->getMessage());
            throw new Exception("Lỗi cập nhật trạng thái rơ-le: " . $e->getMessage());
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
            $commands = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("getControlCommands: garden_number=$garden_number, commands=" . json_encode($commands));
            return $commands ?: [
                ['device_name' => 'den2', 'status' => 0],
                ['device_name' => 'quat2', 'status' => 0]
            ];
        } catch (PDOException $e) {
            error_log("Lỗi getControlCommands: " . $e->getMessage());
            throw new Exception("Lỗi lấy trạng thái thiết bị: " . $e->getMessage());
        }
    }

    public function saveSchedule($device_name, $action, $time, $end_time, $date, $garden_id, $mcu_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT garden_number 
                FROM garden_assignments 
                WHERE garden_id = :garden_id
            ");
            $stmt->execute([':garden_id' => (int)$garden_id]);
            $garden_number = $stmt->fetchColumn();
            
            $valid_devices = ($garden_number == 1) ? ['den1', 'quat1'] : ['den2', 'quat2'];
            if (!in_array($device_name, $valid_devices)) {
                throw new Exception("Thiết bị $device_name không hợp lệ cho garden_id=$garden_id (garden_number=$garden_number)");
            }

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
            error_log("saveSchedule: Saved schedule for garden_id=$garden_id, device_name=$device_name");
        } catch (PDOException $e) {
            error_log("Lỗi saveSchedule: " . $e->getMessage());
            throw new Exception("Lỗi lưu lịch trình: " . $e->getMessage());
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
            $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("getSchedules: garden_id=$garden_id, schedules=" . json_encode($schedules));
            return $schedules;
        } catch (PDOException $e) {
            error_log("Lỗi getSchedules: " . $e->getMessage());
            throw new Exception("Lỗi lấy lịch trình: " . $e->getMessage());
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

    public function setThreshold($garden_id, $sensor_type, $min_value, $max_value, $email_enabled) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO sensor_thresholds (garden_id, sensor_type, min_value, max_value, email_enabled, created_at, updated_at)
                VALUES (:garden_id, :sensor_type, :min_value, :max_value, :email_enabled, NOW(), NOW())
                ON DUPLICATE KEY UPDATE 
                    min_value = :min_value, 
                    max_value = :max_value, 
                    email_enabled = :email_enabled, 
                    updated_at = NOW()
            ");
            $stmt->execute([
                ':garden_id' => (int)$garden_id,
                ':sensor_type' => $sensor_type,
                ':min_value' => $min_value === '' ? null : floatval($min_value),
                ':max_value' => $max_value === '' ? null : floatval($max_value),
                ':email_enabled' => (int)$email_enabled
            ]);
            error_log("setThreshold: Set threshold for garden_id=$garden_id, sensor_type=$sensor_type, min_value=$min_value, max_value=$max_value, email_enabled=$email_enabled");
        } catch (PDOException $e) {
            error_log("Lỗi setThreshold: " . $e->getMessage());
            throw new Exception("Lỗi cài đặt ngưỡng: " . $e->getMessage());
        }
    }

    public function getThresholds($garden_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT sensor_type, min_value, max_value, email_enabled
                FROM sensor_thresholds 
                WHERE garden_id = :garden_id
            ");
            $stmt->execute([':garden_id' => (int)$garden_id]);
            $thresholds = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("getThresholds: garden_id=$garden_id, thresholds=" . json_encode($thresholds));
            return $thresholds;
        } catch (PDOException $e) {
            error_log("Lỗi getThresholds: " . $e->getMessage());
            throw new Exception("Lỗi lấy ngưỡng cảm biến: " . $e->getMessage());
        }
    }

    public function getThresholdsByGardenNumber($garden_number) {
        try {
            $stmt = $this->db->prepare("
                SELECT t.* FROM sensor_thresholds t
                JOIN garden_assignments ga ON t.garden_id = ga.garden_id
                WHERE ga.garden_number = :garden_number
            ");
            $stmt->execute([':garden_number' => (int)$garden_number]);
            $thresholds = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("getThresholdsByGardenNumber: garden_number=$garden_number, thresholds=" . json_encode($thresholds));
            return $thresholds;
        } catch (PDOException $e) {
            error_log("Lỗi getThresholdsByGardenNumber: " . $e->getMessage());
            throw new Exception("Lỗi lấy ngưỡng theo garden_number: " . $e->getMessage());
        }
    }

    public function saveAlert($garden_id, $garden_number, $sensor_type, $alert_type, $sensor_value) {
        try {
            $email = $this->getUserEmail($garden_id);
            $stmt = $this->db->prepare("
                INSERT INTO alerts_log (garden_id, garden_number, sensor_type, alert_type, sensor_value, sent_to_email, created_at)
                VALUES (:garden_id, :garden_number, :sensor_type, :alert_type, :sensor_value, :sent_to_email, NOW())
            ");
            $stmt->execute([
                ':garden_id' => (int)$garden_id,
                ':garden_number' => (int)$garden_number,
                ':sensor_type' => $sensor_type,
                ':alert_type' => $alert_type,
                ':sensor_value' => floatval($sensor_value),
                ':sent_to_email' => $email ?: null
            ]);
            error_log("saveAlert: garden_id=$garden_id, garden_number=$garden_number, sensor_type=$sensor_type, alert_type=$alert_type, sensor_value=$sensor_value, sent_to_email=$email");
        } catch (PDOException $e) {
            error_log("Lỗi saveAlert: " . $e->getMessage());
            throw new Exception("Lỗi lưu cảnh báo: " . $e->getMessage());
        }
    }

    public function getAlerts($garden_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM alerts_log 
                WHERE garden_id = :garden_id 
                ORDER BY created_at DESC 
                LIMIT 10
            ");
            $stmt->execute([':garden_id' => (int)$garden_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Lỗi getAlerts: " . $e->getMessage());
            throw new Exception("Lỗi lấy lịch sử cảnh báo: " . $e->getMessage());
        }
    }

    public function getUserEmail($garden_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT u.email FROM users u
                JOIN gardens g ON u.id = g.user_id
                WHERE g.id = :garden_id
            ");
            $stmt->execute([':garden_id' => (int)$garden_id]);
            $email = $stmt->fetchColumn();
            error_log("getUserEmail: garden_id=$garden_id, email=$email");
            return $email ?: null;
        } catch (PDOException $e) {
            error_log("Lỗi getUserEmail: " . $e->getMessage());
            return null;
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
                SELECT garden_number 
                FROM garden_assignments
                ORDER BY assigned_at DESC
                LIMIT 1
            ");
            $stmt->execute();
            $last_garden_number = $stmt->fetchColumn();
            $garden_number = ($last_garden_number == 1) ? 2 : 1;

            $stmt = $this->db->prepare("
                INSERT INTO garden_assignments (garden_id, garden_number, assigned_at)
                VALUES (:garden_id, :garden_number, NOW())
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

    public function getGardenIdByGardenNumber($garden_number) {
        try {
            $stmt = $this->db->prepare("
                SELECT garden_id 
                FROM garden_assignments 
                WHERE garden_number = :garden_number
                ORDER BY assigned_at DESC 
                LIMIT 1
            ");
            $stmt->execute([':garden_number' => (int)$garden_number]);
            $garden_id = $stmt->fetchColumn();
            return $garden_id ?: null;
        } catch (PDOException $e) {
            error_log("Lỗi getGardenIdByGardenNumber: " . $e->getMessage());
            throw new Exception("Lỗi lấy garden_id theo garden_number: " . $e->getMessage());
        }
    }

    public function saveGardenAssignment($garden_id, $garden_number) {
        try {
            $stmt = $this->db->prepare("
                SELECT garden_number 
                FROM garden_assignments 
                WHERE garden_id = :garden_id
            ");
            $stmt->execute([':garden_id' => (int)$garden_id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                error_log("saveGardenAssignment: garden_id=$garden_id already assigned to garden_number={$existing['garden_number']}, no update allowed");
                return true;
            }

            $stmt = $this->db->prepare("
                SELECT garden_id 
                FROM garden_assignments 
                WHERE garden_number = :garden_number AND garden_id != :garden_id
            ");
            $stmt->execute([':garden_number' => (int)$garden_number, ':garden_id' => (int)$garden_id]);
            if ($stmt->fetchColumn()) {
                throw new Exception("Số vườn $garden_number đã được gán cho vườn khác");
            }

            $stmt = $this->db->prepare("
                INSERT INTO garden_assignments (garden_id, garden_number, assigned_at)
                VALUES (:garden_id, :garden_number, NOW())
            ");
            $stmt->execute([
                ':garden_id' => (int)$garden_id,
                ':garden_number' => (int)$garden_number
            ]);
            error_log("saveGardenAssignment: Assigned garden_id=$garden_id to garden_number=$garden_number");
            return true;
        } catch (PDOException $e) {
            error_log("Lỗi saveGardenAssignment: " . $e->getMessage());
            throw new Exception("Lỗi lưu gán vườn: " . $e->getMessage());
        }
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
            $stmt = $this->db->prepare("
                INSERT INTO mcu_assignments (mac_address, mcu_id, assigned_at)
                VALUES (:mac_address, 'mcu_id_001', NOW())
            ");
            $stmt->execute([':mac_address' => $mac_address]);
            return ['success' => true, 'mcu_id' => 'mcu_001'];
        } catch (PDOException $e) {
            error_log("Lỗi getMcuId: " . $e->getMessage());
            throw new Exception("Lỗi lấy hoặc tạo mcu_id: " . $e->getMessage());
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
                WHERE mcu_id = :mcu_id
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

    public function checkThresholdsAndSendAlerts($garden_id, $garden_number, $data) {
        try {
            $thresholds = $this->getThresholdsByGardenNumber($garden_number);
            $user_email = $this->getUserEmail($garden_id);
            if (!$user_email) {
                error_log("Không tìm thấy email cho garden_id=$garden_id");
                return;
            }

            $sensor_types = [
                'soil_moisture' => 'Độ ẩm đất',
                'temperature' => 'Nhiệt độ',
                'humidity' => 'Độ ẩm không khí',
                'water_level_cm' => 'Mức nước'
            ];

            $alert_triggered = false;
            $alert_messages = [];

            $stmt = $this->db->prepare("SELECT garden_names FROM gardens WHERE id = :garden_id");
            $stmt->execute([':garden_id' => (int)$garden_id]);
            $garden_name = $stmt->fetchColumn() ?: "Vườn không tên";

            foreach ($thresholds as $threshold) {
                if (!$threshold['email_enabled']) {
                    error_log("Email không được bật cho sensor_type={$threshold['sensor_type']}, garden_id=$garden_id");
                    continue;
                }

                $sensor_type = $threshold['sensor_type'];
                if (!isset($data[$sensor_type])) {
                    error_log("Dữ liệu cảm biến $sensor_type không tồn tại trong data=" . json_encode($data));
                    continue;
                }

                $sensor_value = floatval($data[$sensor_type]) ?? 0;
                $min_value = $threshold['min_value'] !== null ? floatval($threshold['min_value']) : null;
                $max_value = $threshold['max_value'] !== null ? floatval($threshold['max_value']) : null;
                $alert_type = '';

                error_log("Checking threshold: garden_id=$garden_id, garden_number=$garden_number, sensor_type=$sensor_type, value=$sensor_value, min_value=$min_value, max_value=$max_value");

                if ($min_value !== null && $sensor_value < $min_value) {
                    $alert_type = "Cảnh báo: {$sensor_types[$sensor_type]} thấp ($sensor_value)";
                } elseif ($max_value !== null && $sensor_value > $max_value) {
                    $alert_type = "Cảnh báo: {$sensor_types[$sensor_type]} cao ($sensor_value)";
                }

                if ($alert_type) {
                    $this->saveAlert($garden_id, $garden_number, $sensor_type, $alert_type, $sensor_value);
                    $alert_triggered = true;
                    $alert_messages[] = $alert_type;

                    $subject = "Cảnh báo Vườn Cây Thông Minh - {$sensor_types[$sensor_type]}";
                    $body = "
                        <h2>Cảnh báo từ Hệ Thống Vườn Cây Thông Minh</h2>
                        <p><strong>Tên vườn:</strong> $garden_name</p>
                        <p><strong>Vườn số:</strong> $garden_number</p>
                        <p><strong>Loại cảm biến:</strong> {$sensor_types[$sensor_type]}</p>
                        <p><strong>Loại cảnh báo:</strong> $alert_type</p>
                        <p><strong>Giá trị:</strong> $sensor_value</p>
                        <p><strong>Thời gian:</strong> " . date('Y-m-d H:i:s') . "</p>
                        <p>Vui lòng kiểm tra hệ thống để xử lý!</p>
                    ";

                    error_log("Attempting to send alert: garden_id=$garden_id, garden_number=$garden_number, alert_type=$alert_type, to=$user_email");
                    $result = sendAlertEmail($user_email, $subject, $body);
                    if ($result) {
                        error_log("Successfully sent email to $user_email for alert_type=$alert_type");
                    } else {
                        error_log("Failed to send email to $user_email for alert_type=$alert_type");
                    }
                } else {
                    error_log("No alert triggered for sensor_type=$sensor_type, value=$sensor_value, garden_id=$garden_id");
                }
            }

            if ($alert_triggered) {
                error_log("Alerts triggered for garden_id=$garden_id: " . implode(", ", $alert_messages));
            } else {
                error_log("No alerts triggered for garden_id=$garden_id");
            }
        } catch (Exception $e) {
            error_log("Lỗi checkThresholdsAndSendAlerts: " . $e->getMessage());
        }
    }
}
?>
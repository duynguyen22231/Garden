<?php
require_once '../config/database.php';

class HomeModel {
    private $conn;

    public function __construct() {
        global $conn;
        if (!$conn instanceof PDO) {
            error_log("Lỗi: Không thể lấy kết nối cơ sở dữ liệu trong HomeModel. \$conn không phải là PDO instance.");
            throw new Exception("Không thể kết nối cơ sở dữ liệu");
        }
        $this->conn = $conn;
    }

    public function getAll($userId, $isAdmin) {
        try {
            error_log("Bắt đầu lấy thông tin vườn cây");
            $sql = "SELECT gardens.id, gardens.garden_names, gardens.location, gardens.longitude, 
                           gardens.latitude, gardens.note, gardens.area, gardens.created_at, 
                           gardens.status, gardens.user_id, gardens.img, users.full_name AS owner_name
                    FROM gardens
                    LEFT JOIN users ON gardens.user_id = users.id
                    WHERE gardens.status = 'Hoạt động'";
            
            $params = [];
            if (!$isAdmin) {
                $sql .= " AND gardens.user_id = :user_id";
                $params[':user_id'] = $userId;
            }

            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            // Thêm img_url cho ảnh BLOB
            foreach ($result as &$garden) {
                $garden['img_url'] = !empty($garden['img']) 
                    ? "http://localhost/SmartGarden/backend-api/routes/home.php" // Sẽ dùng POST
                    : '';
                $garden['img_id'] = $garden['id']; // Lưu ID để dùng trong POST
                unset($garden['img']); // Xóa dữ liệu BLOB để giảm kích thước JSON
            }
            error_log("Số vườn lấy được: " . count($result));
            return $result;
        } catch (PDOException $e) {
            error_log("Lỗi trong getAll: " . $e->getMessage());
            return [];
        }
    }

    public function getGardenById($garden_id) {
        try {
            $sql = "SELECT * FROM gardens WHERE id = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':id' => $garden_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Lỗi trong getGardenById: " . $e->getMessage());
            return null;
        }
    }

    public function getGardenImage($garden_id) {
        try {
            $sql = "SELECT img FROM gardens WHERE id = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':id' => $garden_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result && !empty($result['img']) ? $result['img'] : null;
        } catch (PDOException $e) {
            error_log("Lỗi trong getGardenImage: " . $e->getMessage());
            return null;
        }
    }

    public function getAllUsers() {
        try {
            $sql = "SELECT id, full_name FROM users WHERE administrator_rights = 0";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Lỗi trong getAllUsers: " . $e->getMessage());
            return [];
        }
    }

    public function saveGarden($data) {
        try {
            $sql = "INSERT INTO gardens (garden_names, user_id, location, area, note, latitude, longitude, img, status)
                    VALUES (:name, :user_id, :location, :area, :note, :latitude, :longitude, :img, 'Hoạt động')";
            $stmt = $this->conn->prepare($sql);
            $result = $stmt->execute([
                ':name' => $data['name'],
                ':user_id' => $data['user_id'],
                ':location' => $data['location'],
                ':area' => $data['area'],
                ':note' => empty($data['note']) ? 'Không có ghi chú' : $data['note'],
                ':latitude' => $data['latitude'],
                ':longitude' => $data['longitude'],
                ':img' => $data['img'] // BLOB data
            ]);
            return $result;
        } catch (PDOException $e) {
            error_log("Lỗi trong saveGarden: " . $e->getMessage());
            return false;
        }
    }

    public function getSensorData($garden_id = null, $userId, $isAdmin) {
        try {
            $result = [];
            if ($garden_id !== null) {
                $sql = "SELECT soil_moisture, temperature, humidity
                        FROM sensor_readings
                        WHERE garden_id = :garden_id
                        ORDER BY created_at DESC
                        LIMIT 1";
                $stmt = $this->conn->prepare($sql);
                $stmt->execute([':garden_id' => $garden_id]);
                $sensor_data = $stmt->fetch(PDO::FETCH_ASSOC);

                $sql_relay = "SELECT status
                             FROM relay_controls
                             WHERE garden_id = :garden_id
                             ORDER BY updated_at DESC
                             LIMIT 1";
                $stmt_relay = $this->conn->prepare($sql_relay);
                $stmt_relay->execute([':garden_id' => $garden_id]);
                $relay = $stmt_relay->fetch(PDO::FETCH_ASSOC);

                $result[$garden_id] = $sensor_data ? [
                    'temperature' => $sensor_data['temperature'] ?? 0,
                    'soil_moisture' => $sensor_data['soil_moisture'] ?? 0,
                    'humidity' => $sensor_data['humidity'] ?? 0,
                    'irrigation' => $relay['status'] ?? 0
                ] : [
                    'temperature' => 0,
                    'soil_moisture' => 0,
                    'humidity' => 0,
                    'irrigation' => 0
                ];
            } else {
                $gardens = $this->getAll($userId, $isAdmin);
                foreach ($gardens as $garden) {
                    $sql = "SELECT soil_moisture, temperature, humidity
                            FROM sensor_readings
                            WHERE garden_id = :garden_id
                            ORDER BY created_at DESC
                            LIMIT 1";
                    $stmt = $this->conn->prepare($sql);
                    $stmt->execute([':garden_id' => $garden['id']]);
                    $sensor_data = $stmt->fetch(PDO::FETCH_ASSOC);

                    $sql_relay = "SELECT status
                                 FROM relay_controls
                                 WHERE garden_id = :garden_id
                                 ORDER BY updated_at DESC
                                 LIMIT 1";
                    $stmt_relay = $this->conn->prepare($sql_relay);
                    $stmt_relay->execute([':garden_id' => $garden['id']]);
                    $relay = $stmt_relay->fetch(PDO::FETCH_ASSOC);

                    $result[$garden['id']] = $sensor_data ? [
                        'temperature' => $sensor_data['temperature'] ?? 0,
                        'soil_moisture' => $sensor_data['soil_moisture'] ?? 0,
                        'humidity' => $sensor_data['humidity'] ?? 0,
                        'irrigation' => $relay['status'] ?? 0
                    ] : [
                        'temperature' => 0,
                        'soil_moisture' => 0,
                        'humidity' => 0,
                        'irrigation' => 0
                    ];
                }
            }
            return $result;
        } catch (PDOException $e) {
            error_log("Lỗi trong getSensorData: " . $e->getMessage());
            return [];
        }
    }

    public function getChartData($garden_id = null, $userId, $isAdmin) {
        try {
            $result = [];
            if ($garden_id !== null) {
                $sql = "SELECT soil_moisture, temperature, humidity, created_at
                        FROM sensor_readings
                        WHERE garden_id = :garden_id
                        AND created_at >= NOW() - INTERVAL 24 HOUR
                        ORDER BY created_at ASC";
                $stmt = $this->conn->prepare($sql);
                $stmt->execute([':garden_id' => $garden_id]);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $labels = [];
                $temperature = [];
                $soil_moisture = [];
                $humidity = [];

                foreach ($results as $row) {
                    $labels[] = date("H:i", strtotime($row['created_at']));
                    $temperature[] = $row['temperature'] ?? 0;
                    $soil_moisture[] = $row['soil_moisture'] ?? 0;
                    $humidity[] = $row['humidity'] ?? 0;
                }

                $result[$garden_id] = [
                    'labels' => $labels,
                    'temperature' => $temperature,
                    'soil_moisture' => $soil_moisture,
                    'humidity' => $humidity
                ];
            } else {
                $gardens = $this->getAll($userId, $isAdmin);
                foreach ($gardens as $garden) {
                    $sql = "SELECT soil_moisture, temperature, humidity, created_at
                            FROM sensor_readings
                            WHERE garden_id = :garden_id
                            AND created_at >= NOW() - INTERVAL 24 HOUR
                            ORDER BY created_at ASC";
                    $stmt = $this->conn->prepare($sql);
                    $stmt->execute([':garden_id' => $garden['id']]);
                    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    $labels = [];
                    $temperature = [];
                    $soil_moisture = [];
                    $humidity = [];

                    foreach ($results as $row) {
                        $labels[] = date("H:i", strtotime($row['created_at']));
                        $temperature[] = $row['temperature'] ?? 0;
                        $soil_moisture[] = $row['soil_moisture'] ?? 0;
                        $humidity[] = $row['humidity'] ?? 0;
                    }

                    $result[$garden['id']] = [
                        'labels' => $labels,
                        'temperature' => $temperature,
                        'soil_moisture' => $soil_moisture,
                        'humidity' => $humidity
                    ];
                }
            }
            return $result;
        } catch (PDOException $e) {
            error_log("Lỗi trong getChartData: " . $e->getMessage());
            return [];
        }
    }

    public function getAlerts($garden_id = null, $userId, $isAdmin) {
        try {
            $result = [];
            if ($garden_id !== null) {
                $sql = "SELECT message, 
                               CASE 
                                   WHEN message LIKE '%thiếu nước%' THEN 'danger'
                                   WHEN message LIKE '%lỗi%' THEN 'warning'
                                   ELSE 'info'
                               END AS severity
                        FROM alerts
                        WHERE mcu_id IN (SELECT mcu_id FROM microcontrollers WHERE garden_id = :garden_id)
                        AND timestamp >= NOW() - INTERVAL 24 HOUR
                        ORDER BY timestamp DESC
                        LIMIT 5";
                $stmt = $this->conn->prepare($sql);
                $stmt->execute([':garden_id' => $garden_id]);
                $result[$garden_id] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } else {
                $gardens = $this->getAll($userId, $isAdmin);
                foreach ($gardens as $garden) {
                    $sql = "SELECT message, 
                                   CASE 
                                       WHEN message LIKE '%thiếu nước%' THEN 'danger'
                                       WHEN message LIKE '%lỗi%' THEN 'warning'
                                       ELSE 'info'
                                   END AS severity
                            FROM alerts
                            WHERE mcu_id IN (SELECT mcu_id FROM microcontrollers WHERE garden_id = :garden_id)
                            AND timestamp >= NOW() - INTERVAL 24 HOUR
                            ORDER BY timestamp DESC
                            LIMIT 5";
                    $stmt = $this->conn->prepare($sql);
                    $stmt->execute([':garden_id' => $garden['id']]);
                    $result[$garden['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                }
            }
            return $result;
        } catch (PDOException $e) {
            error_log("Lỗi trong getAlerts: " . $e->getMessage());
            return [];
        }
    }

    public function toggleIrrigation($garden_id) {
        try {
            $sql = "SELECT status FROM relay_controls 
                    WHERE garden_id = :garden_id 
                    ORDER BY updated_at DESC 
                    LIMIT 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':garden_id' => $garden_id]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);
            $new_status = $current ? ($current['status'] ? 0 : 1) : 1;

            $sql_update = "INSERT INTO relay_controls (garden_id, device_name, status, updated_at)
                          VALUES (:garden_id, 'irrigation', :status, NOW())";
            $stmt_update = $this->conn->prepare($sql_update);
            return $stmt_update->execute([
                ':garden_id' => $garden_id,
                ':status' => $new_status
            ]);
        } catch (PDOException $e) {
            error_log("Lỗi trong toggleIrrigation: " . $e->getMessage());
            return false;
        }
    }
}
?>
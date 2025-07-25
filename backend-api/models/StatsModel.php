<?php
require_once __DIR__ . '/../config/database.php';

class StatsModel {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAccessibleGardens($userId, $isAdmin) {
        try {
            $sql = "SELECT 
                        g.id as garden_id, 
                        g.garden_names as garden_name,
                        g.location,
                        g.status,
                        u.id as user_id,
                        u.full_name as user_full_name,
                        u.username
                    FROM gardens g
                    INNER JOIN users u ON g.user_id = u.id
                    WHERE g.status = 'Hoạt động'";
            
            if (!$isAdmin) {
                $sql .= " AND g.user_id = :user_id";
            }
            
            $stmt = $this->conn->prepare($sql);
            
            if (!$isAdmin) {
                $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            }
            
            $stmt->execute();
            $gardens = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Fetched " . count($gardens) . " accessible gardens for user $userId: " . json_encode($gardens));
            return $gardens;
        } catch (PDOException $e) {
            error_log("Error in getAccessibleGardens for user $userId: " . $e->getMessage());
            throw new Exception('Lỗi khi lấy danh sách vườn: ' . $e->getMessage(), 500);
        }
    }

    public function getGardenStats($gardenIds, $userId, $isAdmin, $startDate = null, $endDate = null) {
        try {
            $stats = [];
            
            // Lấy danh sách vườn mà user có quyền truy cập
            $accessibleGardens = $this->getAccessibleGardens($userId, $isAdmin);
            $accessibleGardenIds = array_column($accessibleGardens, 'garden_id');
            
            // Lọc các gardenIds được phép
            $allowedGardenIds = array_intersect($gardenIds, $accessibleGardenIds);
            
            foreach ($allowedGardenIds as $gardenId) {
                // Lấy thông tin vườn
                $gardenInfo = $this->getGardenInfo($gardenId);
                if (!$gardenInfo) {
                    error_log("No garden info found for garden ID $gardenId");
                    continue;
                }
                
                // Lấy garden_number từ garden_assignments
                $gardenNumber = $this->getGardenNumber($gardenId);
                if ($gardenNumber === null) {
                    error_log("No garden_number found for garden ID $gardenId");
                    continue;
                }
                
                // Lấy thống kê cảm biến
                $sensorStats = $this->getSensorStats($gardenNumber, $startDate, $endDate);
                
                // Lấy thống kê theo thời gian
                $monthlyStats = $this->getMonthlyStats($gardenNumber, $startDate, $endDate);
                
                $stats[] = [
                    'id' => $gardenId,
                    'name' => $gardenInfo['garden_names'] ?? 'Không tên',
                    'owner_name' => $gardenInfo['owner_name'] ?? 'Không xác định',
                    'avg_temperature' => isset($sensorStats['avg_temperature']) ? floatval($sensorStats['avg_temperature']) : 0,
                    'max_temperature' => isset($sensorStats['max_temperature']) ? floatval($sensorStats['max_temperature']) : 0,
                    'min_temperature' => isset($sensorStats['min_temperature']) ? floatval($sensorStats['min_temperature']) : 0,
                    'avg_humidity' => isset($sensorStats['avg_humidity']) ? floatval($sensorStats['avg_humidity']) : 0,
                    'max_humidity' => isset($sensorStats['max_humidity']) ? floatval($sensorStats['max_humidity']) : 0,
                    'min_humidity' => isset($sensorStats['min_humidity']) ? floatval($sensorStats['min_humidity']) : 0,
                    'avg_soil_moisture' => isset($sensorStats['avg_soil_moisture']) ? floatval($sensorStats['avg_soil_moisture']) : 0,
                    'max_soil_moisture' => isset($sensorStats['max_soil_moisture']) ? floatval($sensorStats['max_soil_moisture']) : 0,
                    'min_soil_moisture' => isset($sensorStats['min_soil_moisture']) ? floatval($sensorStats['min_soil_moisture']) : 0,
                    'avg_water_level' => isset($sensorStats['avg_water_level']) ? floatval($sensorStats['avg_water_level']) : 0,
                    'max_water_level' => isset($sensorStats['max_water_level']) ? floatval($sensorStats['max_water_level']) : 0,
                    'min_water_level' => isset($sensorStats['min_water_level']) ? floatval($sensorStats['min_water_level']) : 0,
                    'rain_percentage' => isset($sensorStats['rain_percentage']) ? floatval($sensorStats['rain_percentage']) : 0,
                    'monthly_stats' => $monthlyStats
                ];
            }
            
            error_log("Fetched stats for " . count($stats) . " gardens for user $userId: " . json_encode($stats));
            return $stats;
        } catch (Exception $e) {
            error_log("Error in getGardenStats for user $userId: " . $e->getMessage());
            throw new Exception('Lỗi khi lấy thống kê vườn: ' . $e->getMessage(), 500);
        }
    }

    private function getGardenNumber($gardenId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT garden_number 
                FROM garden_assignments 
                WHERE garden_id = :garden_id
                ORDER BY assigned_at DESC
                LIMIT 1
            ");
            $stmt->bindParam(':garden_id', $gardenId, PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $gardenNumber = $result ? $result['garden_number'] : null;
            error_log("Fetched garden_number $gardenNumber for garden ID $gardenId");
            return $gardenNumber;
        } catch (PDOException $e) {
            error_log("Error in getGardenNumber for garden ID $gardenId: " . $e->getMessage());
            return null;
        }
    }

    private function getSensorStats($gardenNumber, $startDate, $endDate) {
        try {
            $whereClause = "WHERE garden_number = :garden_number";
            $params = [':garden_number' => $gardenNumber];
            
            if ($startDate && $endDate) {
                $whereClause .= " AND created_at BETWEEN :start_date AND :end_date";
                $params[':start_date'] = $startDate;
                $params[':end_date'] = $endDate;
            }
            
            $sql = "SELECT 
                        AVG(temperature) as avg_temperature,
                        MAX(temperature) as max_temperature,
                        MIN(temperature) as min_temperature,
                        AVG(humidity) as avg_humidity,
                        MAX(humidity) as max_humidity,
                        MIN(humidity) as min_humidity,
                        AVG(soil_moisture) as avg_soil_moisture,
                        MAX(soil_moisture) as max_soil_moisture,
                        MIN(soil_moisture) as min_soil_moisture,
                        AVG(water_level_cm) as avg_water_level,
                        MAX(water_level_cm) as max_water_level,
                        MIN(water_level_cm) as min_water_level,
                        (SUM(is_raining) / COUNT(*)) * 100 as rain_percentage
                    FROM sensor_readings
                    $whereClause";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            
            if (empty($result)) {
                error_log("No sensor stats found for garden_number $gardenNumber between $startDate and $endDate");
                return [
                    'avg_temperature' => 0,
                    'max_temperature' => 0,
                    'min_temperature' => 0,
                    'avg_humidity' => 0,
                    'max_humidity' => 0,
                    'min_humidity' => 0,
                    'avg_soil_moisture' => 0,
                    'max_soil_moisture' => 0,
                    'min_soil_moisture' => 0,
                    'avg_water_level' => 0,
                    'max_water_level' => 0,
                    'min_water_level' => 0,
                    'rain_percentage' => 0
                ];
            }
            
            $result = array_map(function($value) {
                return $value !== null ? floatval($value) : 0;
            }, $result);
            
            error_log("Fetched sensor stats for garden_number $gardenNumber: " . json_encode($result));
            return $result;
        } catch (PDOException $e) {
            error_log("Error in getSensorStats for garden_number $gardenNumber: " . $e->getMessage());
            return [
                'avg_temperature' => 0,
                'max_temperature' => 0,
                'min_temperature' => 0,
                'avg_humidity' => 0,
                'max_humidity' => 0,
                'min_humidity' => 0,
                'avg_soil_moisture' => 0,
                'max_soil_moisture' => 0,
                'min_soil_moisture' => 0,
                'avg_water_level' => 0,
                'max_water_level' => 0,
                'min_water_level' => 0,
                'rain_percentage' => 0
            ];
        }
    }

    private function getMonthlyStats($gardenNumber, $startDate, $endDate) {
        try {
            $whereClause = "WHERE garden_number = :garden_number";
            $params = [':garden_number' => $gardenNumber];
            
            if ($startDate && $endDate) {
                $whereClause .= " AND created_at BETWEEN :start_date AND :end_date";
                $params[':start_date'] = $startDate;
                $params[':end_date'] = $endDate;
            }
            
            $sql = "SELECT 
                        DATE_FORMAT(created_at, '%Y-%m') as month,
                        AVG(temperature) as avg_temperature,
                        AVG(humidity) as avg_humidity,
                        AVG(soil_moisture) as avg_soil_moisture,
                        AVG(water_level_cm) as avg_water_level,
                        (SUM(is_raining) / COUNT(*)) * 100 as rain_percentage
                    FROM sensor_readings
                    $whereClause
                    GROUP BY month
                    ORDER BY month";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($result)) {
                error_log("No monthly stats found for garden_number $gardenNumber between $startDate and $endDate");
                return [];
            }
            
            $result = array_map(function($row) {
                return [
                    'month' => $row['month'],
                    'avg_temperature' => isset($row['avg_temperature']) ? floatval($row['avg_temperature']) : 0,
                    'avg_humidity' => isset($row['avg_humidity']) ? floatval($row['avg_humidity']) : 0,
                    'avg_soil_moisture' => isset($row['avg_soil_moisture']) ? floatval($row['avg_soil_moisture']) : 0,
                    'avg_water_level' => isset($row['avg_water_level']) ? floatval($row['avg_water_level']) : 0,
                    'rain_percentage' => isset($row['rain_percentage']) ? floatval($row['rain_percentage']) : 0
                ];
            }, $result);
            
            error_log("Fetched monthly stats for garden_number $gardenNumber: " . count($result) . " records: " . json_encode($result));
            return $result;
        } catch (PDOException $e) {
            error_log("Error in getMonthlyStats for garden_number $gardenNumber: " . $e->getMessage());
            return [];
        }
    }

    private function getGardenInfo($gardenId) {
        try {
            $stmt = $this->conn->prepare("
                SELECT g.*, u.full_name as owner_name 
                FROM gardens g
                JOIN users u ON g.user_id = u.id
                WHERE g.id = ?
            ");
            $stmt->execute([$gardenId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("Fetched garden info for garden ID $gardenId: " . ($result ? json_encode($result) : 'Not found'));
            return $result;
        } catch (PDOException $e) {
            error_log("Error in getGardenInfo for garden ID $gardenId: " . $e->getMessage());
            return null;
        }
    }
}
?>
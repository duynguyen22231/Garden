<?php
require_once __DIR__ . '/../config/database.php';

class GardenModel {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAllGardens($search = '') {
        try {
            if (!empty($search)) {
                if (is_numeric($search) && (int)$search == $search) {
                    $stmt = $this->conn->prepare("
                        SELECT gardens.*, users.full_name AS owner_name
                        FROM gardens
                        JOIN users ON gardens.user_id = users.id
                        WHERE gardens.id = :search_id
                        ORDER BY gardens.id ASC
                    ");
                    $stmt->bindValue(':search_id', (int)$search, PDO::PARAM_INT);
                } else {
                    $stmt = $this->conn->prepare("
                        SELECT gardens.*, users.full_name AS owner_name
                        FROM gardens
                        JOIN users ON gardens.user_id = users.id
                        WHERE gardens.garden_names LIKE :search OR gardens.location LIKE :search OR users.full_name LIKE :search
                        ORDER BY gardens.id ASC
                    ");
                    $stmt->bindValue(':search', '%' . $search . '%');
                }
            } else {
                $stmt = $this->conn->prepare("
                    SELECT gardens.*, users.full_name AS owner_name
                    FROM gardens
                    JOIN users ON gardens.user_id = users.id
                    ORDER BY gardens.id ASC
                ");
            }
    
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            // Chuyển đổi dữ liệu ảnh thành base64 để hiển thị trên web
            foreach ($results as &$garden) {
                if ($garden['img']) {
                    $garden['img'] = base64_encode($garden['img']); // Chuyển BLOB thành base64
                }
            }
            return $results;
    
        } catch (PDOException $e) {
            return ['error' => $e->getMessage()];
        }
    }
    
    public function getStatusOptions() {
        $stmt = $this->conn->prepare("SHOW COLUMNS FROM gardens LIKE 'status'");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
        if ($row && isset($row['Type'])) {
            preg_match("/^enum\((.*)\)$/", $row['Type'], $matches);
            $enum = explode(",", $matches[1]);
            $enum = array_map(function ($value) {
                return trim($value, " '");
            }, $enum);
    
            return array_map(function ($value) {
                return ['id' => $value, 'name' => $value];
            }, $enum);
        }
    
        return [];
    }

    public function getAllUsers() {
        try {
            $stmt = $this->conn->prepare("SELECT id, full_name FROM users");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function getGardenById($id) {
        $stmt = $this->conn->prepare("
            SELECT gardens.*, users.full_name AS owner_name
            FROM gardens
            JOIN users ON gardens.user_id = users.id
            WHERE gardens.id = ?
        ");
        $stmt->execute([$id]);
        $garden = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($garden && $garden['img']) {
            $garden['img'] = base64_encode($garden['img']); // Chuyển BLOB thành base64
        }
        return $garden;
    }
    

    public function saveGarden($data, $files) {
        $imageData = $this->uploadImage($files['img']);
        $stmt = $this->conn->prepare("INSERT INTO gardens (garden_names, location, latitude, longitude, area, note, status, user_id, img) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([ 
            $data['garden_names'], $data['location'], $data['latitude'], $data['longitude'],
            $data['area'], $data['note'], $data['status'], $data['user_id'], $imageData 
        ]);
    }

    public function updateGarden($id, $data, $files) {
        $imageData = null;
        if (isset($files['img']) && $files['img']['tmp_name']) {
            $imageData = $this->uploadImage($files['img']);
        }

        $sql = "UPDATE gardens SET garden_names = ?, location = ?, latitude = ?, longitude = ?, area = ?, note = ?, status = ?, user_id = ?";
        $params = [
            $data['garden_names'], $data['location'], $data['latitude'], $data['longitude'],
            $data['area'], $data['note'], $data['status'], $data['user_id']
        ];

        if ($imageData !== null) {
            $sql .= ", img = ?";
            $params[] = $imageData;
        }

        $sql .= " WHERE id = ?";
        $params[] = $id;

        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($params);
    }

    public function deleteGarden($id) {
        $stmt = $this->conn->prepare("DELETE FROM gardens WHERE id = ?");
        return $stmt->execute([$id]);
    }

    private function uploadImage($file) {
        // Kiểm tra nếu không có file upload
        if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        // Kiểm tra lỗi upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            error_log("Lỗi upload file: " . $file['error']);
            return null;
        }

        // Kiểm tra loại file (chỉ cho phép ảnh)
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $fileType = mime_content_type($file['tmp_name']);
        if (!in_array($fileType, $allowedTypes)) {
            error_log("Loại file không hợp lệ: " . $fileType);
            return null;
        }

        // Kiểm tra kích thước file (tối đa 5MB)
        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $maxSize) {
            error_log("File quá lớn: " . $file['size'] . " bytes");
            return null;
        }

        // Đọc dữ liệu ảnh và trả về dưới dạng nhị phân
        $imageData = file_get_contents($file['tmp_name']);
        return $imageData;
    }
}
?>
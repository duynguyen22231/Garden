<?php
require_once __DIR__ . '/../config/database.php';

class HomeModel {
    private $conn;

    public function __construct() {
        global $conn;
        if (!$conn instanceof PDO) {
            error_log("HomeModel: Database connection is not a PDO instance");
            throw new Exception("Không thể kết nối đến cơ sở dữ liệu.");
        }
        $this->conn = $conn;
    }

    public function userExists($userId) {
        try {
            $stmt = $this->conn->prepare("SELECT id FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $exists = $stmt->fetchColumn() !== false;
            error_log("userExists: userId=$userId, exists=" . ($exists ? 'true' : 'false'));
            return $exists;
        } catch (PDOException $e) {
            error_log("userExists: Error: " . $e->getMessage());
            throw new Exception("Lỗi khi kiểm tra người dùng: " . $e->getMessage());
        }
    }

    public function getAll($userId, $isAdmin) {
        try {
            error_log("getAll: Fetching gardens for userId=$userId, isAdmin=$isAdmin");
            $sql = "SELECT g.id, g.garden_names, g.location, g.longitude, g.latitude, g.note, g.area, 
                           g.created_at, g.status, g.user_id, g.img, u.full_name AS owner_name
                    FROM gardens g
                    LEFT JOIN users u ON g.user_id = u.id
                    WHERE g.status = 'Hoạt động'";
            $params = [];
            if (!$isAdmin) {
                $sql .= " AND g.user_id = :user_id";
                $params[':user_id'] = $userId;
            }
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $gardens = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($gardens as &$garden) {
                $garden['img_url'] = !empty($garden['img']) 
                    ? 'data:image/jpeg;base64,' . base64_encode($garden['img'])
                    : '';
                $garden['img_id'] = $garden['id'];
                unset($garden['img']);
            }
            error_log("getAll: Found " . count($gardens) . " gardens");
            return $gardens;
        } catch (PDOException $e) {
            error_log("getAll: Error: " . $e->getMessage());
            throw new Exception("Lỗi khi lấy danh sách vườn: " . $e->getMessage());
        }
    }

    public function getAllUsers() {
        try {
            $sql = "SELECT id, username, email, full_name, administrator_rights 
                    FROM users";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("getAllUsers: Found " . count($users) . " users");
            return $users;
        } catch (PDOException $e) {
            error_log("getAllUsers: Error: " . $e->getMessage());
            throw new Exception("Lỗi khi lấy danh sách người dùng: " . $e->getMessage());
        }
    }

    public function saveGarden($data, $files) {
        try {
            $imageData = $this->uploadImage($files['image'] ?? null);
            
            // Nếu không có ảnh, gán giá trị null thay vì bỏ qua
            if ($imageData === null) {
                $imageData = null; // Rõ ràng gán null
            }

            $this->conn->beginTransaction();
            $sql = "INSERT INTO gardens (user_id, garden_names, location, area, note, latitude, longitude, img, status, created_at)
                    VALUES (:user_id, :garden_names, :location, :area, :note, :latitude, :longitude, :img, 'Hoạt động', NOW())";
            $stmt = $this->conn->prepare($sql);
            
            $params = [
                ':user_id' => $data['user_id'],
                ':garden_names' => $data['name'],
                ':location' => $data['location'] ?? null,
                ':area' => $data['area'] ?? null,
                ':note' => $data['note'] ?? 'Không có ghi chú',
                ':latitude' => $data['latitude'],
                ':longitude' => $data['longitude'],
                ':img' => $imageData
            ];

            $result = $stmt->execute($params);
            error_log("saveGarden: user_id={$data['user_id']}, garden_names={$data['name']}, success=" . ($result ? 'true' : 'false'));
            $this->conn->commit();
            return $result;
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("saveGarden: Error: " . $e->getMessage());
            throw new Exception("Lỗi khi lưu vườn: " . $e->getMessage());
        }
    }

    private function uploadImage($file) {
        if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
            error_log("uploadImage: No file uploaded or no file selected");
            return null;
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            error_log("uploadImage: Upload error code: " . $file['error']);
            return null;
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $fileType = mime_content_type($file['tmp_name']);
        if (!in_array($fileType, $allowedTypes)) {
            error_log("uploadImage: Invalid file type: " . $fileType);
            return null;
        }

        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $maxSize) {
            error_log("uploadImage: File too large: " . $file['size'] . " bytes");
            return null;
        }

        $imageData = file_get_contents($file['tmp_name']);
        if ($imageData === false) {
            error_log("uploadImage: Failed to read file contents");
            return null;
        }
        
        error_log("uploadImage: Successfully read image data, size: " . strlen($imageData) . " bytes");
        return $imageData;
    }
}
?>